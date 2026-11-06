# EduPredict — M42: ML Research, Feature Mapping, and Candidate Training

**Status:** Implementation specification; research only  
**Target branch:** `feature/m42-ml-research`  
**Project:** UCC EduPredict (Laravel 12 + existing Python/scikit-learn research workspace and M33 FastAPI service)  
**Source of truth:** latest **60-item questionnaire** (`SystemQuestions.pdf`), `EduPredict_Scoring_and_Research_Basis_Updated.pdf` (both files in /src/cursor/reference/approved-questionnaire/), original **Pianda Mendeley v4** data/codebook, and original **UCI Predict Students' Dropout and Academic Success** data/documentation.  
**Working directory:** `~/projects/EduPredict/src` (verify; never assume paths)

## 0. Mission and hard safety boundaries

Implement an **auditable research-only M42** pipeline to determine whether source-trained models can accept legitimate EduPredict inputs. This is **not** a production ML launch, and a favorable external holdout score **does not** establish validity for UCC students.

**Do not** push, merge, deploy, switch branches automatically, reset databases, run destructive migrations, edit production environment variables, or modify unrelated files. Never change the active Laravel predictor, `PREDICTOR_DRIVER`, live student pages, prediction labels/history, deployed Render/Supabase systems, questionnaires or seed data. Preserve the existing M25–M27 experiments, trained artifacts, M33 FastAPI code/endpoints and unrelated changes (including `README.md`). Do not create another FastAPI service. Do not silently overwrite existing artifacts or reports.

**Gate:** Run `git status -sb`, `git branch --show-current`, `git log -3 --oneline`. **Require** current branch exactly `feature/m42-ml-research`; otherwise STOP and report the state; do not switch/create the branch. If unexpected working-tree changes exist, document them and preserve them; do not stash, reset, clean, or overwrite. Research work must not use or mutate the shared production database. If running tests requires containers, inspect their configuration first and use isolated/test-only resources; a Git branch does **not** isolate Docker volumes or databases.

## 1. Source inventory before coding

Locate and inspect actual files in the repository rather than assuming filenames:

1. Exact 60-item questionnaire (original DOCX/PDF or v2 seed/schema) and version `ucc-panel-revised-60-v2` (`A1–A10`, `S1–S10`, `B1–B10`, `C1–C10`, `P1–P10`, `E1–E10`). Preserve original 36-item version `ucc-panel-approved-v1` and its historic responses.
2. Updated scoring/research PDF (consultation draft); use the latest version, not the previous 36-item document.
3. Pianda et al. Mendeley Data v4 (DOI `10.17632/hmn3b4c6c4.4`) source files, raw questionnaires, codebooks, value labels and missing/reverse-coding instructions. The approximate 266-record dataset and any exact column counts must be confirmed from the actual files.
4. UCI dataset `Predict Students' Dropout and Academic Success` (DOI `10.24432/C5MC89`), documentation and native features; approximately 4,424 rows, three target labels `Dropout`, `Enrolled`, `Graduate`.
5. Existing ML scripts, requirements, environment, generated reports, M25–M27 research models/artifacts, M33 FastAPI research endpoints/adapters, and tests. **Audit first; extend rather than duplicate or overwrite.**

If a source document, raw data, variable dictionary, original target-scoring definition, or crucial transformation is missing, record a precise blocker. Do **not** synthesize fake source observations, codebook entries, questionnaire statements, conversion tables, coefficients, or experimental metrics. Do only defensible subsets of the work and report what is blocked. Keep raw datasets unchanged. Respect license/attribution restrictions; don't commit restricted or sensitive raw records.

## 2. Questionnaire scoring contract (60-item v2)

- Five-point options: `1=Strongly Disagree`, `2=Disagree`, `3=Neutral`, `4=Agree`, `5=Strongly Agree`.
- Six independent construct means: `mean(A1..A10)`, `mean(S1..S10)`, `mean(B1..B10)`, `mean(C1..C10)`, `mean(P1..P10)`, `mean(E1..E10)`.
- Require **all ten valid integer responses (1–5)** for a completed section. Missing answers must not be imputed as neutral. Explicitly represent incomplete/invalid sections and test this behavior.
- Proposed v2 items are positively worded; **no UCC reverse scoring** unless the exact questionnaire contradicts that and the issue is flagged. Audit source-dataset reverse coding separately.
- Never manufacture an all-60 total or use section means as automatically equivalent to foreign source features.
- Descriptive, **non-ML** interpretation on unrounded mean values: `<=1.80 Very Low`; `>1.80–2.60 Low`; `>2.60–3.40 Moderate`; `>3.40–4.20 High`; `>4.20–5.00 Very High`. Format only after classification. Optional index for a verified native 1–5 scale: `((score - 1)/4)*100`.
- An **A/S/B** mean `<=2.60` may be marked as a **provisional questionnaire-context support concern**. Higher B means mean higher engagement. This rule is **not** a trained dropout prediction or calibrated probability threshold and must **not** change classifier output.
- `E` is a separate self-assessment, useful as a descriptive comparison but **never** a predictor of the Pianda employability outcome.
- All v2 instrument psychometrics, content validity and applicability to UCC remain **pending**; never label panel-revised as reapproved without evidence.

