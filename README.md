# EduPredict

**Machine Learning for Student Employability and Dropout Prediction**: a web-based predictive analytics system for the University of Caloocan City (UCC), designed to be adaptable to other institutions.

It analyzes academic, socioeconomic, behavioral, and skills/experience data to estimate employability, classify dropout risk, show contributing factors and a program-shift indicator, suggest PSOC-based career matches, and recommend institutional actions. Results are advisory only; they never decide admission, academic standing, employment, or discipline.

| | |
|---|---|
| Backend / UI | Laravel 12, PHP 8.3, Blade, Tailwind CSS, Livewire, Chart.js |
| Database | MariaDB 11.4 (MySQL-compatible) |
| Runs in | Docker (2 core containers: `app`, `db` + optional phpMyAdmin) |
| AI | OpenRouter (free-tier models) for text phrasing; the app works without a key |
| ML | Python/scikit-learn models are trained separately and plugged in later (`PredictorInterface`) |

---

## 1. What to install (once)

1. **Docker Desktop** and, on Windows, WSL 2:
   - Check virtualization is on: Task Manager > Performance > CPU > "Virtualization: Enabled" (if disabled, enable it in BIOS).
   - Open PowerShell **as Administrator**, run `wsl --install`, then **restart the PC**.
   - Install Docker Desktop from https://www.docker.com/products/docker-desktop/ (keep "Use WSL 2" ticked), open it, and wait for **Engine running**.
   - The full from-scratch walkthrough (including how the project was created) is in [`SETUP.md`](SETUP.md).
2. **Git**: https://git-scm.com/download/win
3. **Cursor** (our editor): https://cursor.com
4. You do **not** need to install PHP, Composer, Node, MySQL, or XAMPP. They all live inside Docker. Close XAMPP while working.

Check it works (Docker Desktop must be open and say "Engine running"):
```powershell
docker --version
docker compose version
docker run hello-world
```

## 2. First-time setup (every teammate)

```powershell
git clone https://github.com/kitkatz258/EduPredict.git
cd EduPredict

docker compose up -d --build
docker compose exec app composer install
copy src\.env.example src\.env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app npm install
docker compose exec app npm run build
```
The first build takes several minutes. Then open:

| What | URL |
|---|---|
| The app | http://localhost:8000 |
| phpMyAdmin (DB viewer) | http://localhost:8080 (server `db`, user `edupredict`, password `secret`) |
| Vite dev server (when running) | http://localhost:5173 |

