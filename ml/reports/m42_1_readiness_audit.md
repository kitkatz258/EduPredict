# M42.1 research-readiness audit

**Later correction (D1, 2026-11-06):** section 1 below says a sum of `subject_grades.units` is the derivable enrolled feature. That sum is a credit-unit total, not the UCI integer course count. The feasibility decision is `d1_academic_feature_definitions.md` and `ucc_feature_and_target_contract_v1.md`. This file remains the 2026-10-30 audit.

**Date:** 2026-10-30  
**Branch:** `feature/m42-ml-research`  
**HEAD at audit start:** `dfbd1d56fb4b46e13c2b91a1690a3effe00e95a6` (`M42: Map the 60-item instrument and train source-native research candidates without changing live prediction.`)  
**Working tree at start:** clean  

This audit does not train new models, does not call live FastAPI, does not switch `PREDICTOR_DRIVER`, and does not read student rows from MariaDB or Supabase. Findings are from repository files, restored git blobs, and isolated Python checks.

A favorable Pianda or UCI holdout score is **not** UCC validity.

## 0. What was inspected

M42 reports: `data_audit.md`, `questionnaire_feature_mapping.md`, `employability_experiments.md`, `dropout_experiments.md`, `model_comparison.md`, `deployment_feature_compatibility.md`, `research_limitations.md`, `m42_metrics.json`.

M42 artifacts and model cards under `ml/artifacts/m42/`. M25/M27 artifacts under `ml/models/` were loaded read-only and not overwritten.

Laravel: `grade_reports` / `subject_grades` migrations and models, `GwaCalculator`, `GradeScale`, `AcademicSummary`, `GradeReportForm`, `FeatureBuilder`, `FeatureSet`, `ApprovedInstrumentCatalog`, `RevisedInstrumentCatalog`, `ml-feature-contract.md`.

M33: `ml/api/registry.py`, `schemas.py`, `inference.py`, `settings.py`, `support.py`, `boundaries.py`, `src/docs/M33-RESEARCH-INFERENCE.md`, `HttpResearchInferenceClient`.

Historic PDFs restored from `b27211b` (see section 4). SHA-256 matches the M25 mapping review.

## 1. Can real UCC records supply UCI first-semester unit counts?

**Short answer: not as valid UCI inputs today.** Subject credit units exist. First-semester identity, evaluation counts, and Portuguese “approved” meaning do not.

### What EduPredict actually stores

`grade_reports` (`2026_10_05_100005` + versioning in `2026_10_09_170000`):

- `school_year` string (UI offers eight recent `YYYY-YYYY` values)
- `semester` string
- `status`, `version`, `supersedes_id`, `superseded_at`
- Current grades = confirmed and not superseded (`GradeReport::scopeCurrent`)

`subject_grades` (`2026_10_05_100006` + `is_incomplete`):

- `subject_code`, `subject_name`
- **`units` decimal(4,2)** — credit units exist per subject row
- `midterm_grade`, `final_exam_grade` as **tokens**, not evaluation counts
- `final_grade`, `remarks`, `is_failed`, `is_incomplete`, `is_major_subject`

UI semester choices (`GradeReportForm`): `First`, `Second`, `Midyear`. The paste parser can also detect `Summer`. There is no degree-semester index (1st term of the program vs later term). `AcademicSummary` counts distinct `school_year|semester` pairs and orders by `school_year` then the **semester string**, so alphabetical order is First, Midyear, Second, Summer — not a calendar.

`students.year_level` is copied at registration. `enrollment_year` is the registration calendar year. Neither is recomputed from grade reports. `FeatureBuilder` emits cumulative `major_units` / `other_units` from GWA units, not per-semester enrolled/approved counts.

Philippine passing maximum is 3.00; failure is 5.00. INC and dropped rows are left out of GWA. NSTP-prefixed codes are excluded from GWA. Failed NSTP still increments the failure count then is excluded from the average.

UCI (this file): 1st-sem enrolled median 6, approved median 5, evaluations median 8 max 45, credited median 0. Curricular grades 0–20 higher-better. No conversion is defined.

### Field classification

