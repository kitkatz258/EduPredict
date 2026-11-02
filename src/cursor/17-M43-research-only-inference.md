# EduPredict M43 — Isolated Research Inference and Compatibility Contracts

**Status:** implementation specification; not production authorization  
**Expected branch:** `feature/m43-research-inference`  
**Base:** local M42.1 commit `9f9a160` on `feature/m42-ml-research`  
**Purpose:** demonstrate that M42 source-trained artifacts can be loaded and evaluated safely through a separate **research-only** interface, while keeping all EduPredict student predictions on the existing heuristic.

## 1. Read these first

Read the real repository before editing. In particular:

- `ml/reports/m42_1_readiness_audit.md` (the source of truth for known blockers).
- All M42 model cards and reports, notably `deployment_feature_compatibility.md`, `questionnaire_feature_mapping.md`, `m42_metrics.json`, and `research_limitations.md`.
- The artifacts under `ml/artifacts/m42/` and their true metadata, feature names, predictions, classes, hashes, and file formats.
- Existing M33 files (`ml/api/registry.py`, `schemas.py`, `inference.py`, `settings.py`, `support.py`, `boundaries.py`), relevant API tests, and `src/docs/M33-RESEARCH-INFERENCE.md`.
- The source datasets, authentic codebooks, and the updated 60-item research-basis PDF where locally available.
- Current Git status, active branch, uncommitted changes, and last commit.

Do **not** assume an API schema or metadata key from this spec if the actual code differs. Derive and document it from the checked-in artifact and source code.

## 2. Preflight and Git safeguards

1. **Stop without editing** unless the branch is exactly `feature/m43-research-inference`. The user creates/switches branches manually; the agent must not switch or create branches.
2. Verify M42.1 is present in ancestry (expected `9f9a160`) and inspect `git status -sb` and recent history. If unrelated uncommitted edits exist, do not overwrite them; report the conflict and stop.
3. Never push, merge, deploy, rebase, reset, or manipulate remote branches. Do not touch the protected `main` or `feature/render-deploy` branches.
4. Use local/offline test resources. Do not connect to MariaDB, Supabase, Render, or real student records. Do not reset or migrate a database.
5. Local commit is permitted **only after** files, test evidence, and diff are reviewed; do not push.

## 3. Non-negotiable scientific boundaries

- All M42 candidates are **source-trained research models**. They have **not** been validated with UCC students.
- M42 Pianda regression estimates a 1–5 **self-perceived employability survey construct**, not probability of a job offer.
- M42 UCI classification returns `Dropout`, `Enrolled`, `Graduate` **source-dataset classes**, not UCC dropout outcomes or validated Low/Moderate/High student risk.
- Never use UCC 60-item C/P means as Pianda `CompetenceMean`/`PsyCapMean` inputs. Zero verbatim item matches; the constructs are not equivalent by default.
- Never use questionnaire A/S/B as UCI predictors, or E as a Pianda predictor.
- Never convert Philippine GWA into Portuguese 0–20 grades without validated meaning and scale.
- Never infer UCI term identity from the first uploaded report. Semester name alone is insufficient. Avoid future-term leakage.
- Do not convert a missing scholarship status to `0` or `false`.
- Do not fabricate UCC training labels, predicted probabilities, calibration, or risk bands.
- Do not claim that rejecting UCC-named keys proves the origin of arbitrary numeric inputs. The research API is not a mechanism for proving data provenance.

## 4. Implementation scope — isolated M42 research interface

**Goal:** load M42 joblib files without changing or silently replacing the M33 model registry and contracts.

### 4.1 Loader/registry

- Add a **new, separate, explicitly named M42 research loader/registry**; do not rename, overwrite, or repoint the M33 registry or `ML_MODELS_DIR`.
- Use an explicit allowlist of existing M42 artifact files and model identifiers. Do not accept a client-supplied path, filename, arbitrary model id, or directory traversal.
- Validate metadata/version, expected feature schema, model type, class ordering, and intended prediction timing before serving the model. Use actual artifact metadata; document absent optional fields instead of inventing them.
- Preserve the original artifacts byte for byte. Record checksums before and after the work.
- Make loader failures visible and controlled. Do not quietly fall back to heuristic or M25/M27 models.
- Distinguish deployment-feasible source experiments (e.g., Pianda C/P; UCI first-semester units) from source-only exploratory ones (e.g., internship-expanded; second-semester). All remain **UCC-blocked**.

### 4.2 Execution surface

Choose the smallest viable surface supported by existing M33 code:

- Preferred: a **separate local/offline research module** callable from Python tests and a small reproducible CLI.
- Optional: a distinct FastAPI research router with a prefix such as `/v1/research/m42/`, **only if** it can be gated by a new opt-in flag that defaults to **off** and reuses M33 authentication. Do not change existing M33 endpoint requests, responses, registry behavior, or production startup.
- Do not add any Laravel route, controller, `HttpResearchInferenceClient` method, student UI, admin UI, or production container/deployment setting.
- If a local API router is added, test it in-process (FastAPI `TestClient` or existing equivalent). Do not expose a public listener or deploy it.

### 4.3 Validation and output contracts