Build a small isolated scorer + unit tests without modifying the live Laravel scoring or production endpoints. Include tests at cutoffs, missing/duplicate/out-of-range/non-integer item responses, section boundaries, positive orientation, and no aggregate total.

## 3. All-60 item mapping, provenance and leakage audit

Produce `ml/reports/questionnaire_feature_mapping.md` (or consistent audited existing report path) with **each exact item code and verbatim actual statement** from the current instrument, its UCC construct, any source variable/construct candidate, matching status (`exact`, `conceptual`, `none`), reason, transformation, source citation/file+column, model-use status (`yes`, `no`, `pending`), missingness/availability and leakage concerns. State clearly that matching labels or five-point scales **do not** prove construct equivalence.

At a minimum follow this provisional role map until proven by documents:

| Group | Allowed research role | Explicit restriction |
|---|---|---|
| A | Academic self-perception/context | Not a native UCI academic feature |
| S | Resource/financial context and support | Not automatically Portuguese scholarship/debt/tuition |
| B | Student engagement and recommendations | Not a UCI feature; cannot affect dropout probability |
| C | Candidate Pianda *competence* input | Verify source item meaning and scoring; do not assume exact match |
| P | Candidate Pianda *psychological capital* input | UCC has 10 items; source instrument may have more; validate mapping |
| E | Separate UCC self-perceived employability score | **Exclude from employability predictor** to prevent circularity/leakage |

Also map non-questionnaire UCC fields (grades, subject/course units, enrollment information, skills, internship/experience) against foreign dataset definitions. Record **when** each field is available (at enrollment, first semester, second semester, prediction request) to prevent hindsight/temporal leakage.

## 4. Pianda employability regression — source-native first

- Inspect original dataset/codebook and calculate original source competence, psychological capital and employability **target** only according to documented source rules (including any reverse codes). Document whether target truly has range 1–5.
- Produce a **source-native reduced C/P-only** experiment (one or both constructs only if defensibly calculated). Internship experience may be analyzed as a clearly labeled **expanded, non-deployment-compatible comparison**, not as required deployment input if EduPredict lacks an equivalent measurement.
- **Never** use target/employability items (including UCC E items) in predictors, nor rederive predictors from labels. Split before learned preprocessing; use scikit-learn pipelines for training-only fit, missing-value policies justified by the source, and deterministic seeds.
- Compare `DummyRegressor`, `LinearRegression`, `Ridge`, `RandomForestRegressor`, `GradientBoostingRegressor` where dataset size and requirements permit. Use a fixed holdout plus suitable repeated cross-validation on training data; do not tune against the holdout.
- Report holdout **MAE, RMSE, R²**, CV mean/spread, sample/feature counts, missing data and train-vs-test gap. Do **not** call R² an accuracy percentage. If target scale was verified 1–5, document optional readiness index `((prediction-1)/4)*100`, clamping/display policy separately, and note that it is **not** probability of receiving employment.
- Evaluate an optional UCC-score inference probe through strict feature-schema validation: if source-vs-UCC C/P scale equivalence lacks evidence, **reject deployment inference**, and use only obviously synthetic **test-only** fixtures for code-path tests, explicitly labeled as such. Do not claim UCC validity.

## 5. UCI dropout classification — deployment-feasible research subset