| UCI field | Class | Evidence | Timing |
| --- | --- | --- | --- |
| Curricular units 1st sem (enrolled) | **Derivable only after a written term-identity rule** | Sum of `subject_grades.units` on one confirmed report is possible. “1st sem of the degree” is not stored. One uploaded later term would look like a first term. Midyear/Summer have no UCI counterpart. Repeats, NSTP, and dropped rows need a counting rule that does not yet exist. | After that term is confirmed. Leakage if used at enrollment. |
| Curricular units 1st sem (approved) | **Derivable only after a written approval-definition rule** | Passed rows can be counted with local pass = numeric final ≤ 3.00 and not INC/DRP/UD/5.00. That is **not** a verified Portuguese approval definition. INC is incomplete, not “unapproved.” | After that term. Leakage at enrollment. |
| Curricular units 1st sem (evaluations) | **Unavailable / incompatible** | UCI is a count (median 8, max 45). UCC stores at most two exam **tokens** per subject. Counting filled midterm/final cells would invent 0–2 per subject, not the UCI variable. M42’s first-sem candidate does not use this column. | After that term, and still not the UCI field. |
| Curricular units 1st sem (credited) | **Unavailable** | No recognition-of-prior-learning / transferred-units column. UCI median is 0, which matches credit transfer, not term enrollment. | — |
| Curricular units 1st sem (without evaluations) | **Unavailable** | INC/DRP/UD are local tokens, not this UCI count. | — |
| Curricular units 1st/2nd sem (grade) | **Incompatible** | Philippine GWA is 1.00-best / 5.00-fail. UCI unit grades are 0–20 higher-better. M42 excluded grade columns rather than inventing a conversion. | — |
| 2nd-sem enrolled/approved | Same as 1st-sem, plus **temporal leakage** if used before that semester exists | M42 two-sem model is source-native only. | After second semester |
| Scholarship holder | **Derivable only after verified collapse + missing-data rule** | Profile statuses exist but `FeatureBuilder` currently stores `hasScholarship: false` and `scholarshipStatus: null` on new snapshots (M37.1). Missing ≠ 0. | After a published profile |
| Directly available UCI predictors | **None** | Confirmed against schema, `FeatureBuilder`, and M25 feasibility review. | — |

M42 trained `uci-first-sem-units-m42` on enrolled + approved only (evaluations excluded). That was the defensible source-native subset. It still cannot be fed UCC rows until term identity and approval rules exist **and** are accepted.

Do not fabricate missing unit counts, grade conversions, or an earlier academic history when a student uploads a later term.

## 2. Sixty-item mapping, with C and P against the Pianda codebook

Instrument: `ucc-panel-revised-60-v2` / `SystemQuestions.pdf`. Source English: codebook sheet of `Raw Dataset.xlsx` and `Survey questionnaire.docx`. **Exact verbatim matches: 0.** Conceptual: 19. None: 41. Matching a 1–5 scale does not prove construct equivalence.

A, S, and B remain context. None is a UCI feature. B cannot change dropout probability. E is target-adjacent and is excluded from employability predictors.

### Competence C1–C10 vs Pianda C1–C10

Item **counts** now both equal 10. That is not equivalence. Pianda C is a 21st-century competence list (life/career, learning/innovation, IT/media). UCC C is an employability-skill list.

| UCC | UCC statement | Closest Pianda | Defensible similarity? | Still missing |
| --- | --- | --- | --- | --- |
| C1 | Communicate ideas clearly | C6 (also C2) | Partial: both mention clear communication. Pianda C6 adds collaboration. | Item pairing study; not exact |
| C2 | Work effectively in a team | C6, C2 | Partial theme. No Pianda item is “work effectively in a team” without diversity/collaboration clauses. | Same |
| C3 | Identify problems and solutions | C5, C8 | Weak/partial. Reasoning and information evaluation ≠ problem identification. | Same |
| C4 | Adapt to tools, technologies, changing situations | C1 | Partial on adaptation. Pianda C1 has no tools/technologies and is limited to learning/group activities. | Same |
| C5 | Use digital tools for academic/practical tasks | C8–C10 | Weak. Pianda items are information access and media creation. | Same |
| C6 | Apply school learning to real-world tasks | none in C | **Not defensible as a C-item pair.** SIE/EVS are not competence predictors. | Would need a new construct decision |
| C7 | Organize work and responsibilities | C3 | Partial. Pianda C3 is project execution. | Item pairing study |
| C8 | Learn new skills when required | none (C7 is creative innovation) | **Not defensible** | — |
| C9 | Responsibility and professional behavior | none in C | **Not defensible.** EVS is the target. | — |
| C10 | Accept feedback and use it | none in C (SIE11 is supervisor feedback) | **Not defensible** as a competence predictor | — |

Unmatched Pianda C items include leadership (C4), creative innovation (C7), and media creation (C9–C10).

