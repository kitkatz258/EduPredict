# 07 — M32: End-to-End Defense Verification and Bug Fixes

> Read `src/cursor/01-initial-project.md`, `src/cursor/rules/edupredict.mdc`, the latest adjustments/decisions, and existing tests first. Do **M32 only**. The working Laravel app is the source of truth. Verify behavior; fix confirmed defects only. Commit once when complete; stop.

## Goal

Make EduPredict reliable for a live pre-final defense, especially across Laravel 12 + Livewire 4 browser interactions. Verify actual persistence, redirects, access controls, alerts, modal actions, historical snapshots, and layout behavior—not merely HTTP 200 or passing unit tests.

## Guardrails / preparation

1. Inspect the repository and `git status`; identify the current branch, latest commits, active database driver, Docker mounts, and existing tests. Do not assume database migration to PostgreSQL or FastAPI integration occurred.
2. Run within WSL project root (`~/projects/EduPredict`), using `docker compose exec app ...` for app commands. Preserve the existing MariaDB data, `APP_KEY`, `.env`, all ML files, source datasets, questionnaires, grades, predictions, and history.
3. Before any browser test that writes data, take a timestamped **database backup** and record the location. Use clearly identified synthetic/test accounts and test fixtures; don't edit production-like student records to simulate cases. Do not use `migrate:fresh`, `db:wipe`, mass re-seeding, or a destructive database reset.
4. Prefer a dedicated isolated test DB/browser environment. When testing in the preserved dev DB, note every temporary row created and remove ONLY those test rows safely after verification; confirm counts/invariants afterward. Never remove historical predictions just to clean the demo.
5. Treat approved `ucc-panel-approved-v1` question wording and scoring, role authorization, data privacy, model candidates, predictor interface, `placeholder-heuristic-v0` disclaimers, and existing record snapshots as immutable for this milestone.

## What to verify / fix

### A. Cross-app Livewire and frontend regression (highest priority)

- Audit shared dialog component(s), buttons, Alpine handlers, `wire:click`, `wire:submit`, dynamic action-name attributes, SweetAlert2 confirmations, and event listeners across Student, Department Head, Dean, and Administrator pages.
- Reproduce or regression-test the prior `Livewire\\Exceptions\\MethodNotFoundException: Public method [undefined] not found` failure. Assert that **no Livewire update payload has a call with `method: "undefined"`**; no click silently fails or invokes a nonexistent method. Add an appropriate automated browser or JS-level regression check for the shared dialog close/cancel action.
- Verify modal **Open, View, Edit, Save, Cancel, Close, Escape, backdrop click**, repeat open/close, and focus restore; confirm `wire:loading` and duplicate-submission prevention do not block legitimate follow-up actions.
- Watch browser console and Network for uncaught exceptions, 419/422/403/500 errors, failed Livewire requests, stale/duplicate component state, and missing assets. Expected authorization 403 and validation 422 must be deliberately tested and clearly distinguished from bugs.
- Verify SweetAlert2 backdrop dimming, cancel-vs-confirm semantics, readable success/error messages, and toast/alert display after successful **persisted** actions. Check no duplicate alerts.

### B. Student end-to-end assessment

- Direct root/sign-in/create-account toggle, login using student number, logout, invalid credentials, controlled registration requiring an eligible student, password visibility, basic recovery navigation, and responsive behavior.
- With a synthetic student who has no active questionnaire draft, start the **current** `ucc-panel-approved-v1` questionnaire (36 questions; A1–A6, S1–S6, B1–B6, C1–C8, P1–P6, E1–E4). Test back/continue, save/reload/resume, required-answer validation, socioeconomic encryption, submission, computed section means, context flags, and version labeling. Test legacy `draft-v1` access without rewriting or re-scoring it.
- Verify Skills / Certifications / Work Experience modal CRUD, archive/restore, OJT type, and optional sections. Test Grades upload/PDF-OCR or source text parsing when fixtures are available, portal paste stripping instructor names, manual entry, `INC` provisional GWA exclusion, draft deletion, immutable confirmed versioning, and a read-only View modal. Do not upload private student documents for this test.
- Verify optional Grades and Skills paths: completed current questionnaire + existing grades; completed questionnaire + no grades; completed questionnaire + partial supplemental information. Ensure absence is not stored as numeric zero or treated as proof of no ability. When truly insufficient, show an explicit refusal instead of a manufactured estimate.
- Request a prediction only when eligible; verify consent, 24-hour cooldown, authorization, result disclaimers, dashboard, career cards, and saved History attempt. Compare stored snapshot with questionnaire version, grades and skill data actually used; old attempts must not change when new inputs are saved. Avoid corrupting/mutating preserved history or creating unnecessary production-like predictions.

