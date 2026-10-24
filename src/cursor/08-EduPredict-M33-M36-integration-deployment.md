# EduPredict — M33–M36: API, Hosting, and Defense Readiness

> Read `src/cursor/01-initial-project.md`, `src/cursor/rules/edupredict.mdc`, M25–M27 research reports, and M30–M32 changes first. Work in separate small commits. Do not reset data or change APP_KEY. This plan is a staged implementation guide; actual panel revisions must be supplied and implemented only when documented.

## Non-negotiable scientific boundary
- M25 Pianda linear regression expects **original Pianda** CompetenceMean and PsyCapMean; approved UCC C/P means are *not* validated equivalents. Never silently map them.
- M27 UCI binary logistic models use **UCI schema**; none has a verified UCC deployment feature mapping. Scholarship-only baseline is unsuitable for student advice.
- Do not represent any research candidate as a validated UCC predictor. Keep `HeuristicPredictor` labelled as rule-based prototype; no false probability claims.
- Implement a research-demo inference endpoint for documented *source-schema* inputs, and keep the real student workflow on its existing clearly-labelled engine until a scientifically defensible mapping/model is approved.

## M33 — Isolated FastAPI service + model contract
1. Inspect existing `ml/` training artifacts, metadata, Python dependencies and Laravel PredictorInterface / driver config. Confirm exact model schema/version/expected output.
2. Create a minimal FastAPI service within `ml/` or `ml/api/` and a fourth local Docker Compose service. Build reproducibly from pinned dependencies and existing candidate joblibs; never retrain in the API startup path.
3. Endpoints: `/health/live`, `/health/ready`, `/v1/research/employability`, `/v1/research/dropout`. Request/response validation via Pydantic, strict source-feature schemas, model version, source-dataset/research-only flag. Dropout explicitly binary Dropout vs Not Dropout, and no Low/Moderate/High thresholds.
4. Use private network access and service-to-service authentication; do not publicly expose the inference endpoint by default. Prevent PII, raw questionnaire responses, credentials, and model payloads from entering logs. Configure resource/time limits and deterministic errors.
5. Integrate Laravel with a **separately guarded research/demo client**, or add an HTTP predictor driver that is explicitly disabled for real student requests until validity gates pass. Keep HeuristicPredictor intact and the existing dashboard disclaimer. No automatic fallback from failed ML to heuristic while labelling output ML; distinguish engines and errors.
6. Add Python tests for valid/invalid schemas, startup, inference, missing model, version metadata, and Laravel contract/timeout/error tests. Verify Docker networking locally. Report which exact calls are live, which are blocked, and why. Commit separately.

## M34 — Deployment/data-readiness
1. Audit every database migration/query for PostgreSQL support. Stage PostgreSQL in a new database/container; keep MariaDB and its volume unchanged. Back up source data (and restore-test backups) before attempting import.
2. Implement compatible migrations and documented import/verification plan: exact counts, foreign keys, passwords, consent, encrypted profiles/answers, student grade versions, history, interventions, audit logs. Do not `migrate:fresh` against preserved data. Preserve APP_KEY so encrypted fields remain readable.
3. Make production Dockerfile and Render configuration: PHP + extensions including pgsql, Composer install with prod flags, Vite assets, health checks, `PORT` binding, HTTPS/proxy trust, `APP_DEBUG=false`, secure secrets, session/cookie policy, no dev seeds or demo passwords.
4. Identify uploads/storage: Render free filesystem is ephemeral. Choose persistent object storage or an appropriate paid disk before accepting real uploads. Test grade OCR dependencies/worker behavior in production image; avoid storing uploaded sensitive files longer than needed.
5. Document environment variable names without values, build/start/migration/rollback commands, CORS/private network restrictions, costs and idle limits. Commit and stop for verification.

## M35 — Hosting and production smoke tests
1. Create a private FastAPI service where supported and Laravel web service, plus Postgres in a common region. Confirm plan capabilities before provisioning. Never reveal secrets or include them in commits.
2. Deploy only test/synthetic records initially. Run additive migrations and explicit import steps only after backups. Verify HTTPS, login/logout, new student registration, six-section assessment, grades upload, read-only history, department-level access, dean aggregation, administrator CRUD/CSV, SweetAlert, mobile UI, and FastAPI research demo separately.
3. Test failure modes: cold start/restarts, API down, invalid ML payload, timeout, DB reconnect, missing uploaded file, expired session, direct unauthorized URLs. Ensure user-facing errors are safe and understandable.
4. Collect evidence (URLs without sensitive data, response statuses, screenshots, logs with secrets redacted), rollback plan, and a known-good local demo fallback. Only mark deployed when tested end-to-end.

## M36 — Panel-requested adjustments and final QA
- **Inputs pending:** transcribed panel comments and resulting approved list of changes. Map each requested change to acceptance criteria, size, role/security impact, and whether it is necessary for final defense. Do not invent panel requirements.
- Prioritize blockers and high-impact corrections; defer nonessential redesigns. Run focused tests per fix, then entire Laravel/Python/JS suites, frontend build and live smoke tests.
- Check model disclosures, demo script accuracy, research-methodology claims, permissions, database backups, and secure data handling.
- Produce `docs/FINAL_DEPLOYMENT_AND_DEFENSE_CHECKLIST.md`, a known limitations section, deployed-versus-local feature matrix, and exact commit hashes.

## Stop/go gates
- API can load both source-schema research artifacts, returns versions and explicit research-only metadata, and refuses incompatible UCC inputs.
- Student predictions never mislabel heuristic output as trained ML or silently substitute questionnaire means.
- Database counts, identifiers, grade versions, encrypted profiles, saved predictions and histories are preserved or intentionally excluded from a clearly labelled synthetic deployment.
- Hosting shows no uncaught 500s during critical demos. Cross-role access tests pass, especially dean restrictions.
- If deployment or compatibility gates fail, preserve the verified local app and present research models via controlled source-dataset demo instead of fabricated UCC predictions.

## Recommended agent sequence
Complete M33 only; report actual tests and a commit. Then M34, review deployment plan, then M35. Start M36 only once panel changes have been supplied. Never combine all milestones into one uncontrolled change.