### Psychological capital P1–P10 vs PsyCap1–PsyCap14

UCC has 10 items; source has 14. PsyCap4 is pandemic-specific and has no UCC pair.

| UCC | Closest Pianda | Defensible similarity? | Note |
| --- | --- | --- | --- |
| P1 | PsyCap12 self-efficacy | **Closest of the set** (difficult tasks). UCC adds studies/future work. Still not verbatim. | Content overlap only |
| P2 | PsyCap1 hope pathways | **Close.** UCC drops the study setting. | Content overlap only |
| P3 | PsyCap10 / PsyCap3 | Weak. PsyCap10 is a belief about other people; PsyCap3 has no setback clause. | — |
| P4 | PsyCap5 optimism | **Close.** UCC names studies/career; does not say “optimistic.” | Content overlap only |
| P5 | PsyCap9 / PsyCap10 | Weak. Recovery from academic/personal difficulty ≠ low-grade problem solving. | — |
| P6 | PsyCap11 | Weak. Growth-through-effort ≠ motivation for high academic achievement. | — |
| P7 | PsyCap1 / PsyCap7 | Weak. Career-goal pathways are not study obstacles or “dreams.” | — |
| P8 | none | **Not defensible.** No vigor/energy item. | — |
| P9 | PsyCap10 | Weak. Calm persistence ≠ general recovery belief. | — |
| P10 | PsyCap6 | Partial outlook theme. | — |

Unmatched source items include PsyCap4 (pandemic), PsyCap13 (ask the teacher), PsyCap14 (contribution to others).

### Empirical evidence still missing (required before calling C/P equivalent)

1. Expert I-CVI and S-CVI/Ave on the 60-item text (scoring PDF §4.1). None observed.
2. CLAS-similar pilot Cronbach alpha **per 10-item section**. None observed.
3. Measurement model (CFA or equivalent) and a test that can **fail** invariance / metric equivalence.
4. Decision that UCC C/P are new scales **or** that they may share Pianda coefficients. Close wording on P1, P2, and P4 is not that study.
5. UCC response rows. This repository has none for a local equivalence analysis.
6. A later UCC employment criterion. Pianda’s target is same-occasion EVS, not hiring.

Until that evidence exists, UCC C/P means must not be written into `CompetenceMean` / `PsyCapMean`.

## 3. M42 artifacts, schemas, leakage, and Linear vs Ridge

### Loading (read-only)

All five M42 joblib files load as `{pipeline, metadata}`. Pipelines were fit on the training split only. `production_validated` is false. `ucc_inference` is `blocked`. `risk_thresholds` is null. Dropout cards have no `feature_set` or `role` keys (`None` at load). Dataset SHA-256 values match the local gitignored raw files:

- Pianda workbook `f5e6fcdbb4e5687871a0b1727ca1f2ba3e1e7e6f785895ad763324617bcc064f`
- UCI CSV `3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae`

Seed 42. Employability split 212 / 54. Dropout split 3539 / 885, stratified on the three-class target.

Employability C/P pipeline steps: `schema` (exact columns, Likert 1–5, no imputation) → `scale` → `LinearRegression`. Extra `E1` or `EVS1` is rejected.

Dropout first-sem / two-sem: three-class labels `Dropout`, `Enrolled`, `Graduate`. Scholarship-only is the same three-class target, not M27’s binary 0/1.

### Why LinearRegression was selected although Ridge had a slightly lower CV RMSE

Pre-specified rule in `src/training/selection.py`: lower mean CV RMSE; if another selectable model is within **0.01** RMSE, keep the earlier name in the preference list.

Measured C/P reduced:

| Algorithm | CV RMSE |
| --- | --- |
| Ridge | 0.366314 |
| LinearRegression | 0.366398 |
| DummyRegressor | 0.5865 |

Difference Linear − Ridge = **0.000084**, inside the 0.01 margin. Preference is `LinearRegression`, then `Ridge`. Contenders: both. **LinearRegression wins by the tie-break, not by a meaningful RMSE advantage.** Holdout was not used to choose (Linear holdout RMSE 0.2957 vs Ridge 0.2958). This matches M25, which selected “Linear Regression” the same way. It is not a training defect. Algorithms were not changed in M42.1.

### Leakage

- EVS items and `EmployabilityMean` are not predictors.
- UCC E is rejected by the compatibility contract.
- Grade columns are absent from M42 dropout feature sets.
- Two-sem unit columns are labeled `after_second_semester`.
- Live student rows were not used.

### Reproducibility (this audit)