### C. Department Head and Dean permissions

- Department Head: dashboard aggregate view, separate Students list, search, risk/program/year filters, student-status modal, intervention review and status notes, and notifications/in-app routing that still exists (bell was intentionally removed). No raw socioeconomic answers or questionnaire details in unauthorized contexts.
- Explicitly verify scope boundaries for a Head assigned to one department or narrowed program; tampered IDs and request parameters must not expose other departments or students.
- Dean: CLAS-only aggregate dashboard with department/program/year/period filters; no names, identifiers, student detail pages, prediction histories, grade reports, interventions, or student-level exports. Test backend denial, including crafted URLs/filter parameters, not just hidden controls. Consider small-group disclosure risks but don't change aggregation policy without evidence/approval.

### D. Administrator full CRUD/interaction pass

- Dashboard filter changes work immediately and produce coherent charts/counts; no stray progress/help-card clutter.
- Users: role/status filters and search; Add/Edit modals; activate/deactivate confirmation; actual database persistence; legacy Faculty login disabled; own-admin protection where applicable.
- Eligible students: manual Add, CSV upload (valid/malformed/unsupported file), correct feedback counts, no login automatically created, duplicate handling, strict year 1–4; verify any upload actually persists/rejects correctly, not just a 200 response.
- Academic structure: college/department/program Add/Edit and active state where allowed; ensure CLAS pilot constraints and no cascade loss of linked students, grades, or predictions.
- Questionnaire: item/category/section filters, version locks once answered, appropriate publication controls, modals, no changes to panel-approved items through this milestone.
- PSOC occupations and Interventions: search/filter/View/Add/Edit, intervention activation/deactivation, eligibility help text, persistence, valid SweetAlert confirmations; historical recommended actions must stay unchanged.
- Activity Log: readable column labels, searching/filtering and pagination, meaningful log entries for actual actions. Account deletion requests: clear label, Pending/Approved/Rejected/All filter and review confirmation; approval deactivates sign-in without deleting prediction/history.
- Verify responsive tables/pagination and the fixed staff sidebar (wheel scrolling on sidebar should not scroll the content; mobile drawer works correctly).

### E. Regression and diagnostics

- Start with targeted test suites; add tests for discovered defects **before** or alongside their fixes, especially `method: undefined`, modal save/cancel, protected endpoints, approved-questionnaire submission, prediction guardrails, and import persistence.
- Run full `docker compose exec app php artisan test`, `docker compose exec app npm run build`, and compile Blade views if appropriate. Record exact executed counts and commands.
- Browser test at least one happy path and one failure/validation path per role, using real interactive clicks (e.g. Playwright/browser devtools if available). If browser automation is unavailable, run appropriate HTTP/integration tests and list exact unverified manual steps—do not claim browser verification.
- Record screenshots/console/network evidence for important defects and fix verification when practical. Do not include sensitive values, cookies, private test responses, or secrets in reports.
- After tests, ensure Docker/app is functional, verify Git diff and no secrets/data files staged, and confirm preserved DB record counts/relations and `APP_KEY` unchanged.

## Fix policy

- Fix only confirmed bugs in M32, with small, reviewable changes and focused regression tests. Do not redesign UI, rewrite the scoring rules, modify approved questions, retrain models, change model risk thresholds, create FastAPI, migrate PostgreSQL, or deploy.
- If a defect would require substantive product/research decisions or a destructive migration, **document it as a blocker and stop that fix**, rather than guessing.

## Required outputs

Create `src/docs/M32-DEFENSE-VERIFICATION.md` containing:

1. Environment, Git baseline, backup path and data-preservation checks.
2. A concise role-by-role test matrix: workflow, expected result, actual result, PASS/FAIL/NOT TESTED, and test method (browser or automated test).
3. Bugs found, reproduction steps, root causes, changes, regression tests, and remaining issues.
4. Current known demo limitations: application uses `placeholder-heuristic-v0`; M25/M27 ML models remain research artifacts, not UCC-integrated; current questionnaire panel-approved rather than psychometrically validated.
5. Exact full-suite/build results, browser verification evidence, final Git status, commit hash, and a short, safe recommended defense demo sequence.

## Done when

- No reproducible unhandled exceptions, `undefined` Livewire method calls, or silent no-op saves remain in verified key workflows.
- Authorization restrictions remain enforced; confirmation/cancel and data persistence work.
- Current approved questionnaire can be completed; optional-data prediction eligibility behaves as specified; snapshots/history are not rewritten.
- Full Laravel tests and frontend build pass; preserved database validated against its backup and no sensitive/unauthorized files committed.
- Commit as `M32: Verify defense workflows and fix confirmed regressions`. **Stop after M32**, leaving unrelated work for a later milestone.
