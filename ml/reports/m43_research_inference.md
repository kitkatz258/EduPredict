# M43 isolated research inference

**Status:** research-only local loader. Not production authorization.  
**Date:** 2026-11-02  
**Branch:** `feature/m43-research-inference`  
**Base:** M42.1 `9f9a160` is in ancestry.

M42 joblib files can be loaded and scored with source-native columns through a **separate** Python module and CLI. Student predictions stay on the heuristic. M33 FastAPI is unchanged. No FastAPI router was added.

## Architecture

```
Python test / CLI  -->  src.m43.registry.M42ResearchRegistry
                       (allowlist + SHA-256 + metadata contract)
                   -->  src.m43.validation  (source schema, UCC key refusal)
                   -->  saved pipeline.predict / predict_proba
```

This path does **not** use `api.registry.Registry`, `ML_MODELS_DIR`, `HttpResearchInferenceClient`, `FeatureBuilder`, or `PREDICTOR_DRIVER`.

Preferred surface from the spec: local/offline module + CLI. Optional `/v1/research/m42/` router was **not** added, so existing M33 endpoints cannot change.

Usage from `ml/`:

```bash
export OMP_NUM_THREADS=1 OPENBLAS_NUM_THREADS=1 MKL_NUM_THREADS=1 NUMEXPR_NUM_THREADS=1
export PYTHONPATH=.
.venv/bin/python3 -m src.m43 list
.venv/bin/python3 -m src.m43 checksums
.venv/bin/python3 -m src.m43 score --model employability_cp_reduced_m42 \
  --features '{"CompetenceMean": 3.5, "PsyCapMean": 4.0}'
.venv/bin/python3 -m src.m43 score --model uci_first_sem_units_m42 \
  --features '{"Curricular units 1st sem (enrolled)": 6, "Curricular units 1st sem (approved)": 5}'
```

Those JSON objects are **labeled synthetic source-schema examples**. Accepting the keys does not prove the numbers came from Pianda or UCI.

## Artifact allowlist

Directory: `ml/artifacts/m42/` only. Client paths, `ML_MODELS_DIR`, and M25/M27 filenames are rejected. Hashes below match the M42.1 audit and were unchanged after scoring.

| model_id | Role | Timing | Task | SHA-256 | Bytes |
| --- | --- | --- | --- | --- | --- |
| `employability_cp_reduced_m42` | deployment-feasible source experiment | same-occasion survey | 1–5 Pianda EVS-mean regression | `2b210d72cb271faa15539a229b30d5d0238b570878fcda4abdf11abf76c147be` | 3510 |
| `employability_cp_internship_expanded_m42` | source-only exploratory (`comparison_only`) | same-occasion survey | regression + `InternshipMean` | `fa5f30191fe7bdbee7fe1e1db9429310010641aea27eb900c52588a95403df38` | 3603 |
| `uci_first_sem_units_m42` | deployment-feasible source experiment | `after_first_semester` | three-class UCI | `653c3a8ba1baed778ba383d8933d4bda057d897c775d4b1ca8060fc71769cf61` | 5740 |
| `uci_two_sem_units_m42` | source-only exploratory | `after_second_semester` | three-class UCI | `8566d0db02ef5337da1eb33a30d52f9f592f1137f72e3c02f831274a7a4ac2f5` | 4116911 |
| `uci_scholarship_only_m42` | source-only exploratory | `after_published_profile` | three-class UCI | `6e5e2fd7e2ac9b601fca2c827d27d00f4503b599c09d494968c31b8869535595` | 4489 |

All five: `production_validated=false`, `ucc_inference=blocked`, `deployment_candidate=false`, `risk_thresholds=null`.

**Absent optional fields (not invented):** M42 dropout artifacts have no `feature_set`, `role`, `contract_id`, or `model_version`. Employability artifacts have no `model_version`. The loader reports those absences.

M33 artifacts under `ml/models/` were not rewritten. Rechecked SHA-256:

| File | SHA-256 |
| --- | --- |
| `employability_candidate_v1.joblib` | `e8e30ce6ccc5785abc96b6bb896533a1fefcc31cdd4818558908a6d9370fe705` |
| `dropout_candidate_v1.joblib` | `24a9505b241fa6587a44c2a572f64470aee00f3d6dd2cecd0168fe940250c95f` |
| `dropout_binary_full_exploratory_m27.joblib` | `cc897a5876ab08b2cdc69a7ed3f624e71ee9c004aea6b5e3d3c7b54adf27d46e` |
| `dropout_binary_after_first_semester_m27.joblib` | `e09cff1a5829f3eeb992ebaacc985dd5da2daa75239a077dcb169e352fea3545` |
| `dropout_binary_at_enrollment_m27.joblib` | `b07197532e97634e2b181d38d6a4c31a59d185e828dd3252a3c313765d93f835` |
| `dropout_binary_scholarship_baseline_m27.joblib` | `1d3b26619864d58f7753dda418e7983899d301a167edf9b9bf6c1b4ceb820dd6` |