Reloaded M42 C/P artifact; two `predict` calls on the first 8 Pianda predictor rows matched: `[3.640902, 3.819946, 3.666118, 3.948558, 4.07548, 4.668954, 4.43948, 3.459325]`. Extra `EVS1` and `E1` columns raise `ValueError: Predictor schema mismatch`. `select_candidate` on the stored CV rows returns LinearRegression with contenders `[LinearRegression, Ridge]`. `python -m src.run_m42` was **not** re-run. Joblib SHA-256 values were unchanged after the inspect:

- `employability_cp_reduced_m42.joblib` `2b210d72cb271faa15539a229b30d5d0238b570878fcda4abdf11abf76c147be`
- `employability_cp_internship_expanded_m42.joblib` `fa5f30191fe7bdbee7fe1e1db9429310010641aea27eb900c52588a95403df38`
- `uci_first_sem_units_m42.joblib` `653c3a8ba1baed778ba383d8933d4bda057d897c775d4b1ca8060fc71769cf61`
- `uci_scholarship_only_m42.joblib` `6e5e2fd7e2ac9b601fca2c827d27d00f4503b599c09d494968c31b8869535595`
- `uci_two_sem_units_m42.joblib` `8566d0db02ef5337da1eb33a30d52f9f592f1137f72e3c02f831274a7a4ac2f5`

The six files under `ml/models/` were also unchanged.

## 4. Deleted 36-item PDFs

M42 commit `dfbd1d5` deleted:

- `src/cursor/reference/approved-questionnaire/EduPredict_Questionnaire.pdf`
- `src/cursor/reference/approved-questionnaire/EduPredict_Scoring_and_Research_Basis.pdf`

Those were the tracked 36-item sources (`ucc-panel-approved-v1`). Historic student answers still point at that version in Laravel and were not rescored. The PHP catalog `ApprovedInstrumentCatalog` still holds the 36 statements.

M42.1 restored the blobs from `b27211b`. SHA-256 matches the M25 review:

| File | Bytes | SHA-256 |
| --- | --- | --- |
| `EduPredict_Questionnaire.pdf` | 172602 | `fd34cfccd89c70ca790d3c495889b057645ffc7fab1202473a804ba8ac40ef43` |
| `EduPredict_Scoring_and_Research_Basis.pdf` | 265759 | `0a47b9ca326494b6b63307cf9e85b98df5df36219b57ba052ff9882b66cdcb86` |

Current 60-item files remain:

- `SystemQuestions.pdf`
- `EduPredict_Scoring_and_Research_Basis_Updated.pdf`

See `src/cursor/reference/approved-questionnaire/README.md`. Live questionnaire version stays `ucc-panel-revised-60-v2`. Stored answers were not touched.

## 5. M43 readiness matrix

| Item | Bucket | Why |
| --- | --- | --- |
| Isolated Python scorer + unit tests for 60-item means | **Safe for isolated research integration** | No Laravel change. Not a predictor. |
| Source-native Pianda C/P joblib scored **only** with Pianda `CompetenceMean`/`PsyCapMean` | **Safe for isolated research integration** | After a **new** loader, not by pointing M33 at the file. UCC answers still blocked. |
| Source-native UCI three-class unit/scholarship joblib scored **only** with UCI columns | **Safe for isolated research integration** | Same: new research path, three-class output, no student workflow. |
| M42 reports, mapping, model cards | **Safe for isolated research integration** | Documentation and audit trail. |
| First/second-sem enrolled and approved counts from UCC grade rows | **Requires application feature engineering** | Need a term-identity memo, approval definition, NSTP/repeat/Midyear rules, and new FeatureBuilder fields. Do not ship until the memo can fail review. |
| Scholarship 0/1 from `scholarship_status` | **Requires application feature engineering** | Collapse is unverified. Missing/draft profiles must refuse, not become 0. FeatureBuilder currently emits false/null. |
| Questionnaire section means in prediction snapshots | **Requires application feature engineering** plus panel review | v2 means are stored on responses; they are not `FeatureSet` inputs and must not be renamed to Pianda columns. |
| Treating UCC C/P as Pianda C/P | **Requires expert/panel review or validation** | Zero exact items; P is 10 vs 14; no CVI/alpha/invariance. |
| Labeling v2 as panel-reapproved or psychometrically validated | **Requires expert/panel review or validation** | Scoring PDF is a consultation draft. |
| Low/Moderate/High on P(Dropout) | **Requires expert/panel review or validation** | No calibrated cuts. Do not copy 1/3 or heuristic 0.30/0.60. |
| UCC employment or dropout labels | **Requires real UCC outcome data** | None in the repo. Do not build labels from GWA. |
| Local calibration/fairness | **Requires real UCC outcome data** | Source Brier/ROC are not UCC calibration. |
| Feeding GWA into UCI grade columns | **Blocked from production** | Scale and direction differ. |
| Feeding A/S/B into UCI dropout | **Blocked from production** | Different constructs. |
| Feeding E into employability regression | **Blocked from production** | Target leakage. |
| Switching `PREDICTOR_DRIVER` or scoring students with M42 joblib | **Blocked from production** | Mapping, units, labels, and M33 contract all fail. |
| Pointing existing M33 FastAPI at `ml/artifacts/m42/` | **Blocked from production** (and blocked as a silent M33 swap) | See section 6. |