Demo accounts for each role are in [section 9](#9-demo-accounts). The same command loads colleges, programs, questionnaire items, the PSOC starter set, interventions, and the synthetic cohort.

Start the frontend dev server when working on the UI:
```powershell
docker compose exec app npm run dev -- --host
```

## 3. Daily commands

Run all of these from the **project root** (the folder with `docker-compose.yml`), not from `src`.

```powershell
docker compose up -d                          # start
docker compose down                           # stop (database kept)
docker compose down -v                        # stop AND wipe the database (fresh start)
docker compose ps                             # what is running
docker compose logs -f app                    # live logs
docker compose exec app bash                  # shell inside the Laravel container
docker compose exec app php artisan migrate   # run migrations
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
docker compose exec app composer require vendor/package
```
Rule: anything `php`, `artisan`, `composer` or `npm` goes through `docker compose exec app ...`. The database host inside Laravel is `db`, never `localhost`.

## 4. Project layout

```
EduPredict/
├── Dockerfile, docker-compose.yml     Docker setup
├── .cursor/rules/                     Standing rules for Cursor
├── SETUP.md                           Detailed Docker + Laravel guide
├── src/                               The Laravel 12 project
│   └── cursor/                        Cursor instructions
│       ├── 01-initial-project.md      Master spec (milestones M0 to M12)
│       ├── 02-...md                   Later adjustment requests
│       ├── ui-reference/              UI screenshots
│       └── samples/                   Sample grade reports (do not commit real personal data)
└── ml/                                (later) Python notebooks and exported models
```

## 5. Git workflow (please follow)

- `main` always works. Never commit directly to it once the base is built.
- One branch per task: `git checkout -b feature/short-name`, then commit, push, and open a Pull Request. Someone else reviews before merge.
- Pull before you start: `git pull`.
- After pulling changes that touch `composer.json` or `package.json`: run `docker compose exec app composer install` and `npm install`. After changes to migrations: `php artisan migrate`.
- **Never commit `.env` or any API key.** Share keys privately. Never commit real student data or real grade reports.
- The database is shared through **migrations and seeders**, not by sharing the Docker volume.

## 6. Working with Cursor

- Open the **root** folder (`EduPredict`) in Cursor, not `src`.
- Cursor reads `.cursor/rules/edupredict.mdc` automatically. The big spec is `src/cursor/01-initial-project.md`.
- Fixes and polish go into new numbered files (`02-...md`, `03-...md`) with: what is wrong, what it should do, what not to touch.
- Review what Cursor builds one milestone at a time, and make sure you can explain it. We will be asked to defend this system.

## 7. AI (OpenRouter) settings

In `src/.env` (never committed):
```
AI_PROVIDER=openrouter
AI_BASE_URL=https://openrouter.ai/api/v1
AI_API_KEY=            # your own OpenRouter key; leave empty to run without AI
AI_MODEL=              # a ":free" text model
AI_VISION_MODEL=       # optional
AI_FALLBACK_MODELS=
AI_DAILY_LIMIT=40
AI_PER_MINUTE_LIMIT=20
```
Leave `AI_API_KEY` empty to run every AI feature on its rule-based or manual fallback. Free models are limited to roughly 20 requests/minute and about 50/day per account. The app stops calling the API at the daily and per-minute limits and shows the standard text instead. Automated tests force the key empty, so the suite cannot call OpenRouter.

## 8. Troubleshooting (problems we already hit)

| Problem | Fix |
|---|---|
| `docker` is not recognized in the terminal | Close and reopen Cursor/PowerShell. Check Docker Desktop is running. |
| `no configuration file provided` | You are inside `src`. Run `cd ..` to the project root. |
| `read-only file system` or `exec format error` from a container | Docker's stored images are damaged. Docker Desktop > bug icon (Troubleshoot) > **Clean up data** > tick **WSL 2** only > Delete, then `docker compose build`, `docker compose pull`, `docker compose up -d`. |
| Red "tempnam(): file created in the system's temporary directory" page | `docker compose exec app chown -R www-data:www-data storage bootstrap/cache` then `docker compose exec app php artisan view:clear` (or `chmod -R 777 storage bootstrap/cache` for local dev). |
| Port already in use | Stop XAMPP (Apache/MySQL), or change the left-hand port number in `docker-compose.yml`. |
| `no such service` | Use the service names `app`, `db`, `phpmyadmin` (not the container names). |
| Everything is slow on Windows | Keep the project in WSL's filesystem (`\\wsl$\Ubuntu\home\you\EduPredict`) instead of `C:\`. |
| Laravel can't connect to the DB | `src/.env` must have `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_PORT=3306`, `DB_DATABASE=edupredict`, `DB_USERNAME=edupredict`, `DB_PASSWORD=secret`. |

## 9. Demo accounts

Development only. Password for every demo account: `Password123!`

| Role | Sign-in identifier |
|---|---|
| Student | 2024-00001 |
| Department Head | depthead@edupredict.test |
| Dean | dean@edupredict.test |
| Administrator | admin@edupredict.test |

Students sign in with their student number. Staff sign in with their work email.

The pilot scope is the UCC College of Liberal Arts and Sciences (CLAS) with eight programs: BA Communication, Bachelor of Public Administration, and BS Computer Science, Entertainment and Multimedia Computing, Information Systems, Information Technology, Mathematics, and Psychology. The Department Head reviews individual students in their department (or one program, when narrowed). The Dean sees aggregated CLAS figures only, never individual students. Faculty is a legacy role: existing faculty accounts are kept for history but cannot sign in.

Sixty synthetic students (`SYN-0001` … `SYN-0060`, emails `syntheticN@edupredict.test`) sit across the CLAS programs. Of those with a stored prediction, 12 are high risk with a disengagement flag, 12 are moderate with a program-fit flag, 12 are low with mixed signals, and 24 are low with no shift pattern. A few use story names (Mara Bautista, Nico Reyes, Rico Dela Cruz, Liza Ramos, and others). Sam Student (`2024-00001`, BSIS) has a complete consent record and no prediction yet, so the student demo can request one. Una Applicant (`2024-88888`) is on the institution list and is not registered.

`npm` is not required to boot the seeded app. Build the frontend when you change Blade, CSS, or JavaScript:

```powershell
docker compose exec app npm install
docker compose exec app npm run build
```

## 11. Tests

From the project root:

```powershell
docker compose exec app php artisan test
```

PHPUnit uses an in-memory SQLite database. It does not call OpenRouter. Grade OCR tests run when `src/cursor/samples/` contains the portal samples and the container has Python and Tesseract; otherwise those cases skip with a message.

## 12. How to plug in the trained models

Do not drop a model file into the app until that work is explicitly approved. The prediction path is already behind one interface.

1. Implement `App\Contracts\PredictorInterface` in a new class. The placeholder is `App\Services\Prediction\HeuristicPredictor`. Its version string is `placeholder-heuristic-v0`. Keep that label on any output that still uses the placeholder. Do not describe heuristic scores as trained model results.
2. Bind the new class in `App\Providers\AppServiceProvider`. The container currently maps `PredictorInterface` to `HeuristicPredictor`. The `http` and `onnx` driver names are reserved and throw until a real driver exists.
3. Select the driver with `PREDICTOR_DRIVER` in `src/.env`. The values are read in `config/edupredict.php` under `predictor.driver`.
4. Match the feature names and snapshot shape in `src/docs/ml-feature-contract.md`. `App\Services\Prediction\FeatureBuilder` is the only place that assembles a feature set. Predictions are insert-only, and each row stores `model_version`.

Career scores, program-shift labels, and institutional-action selection stay deterministic. A future model does not choose those.

## 13. Known gaps

Intentionally unfinished work is listed in [`src/docs/KNOWN_GAPS.md`](src/docs/KNOWN_GAPS.md).

## 10. Privacy and ethics

The system handles sensitive student data under RA 10173 (Data Privacy Act): informed consent, role-based access, encrypted sensitive fields, de-identified data sent to the AI API, audit logs. Predictions are estimates, not guarantees, and are not clinical or diagnostic.