## Input / output contracts

Inputs are the artifact `input_columns` only. Extra, missing, null, NaN, Inf, bool, and string values are rejected. UCI counts use audited ranges in `ml/api/uci_source_support.json` (not invented). Likert means stay in [1, 5].

Rejected: UCC item codes `A1..E10`, GWA and snapshot keys, student number/email/name, `scholarship_status` / `has_scholarship`, UCI grade columns, M33 wrapper `schema_id` on the source object, client paths.

Successful output always sets `research_only=true`, `ucc_inference=blocked`, `suitable_for_student_advice=false`, `student_workflow=false`, `risk_level=null`, and `input_provenance=unverified`.

- Regression: native 1–5 mean plus optional display index `((score-1)/4)*100`. Not hiring probability.
- Classification: source labels `Dropout` / `Enrolled` / `Graduate` aligned from `classes_`, not column position. Probabilities are labeled **source-model uncalibrated scores**. Not converted to M33 binary Dropout/Not Dropout.

Classifier `classes_` on all three UCI artifacts: `['Dropout', 'Enrolled', 'Graduate']`.

## Demo results (labeled synthetic source-schema rows)

Pianda C/P `CompetenceMean=3.5`, `PsyCapMean=4.0`:

- `predicted_employability_mean` = 3.7434540296823573
- `display_readiness_index` = 68.58635074205893

UCI first-sem `enrolled=6`, `approved=5` (source medians, not UCC units):

- `predicted_class` = `Enrolled`
- uncalibrated probabilities: Dropout 0.228151, Enrolled 0.420697, Graduate 0.351151

First 8 Pianda predictor rows from the local workbook reloaded to the M42.1 audit vector:

`[3.640902, 3.819946, 3.666118, 3.948558, 4.07548, 4.668954, 4.43948, 3.459325]`

First 8 UCI first-sem source rows predicted `Enrolled, Graduate, Dropout, Graduate, Enrolled, Graduate, Graduate, Dropout`.

A payload with `C1` is rejected (`ucc_input_rejected`). That rejection does **not** prove remaining numeric values are Pianda measurements.

## Security / research limitations

- Allowlist only; no path traversal; checksum mismatch is a hard failure; no fallback to heuristic/M25/M27.
- Features are not logged.
- All candidates remain UCC-blocked.
- Pianda target is a same-occasion EVS survey mean (Aceh vocational secondary students), not a UCC job outcome.
- UCI classes are Portuguese administrative labels, not UCC dropout risk bands.
- UCC C/P means are not Pianda `CompetenceMean` / `PsyCapMean`.
- UCI enrolled/approved are course **counts**, not `SUM(subject_grades.units)`.
- Missing scholarship is not 0.

## Files changed (implementation)

Added:

- `ml/src/m43/` (`catalog`, `registry`, `validation`, `inference`, CLI)
- `ml/tests/test_m43_inference.py`
- `ml/reports/m43_research_inference.md`
- `ml/reports/ucc_term_identity_decision_memo.md`
- `ml/reports/ucc_scholarship_semantics_decision_memo.md`

Updated: `ml/README.md`, `src/docs/DECISIONS.md`, `src/docs/KNOWN_GAPS.md`. Spec file `src/cursor/17-M43-research-only-inference.md` is tracked with this work.

**Not changed:** `ml/api/*`, `ml/models/*`, `ml/artifacts/m42/*.joblib`, Laravel `FeatureBuilder`, predictors, routes, migrations, questionnaire, Render/Supabase, `PREDICTOR_DRIVER`.

## Test evidence

Command (from `ml/`):

```bash
PYTHONPATH=. .venv/bin/python3 -m pytest tests/test_m43_inference.py tests/test_m42_scorer.py \
  tests/test_m42_mapping.py tests/test_m42_compatibility.py tests/test_m42_experiments.py \
  tests/test_m33_api.py tests/test_m25.py tests/test_m27.py -q
```

Result on 2026-11-02: **86 passed**, 1 FastAPI/Starlette deprecation warning, 36.34s. No failures.

PHPUnit, `migrate:fresh`, MariaDB, Supabase, Render, and `src.run_m42` were not run. Passing unit tests are not UCC validation.

## Residual blockers

1. Panel CVI / CLAS-like reliability / construct equivalence for UCC C/P vs Pianda.
2. Degree-term identity and course-count vs credit-unit sum (`ucc_term_identity_decision_memo.md`).
3. Scholarship collapse and unknown/missing refusal (`ucc_scholarship_semantics_decision_memo.md`).
4. No UCC employment or dropout outcome labels in the repository.
5. No local calibration or Low/Moderate/High cuts.
6. M33 remains binary Dropout/Not Dropout on M25/M27 files; it still cannot load M42 artifacts.

Nothing here authorizes scoring a UCC student with these candidates.