## 6. Can existing M33 FastAPI load M42 artifacts without modification?

**No.** Documented only. The service was not started and no predictor driver was switched.

M33 `Registry` (`ml/api/registry.py`) loads **only** from `ML_MODELS_DIR` (default `ml/models/`):

- `employability_candidate_v1.joblib`
- four **binary** M27 files: `full`, `after_first_semester`, `at_enrollment`, `scholarship_only`

M42 files live in `ml/artifacts/m42/` with different names. Even if copied into `models/`, startup checks fail:

| Check | M33 expects | M42 C/P artifact | M42 dropout artifacts |
| --- | --- | --- | --- |
| `metadata["name"]` | `employability_candidate_v1` | `employability_cp_reduced_m42` | `uci_*_m42` |
| `metadata["algorithm"]` | `"Linear Regression"` / `"Logistic Regression"` | `"LinearRegression"` (no space) | Logistic or **Random Forest Classifier** |
| Dropout classes | `[0, 1]` | n/a | `Dropout`, `Enrolled`, `Graduate` |
| `target_definition` | positive 1 Dropout / negative Not Dropout | absent | three-class labels |
| `feature_set` / `role` | M27 keys and roles | absent | **both `None` on the M42 dropout artifacts** |
| Response schema | binary class names; algorithm Literal | would not validate | three-class `predict` would fail `_positive_probability` |

`DropoutRequest.feature_set` is only `full | after_first_semester | at_enrollment | scholarship_only`. There is no M42 unit-count set. Employability request still requires `schema_id=pianda-v1` and two floats; that payload shape is source-native, but the loaded pipeline would still be the **M25** file unless the registry is rewritten.

`HttpResearchInferenceClient` also asserts response `algorithm === 'Linear Regression'` and binary dropout fields.

M43 may add a **separate** research route or registry after approval. It must not replace M33’s current files or student heuristic.

## 7. Tests run (non-destructive)

```bash
cd ml
PYTHONPATH=. .venv/bin/python -m pytest tests/test_m42_scorer.py tests/test_m42_mapping.py \
  tests/test_m42_compatibility.py tests/test_m42_experiments.py tests/test_m25.py tests/test_m27.py -q
```

Result: **56 passed** in 137.99s on 2026-10-30. PHPUnit, `migrate:fresh`, Supabase, FastAPI process start, and `src.run_m42` were not run. Shared databases were not reset.

## M43: what is ready, what is blocked, what is safe next

**Ready now (research only):** the 60-item isolated scorer; item-level mapping with pending/blocked flags; source-native Pianda C/P and UCI three-class artifacts under `ml/artifacts/m42/`; honest holdout numbers; restored 36-item PDFs; this matrix.

**Still blocked for students:** UCC C/P as Pianda inputs; UCC unit counts as UCI enrolled/approved/evaluated; GWA as UCI grades; A/S/B as dropout features; E as an employability predictor; M33 loading M42 files; any production driver switch.

**Safe M43 work, if approved locally:**

1. Keep `PREDICTOR_DRIVER=heuristic`. Do not deploy M42 models.
2. Optionally add a **new** research-only loader for `ml/artifacts/m42/` that does not replace M33 contracts, still rejects UCC keys, and labels output as source-trained.
3. Write the term-identity and scholarship memos **before** any FeatureBuilder change. If the memo cannot define first semester without inventing history, do not engineer the feature.
4. Collect panel CVI and a CLAS-like pilot; do not claim equivalence from P1/P2/P4 wording.
5. Plan later outcome collection with consent. Do not synthesize dropout/employment labels from current grades.

Nothing in M42.1 authorizes scoring a UCC student with these candidates.