- Preserve UCI three-class target (`Dropout`, `Enrolled`, `Graduate`), not a fabricated Low/Moderate/High outcome label.
- Audit all source fields, value encodings, class balance, missingness, and predictors unavailable in EduPredict; check availability at **prediction time**. No leakage via final-semester records when making first-semester predictions.
- Separate **at-enrollment**, **first-semester**, and **second-semester** candidate feature availability where supported. Focus on real semester enrollment/approved/evaluated unit counts and other meaningfully matching, actually collected UCC fields.
- UCC grades use `1.00 best / 5.00 fail`, while Portuguese UCI average grades use `0–20 higher better`. Do **not** pass UCC GPA/GWA directly to the UCI grade field or invent a conversion. **Exclude average-grade fields and retrain** if no defensible conversion is documented. Course-title matching isn't necessary for compatible unit aggregates, but unit definitions must be verified.
- Do not substitute A/S/B perception means for UCI grades, support features, or behavioral predictors. Do not insert made-up missing student data to satisfy the schema.
- Compare `DummyClassifier`, multinomial `LogisticRegression` (current API equivalent), `RandomForestClassifier`, and `GradientBoostingClassifier` as appropriate. Use stratified holdout, stratified CV on training data, pipelines and fixed seeds. Report class counts, accuracy, macro/weighted F1, class-specific precision/recall/F1, dropout recall, confusion matrix, and defensible probability-calibration/AUC metrics where applicable.
- If outputting model `P(Dropout)`, label it **source-trained, not UCC-calibrated**. Do **not** invent Low/Moderate/High thresholds (especially no 1/3 cutoffs). If the deployment-compatible subset is empty or uncertain, document and block integration rather than invent features.

## 6. Research model artifacts, tests and documentation

Reuse the **existing** Python environment, conventions, M25–M27 scripts and M33 FastAPI artifact architecture where feasible. **No live endpoint change** in M42. Keep candidate artifacts distinct and **versioned**, e.g. under a new `ml/artifacts/m42/` path, never replacing M33 files; apply repository Git/LFS/artifact policy and avoid committing unexpectedly large binaries. Store metadata (or model cards) with dataset version/hash, source codebook, rows/features, target and preprocessing, train/holdout/CV protocol, random seed, metrics, feature order, schema, limits and dependencies.

Required output or explicit, specific blocker for each:

1. `ml/reports/data_audit.md` — dataset provenance, licensing, native columns/codings, completeness, available files, target and leakage checks.
2. `ml/reports/questionnaire_feature_mapping.md` — all exact 60 item statements and per-item mapping, plus separate academic/grade feature map.
3. Tested Python section scorer for all six 10-item means and exact descriptive categories.
4. `ml/reports/employability_experiments.md` — baseline and reduced-model comparisons, **actual numerical** held-out metrics.
5. `ml/reports/dropout_experiments.md` — 3-class baseline/reduced-model comparisons, class-wise **actual numerical** metrics and timing constraints.
6. `ml/reports/model_comparison.md` — honest comparison per task; no comparing regression metrics to classification metrics.
7. `ml/reports/deployment_feature_compatibility.md` — precise candidate model input schema, UCC fields, transformations, availability, `allowed/blocked/pending` status, and UCC-style test fixture rejection cases.
8. `ml/reports/research_limitations.md` — external population gap, survey validation, transferability, calibration and missing UCC outcome-label blockers.
9. Isolated candidate `.joblib` artifacts + model cards **only for scientifically defensible fitted candidates**.
10. A small reproducible CLI/README section with exact research commands, tested environment and outcomes; add or extend Python unit/integration tests without requiring production services.

If existing repository paths differ, preserve conventions and document exact actual locations. Run tests on research-only modules and existing applicable tests; never claim a pass without displaying the executed command, exit status and actual counts. Do not run full app/PG tests against production or shared state. No synthetic dataset may be presented as empirical model performance.

## 7. Completion report and human review gate

Before finalizing, summarize in plain English:

- Exact branch and commit SHA, source inventory and changed files.
- Questionnaire version/60 statements and scoring tests.
- Source dataset versions, sample counts, field schemas and leakage checks.
- Every evaluated algorithm and its **actual** holdout/CV scores, clearly separated by task; say `not run` for any missing results.
- Whether the Pianda C/P construct mapping is validated (probably `pending`), and whether UCI UCC feature and semester/grade compatibility is validated (probably `pending`).
- What cannot safely be fed into the trained models and what UCC-side evidence is still needed.
- Actual test commands, pass/fail counts, and other verification evidence.
- What M43 could integrate **only after approval and local validation**, with no driver switch or deployment now.

A research candidate that works on Pianda/UCI data must **never** be described as validated on UCC data. Any compatibility issue remains a documented gate, not something to bypass.

**Git:** After completing tests and reporting changes, a **local M42-only commit is permitted**, but only after confirming no unrelated edits would be included; give the hash. **Do not push, merge, deploy or switch branches.** If files were already dirty, leave those user changes untouched and explain how to isolate the commit safely.