- Exact input keys, types, ranges, and required/extra field behavior come from each artifact's schema and source dataset. Reject missing, extraneous, null, NaN, infinite, incorrectly typed, and incompatible values; check plausible count ranges **only if sourced**, not invented.
- Do not accept questionnaire item keys `A1..E10`, section means with UCC labels, UCC GWA, student numbers, emails, or names. Explicitly test these rejections.
- Successful research output identifies artifact id/version, source dataset, task, source-native class names or original 1–5 target scale, prediction timing, and limitations.
- For three-class classifiers, confirm actual `classes_` order and align any probability array to `Dropout`/`Enrolled`/`Graduate` by labels. Never assume column position or convert to M33 binary responses.
- If exposing class probabilities, call them **source-model uncalibrated scores/probabilities**, not validated UCC dropout probabilities. Do not generate Low/Moderate/High risk labels.
- For regression, return native scale; optionally return a documented 0–100 **display index** only if the source target coding (1–5) is established. Never call it hiring probability.
- Do not log raw feature payloads or personally identifying data. Examples should use labeled synthetic examples or verified source samples, never UCC rows.

## 5. Two documentation-only research decision memos

Write short, concrete documents **without implementing student features**:

**A. UCC term identity / academic progress**
- Identify why `grade_reports.school_year`, `semester`, `students.year_level`, and `enrollment_year` fail to establish the first/second degree terms.
- Distinguish UCI **counts of curricular units/courses** from Philippine **credit-unit weights**. Verify the exact UCI meaning from the source documentation; do **not** silently assume `SUM(subject_grades.units)` equals UCI enrolled or approved units.
- List testable proposed rules and unresolved decisions for repeats, transfers, credit recognition, NSTP, Midyear/Summer, INC/DRP/UD, superseded grade reports, program shifts, and when a term becomes complete.
- Explicitly state that evaluated/credited/without-evaluation UCI features remain unavailable. No arbitrary 0–20 grade conversion.
- Recommend a source-backed data collection or degree-term key design only as a proposal; make no migration and no `FeatureBuilder` change.

**B. Scholarship semantics**
- Inventory actual locally modeled statuses and the difference between `unknown`, `draft`, `none`, and verified awarded scholarship.
- Define what source evidence and owner approval would be required to collapse any status into the UCI binary `Scholarship holder` variable. Propose explicit rejection of unknowns.
- No changes to student snapshots, current feature builder, or profile forms.

Explicitly label open policy choices as **pending human confirmation**, not agent-approved facts.

## 6. Required verification

1. Run relevant existing M42 and M33 tests before and after changes. If test dependencies are missing, report the exact blocker; do not claim a pass.
2. Unit tests: artifact allowlist; artifact versions/checksums; schema rejection; strict UCC-key refusal; no predictor fallback; unsupported model/role refusal; absent data rejection; nonnumeric/NaN/inf handling.
3. Integration tests (isolated): load at least the Pianda C/P and UCI 1st-semester M42 candidates; verify deterministic predictions from source-native fixture rows; verify three-class label order; source-only 2nd-semester timing; identify source-only exploratory models.
4. If an API router exists: authentication, flag-default-off, disabled route behavior, no M33 route regression, no binary/three-class conflation, local TestClient only.
5. Assert that M33 existing model artifacts, existing routes/contracts, Laravel predictor driver, current questionnaire/historical PDFs, and database configurations remain unchanged.
6. Capture exact commands and real pass/fail results. A passing unit test does not equal UCC validation.
7. Do not regenerate M42 experiments or overwrite `.joblib` files just to make tests pass.

## 7. Deliverables

Use names/paths consistent with the actual repository; suggested:

- New research-only loader / CLI module and tests in `ml/`, with no Laravel changes.
- Optional feature-flagged M42-only FastAPI router, **only** if justified and safe.
- `ml/reports/m43_research_inference.md`: architecture, explicit artifact allowlist, input/output schemas, security and research limitations, offline usage examples, and test evidence.
- `ml/reports/ucc_term_identity_decision_memo.md`: proposed term/count semantics plus blocked fields.
- `ml/reports/ucc_scholarship_semantics_decision_memo.md`: proposed binary rule plus unknown handling.
- Update `ml/README.md` or existing relevant documentation as needed, without claiming production readiness.

## 8. Acceptance criteria / stop conditions

M43 is done **only if** all of the following are established with evidence:

- M42 candidates can be invoked reproducibly with source-native input in an **isolated** research context.
- M33's original contracts and model registry still behave exactly as before.
- Input validation is strict and cannot silently coerce UCC keys into source inputs.
- No student, Laravel, Render, Supabase, production, or database pathway changed.
- The academic term and scholarship memos clearly distinguish possible data engineering from unverified research equivalence.
- The final report includes file diff, exact tests, hashes, model identity, demo commands, residual blockers, and commit id if committed.

**STOP AND REPORT instead of implementing risky changes** if achieving this requires: switching `PREDICTOR_DRIVER`; using UCC data; changing M33 binary requests/responses; rewriting original model artifacts; adding live student endpoints; a new DB migration; inventing source feature meanings; or selecting a model by viewing holdout results.

## 9. Expected report to the user

Explain in simple language:

1. What research inference can now demonstrate (and with which source-native test samples).
2. Why this is **not yet** ML inference on UCC students.
3. What exact Laravel/M33 files were or were not touched.
4. Actual tests and any failing tests, git diff and local commit.
5. What needs panel review, local piloting, and real UCC outcomes before considering live predictions.

Do not push, merge, deploy, or describe M43 as production-ready.
