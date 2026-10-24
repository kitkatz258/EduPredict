# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

EduPredict is a student thesis project for the University of Caloocan City (UCC). It predicts student employability and dropout risk. A Laravel 12 app (`src/`) serves the product. A separate Python research service (`ml/`) is used only for demos. See `AGENTS.md` for style, commit, and PR conventions. `README.md` lists demo accounts and troubleshooting.

## Commands

Run everything from the repo root. PHP, artisan, Composer, and npm always go through the `app` container. Inside Laravel, the DB host is `db`, never `localhost`.

```bash
docker compose up -d --build                     # app :8000, MariaDB :3307, phpMyAdmin :8080, ml (internal only)
docker compose exec app composer install
docker compose exec app php artisan migrate:fresh --seed   # rebuilds the shared dev DB with the synthetic cohort
docker compose exec app npm run dev -- --host    # Vite :5173; `npm run build` after Blade/CSS/JS changes
docker compose exec app php artisan test                                   # full PHPUnit suite
docker compose exec app php artisan test --filter=RoleScopeTest            # one class or method
docker compose exec app php artisan test tests/Feature/Prediction          # one directory
docker compose exec app vendor/bin/pint --test   # format check (drop --test to fix)
```

The default `phpunit.xml` *forces* SQLite `:memory:`, `PREDICTOR_DRIVER=heuristic`, and an empty `AI_API_KEY`. Tests never touch MariaDB or Postgres and never call OpenRouter. Grade-OCR tests skip unless `src/cursor/samples/` has the gitignored portal samples and the container has Python and Tesseract.

PostgreSQL runs as a separate compose project with its own ports (8017/5433/3308) and volumes:
```bash
docker compose -f docker-compose.postgres.yml up -d --build
docker compose -f docker-compose.postgres.yml exec app php artisan test --configuration=phpunit.pgsql.xml
```
`migrate:fresh` may only target `edupredict_testing`. `PostgresTestDatabaseGuard` (run in `AppServiceProvider::boot`) enforces this. Details are in `src/docs/M34-POSTGRESQL-SUPABASE.md`.

Python, from `ml/` (3.11+ venv). Set `OMP_NUM_THREADS=1 OPENBLAS_NUM_THREADS=1 MKL_NUM_THREADS=1 PYTHONPATH=.` so cross-validation is reproducible:
```bash
python -m pip install -r requirements-api.txt   # requirements.txt for training
python -m pytest                                # or: python -m pytest tests/test_m33_api.py -k name
python -m src.run_m25 / python -m src.run_m27   # retrain; needs gitignored raw data in ml/data/raw/
ML_API_TOKEN=local-dev-ml-token python -m api.main
```

## Architecture

**Two separate prediction paths. Keep them separate.**
- Student predictions use `PredictorInterface`, which `AppServiceProvider` binds from `config('edupredict.predictor.driver')`. The default `heuristic` driver uses `HeuristicPredictor` (`placeholder-heuristic-v0`), a deterministic rule-based placeholder. Its output must never be described as a trained-model result. `http` binds `HttpPredictor`, which deliberately refuses a student `FeatureSet`. `onnx` throws.
- Research inference: `HttpResearchInferenceClient` calls the FastAPI `ml` service (`ml/api`, bearer `ML_API_TOKEN`). It sends explicit `pianda-v1` / `uci-697` source-schema payloads, matching `src/app/Services/Prediction/research-schemas.json`. The service loads saved joblib artifacts at startup and rejects UCC item codes and student fields. No path falls back between the two.
- `Services/Prediction/FeatureBuilder` is the only place that turns student records into a `FeatureSet`. `src/docs/ml-feature-contract.md` is the source of truth for features and weights. Read it before changing prediction inputs.
- Predictions are insert-only and each row stores `model_version`. A request needs consent and a completed submission of the current questionnaire version, and has a cooldown (`PredictionRequester`). Career matching (`Services/Career`), program-shift labels (`ProgramShiftEvaluator`), and intervention selection (`Services/Interventions`) stay deterministic and run after the prediction.

**Laravel layout conventions.**
- Most UI is class-based Livewire 4 components (`app/Livewire`) rendered inside thin controllers. Livewire bundles Alpine, so do not install Alpine separately. Do not add jQuery or DataTables. Every list extends `Livewire/Tables/BaseTable`. Each component and action must authorize its own operations, not just the route.
- Roles (`Enums/UserRole`) are student, department_head, dean, and administrator. `faculty` is a legacy value that cannot sign in. Routes use the `role:`, `active`, and `password.changed` middleware, and record access goes through the policies in `app/Policies`. Department heads see individual students in their scope. Deans see **aggregates only**, never individual students. Treat scope changes as security-sensitive and add regression tests.
- Students sign in with `student_number`. Staff sign in with email through the same field.
- Grades (`Services/Grades`) come from paste, PDF text, or OCR. OCR runs `src/tools/grade_table_ocr.py` with Tesseract. Philippine GWA is lower-is-better. INC is left out of the GWA and marks it provisional. Grade reports are versioned, and only current confirmed versions feed features.
- AI phrasing goes through `AiClientInterface` → `OpenRouterClient`, with `Deidentifier` applied before any call. Every AI feature has a rule-based fallback when `AI_API_KEY` is empty or rate limits are hit.
- Socioeconomic fields and answers are encrypted at the application level. Do not query them as plaintext.
- App-specific config is in `config/edupredict.php` (predictor, ML API, prediction thresholds, Postgres DB names).

**Specs and decisions.** Work is organized in milestones (M0–M36). The specs are `src/cursor/01-initial-project.md` plus numbered adjustment files (`02-…`, `08-…M33-M36…`). `src/docs/DECISIONS.md` records one line per decision made where the spec was silent. Add to it when you make such a choice. `src/docs/KNOWN_GAPS.md` lists intentional limitations. `src/cursor/rules/edupredict.mdc` holds standing rules: after each milestone, run a fresh migration with seeders, run the tests, then commit.

## Deployment

The thesis demo runs on Render (free) with Supabase PostgreSQL and synthetic data only. The pieces are `render.yaml`, `docker/render/Dockerfile` (built from the repo-root context, filtered by `.dockerignore`), and `docker/render/entrypoint.sh`. At boot the entrypoint caches config, runs `migrate --force`, and calls `edupredict:seed-demo-if-empty`, which seeds only while `users` is empty. The runbook is `src/docs/M35-RENDER-DEPLOYMENT.md`. Never change `APP_KEY` on a deployed database, because the encrypted fields depend on it. To build locally: `docker build -f docker/render/Dockerfile -t edupredict-render .`

## Gotchas

- On Windows, the bind-mounted `src/` makes the first request take tens of seconds. That delay is expected. Do not turn on config, route, or view caches in development as a fix.
- Never commit `.env`, real student data, or grade samples (`src/cursor/samples/`, `ml/data/raw/` are gitignored). Seeded data (`SYN-####`, demo password `Password123!`) is synthetic.
