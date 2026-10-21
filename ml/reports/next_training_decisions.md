# Next training decisions

Review date: 2026-10-21. This file records decisions that have to be made before any further training. It does not retrain a model, does not set a risk threshold, does not invent a UCC-to-UCI grade conversion, and does not add rows or columns to either source dataset.

Neither `employability_candidate_v1.joblib` nor `dropout_candidate_v1.joblib` is production-ready. Both artifacts already say `production_validated: false`. This review confirms that status after reading the UCC PDFs and the Laravel feature path. M25 commit `d1615b10` remains the training commit. No FastAPI service and no Laravel integration follow from this review.

## What is already known

Employability, from `employability_model_comparison.md`, reloaded and not refit:

- Linear regression on Pianda `CompetenceMean` and `PsyCapMean`, target `EmployabilityMean` (EVS1–EVS12).
- Repeated CV RMSE 0.3664 ± 0.0462, CV R² 0.5708 ± 0.1532. Ridge was inside the 0.01 RMSE margin and lost on the pre-specified order.
- The target is a same-occasion self-report from 266 vocational secondary students. It is not a UCC employment outcome.

Dropout, from `dropout_model_comparison.md`, reloaded and not refit:

- Saved candidate: logistic regression on `Scholarship holder` only. CV macro F1 0.3492 for logistic regression, random forest, and gradient boosting. The tie was broken by the pre-specified order, not by a performance difference.
- Holdout: Enrolled F1 is 0. Dropout precision is 0.3897. The confusion matrix predicts Dropout for 680 of 885 rows. False negatives are 19 of 284 because the model over-predicts Dropout.
- Exploratory gradient boosting on all 36 predictors had CV macro F1 0.7211 and was not saved. After the second semester was removed, the same algorithm scored 0.6888. At the enrollment cut it scored 0.5766. Those cuts still use UCI fields the application does not have.

The item comparison is in `ucc_employability_mapping_review.md`. The 36-predictor classification and the three timing scenarios are in `ucc_dropout_feature_feasibility.md`.

## Decisions required before any new fit

These are open. This review does not answer them by choosing a threshold, a conversion, or a default imputation.

| ID | Decision | Why training is blocked until it is made | Bound this review does set |
| --- | --- | --- | --- |
| D1 | Is the PDF text the locked UCC instrument, given that both PDFs still say draft or consultation draft? | The app seeds a different 20-item `draft-v1` behavior scale. C and P are not collected. | Do not remap current 0–100 construct scores into `CompetenceMean` or `PsyCapMean`. |
| D2 | Will UCC C1–C8 and P1–P6 be treated as new scales, or will an equivalence study be required before they touch the Pianda coefficients? | Close content on some PsyCap items is not equivalence. Item counts are 8 versus 10 and 6 versus 14. No UCC response rows exist. | Do not score UCC means with `employability_candidate_v1`. Do not retrain the Pianda model to paper over that gap. |
| D3 | Are expert CVI and a CLAS-similar pilot alpha required before any employability refit that uses UCC items? | The scoring PDF specifies I-CVI, S-CVI/Ave, and per-construct alpha, and reports neither. | Do not treat approval of the PDF as those indices. |
| D4 | Is the employability target still the Pianda EVS mean, or a later UCC outcome? | E1–E4 is a different self-report and is a comparison measure. A job or placement outcome is not in the data. | Keep E, A, S, B, and SIE out of predictors. The readiness index stays a 1–5 display rescale, not a probability. |
| D5 | Is the scholarship collapse accepted: every published status other than `none` equals UCI 1, and `none` equals UCI 0? | Category equivalence was not claimed. The five local statuses cannot be separated in the UCI file. | Do not train a five-category scholarship model on the UCI file. The file has only 0 and 1. |
| D6 | What happens when scholarship status is missing or still draft? | `FeatureBuilder` currently sets `has_scholarship` to false. The UCI pipeline rejects missing values and does not impute. | Do not impute. A permissible inference rule is to refuse the prediction. That rule is not a new training label. |
| D7 | Is age at enrollment allowed, and what date defines enrollment? | Birthdate is optional and is not in the feature contract. `enrollment_year` is the registration calendar year. Age is a sensitive attribute. | Do not impute age. Do not compute age from the registration year and call it age at enrollment. |
| D8 | How is "first semester" identified, and how are missing earlier terms represented? | Confirmed reports have no degree-semester index. One uploaded later term looks like a first term. Alphabetical semester labels are not calendar order. | Do not code a missing term as zero failures or as a zero grade. |
| D9 | Will any academic count be defined against UCI enrolled or approved units? | Subject rows could support a count. Credited units, evaluation counts, and units without evaluations do not match local fields. Median first-semester credited units on the UCI file are 0. | Do not put a local unit total into the credited, evaluations, or without-evaluations columns. |
| D10 | Is there an external, already written Philippine-to-Portuguese grade conversion? | Local GWA is lower-is-better, 1.00–5.00, with its own exclusions. UCI curricular grades are higher-is-better, 0–20. Admission and prior grades are 0–200 and are not stored. | Do not invent a conversion in the next experiment. Both semester grade columns stay inappropriate as GWA substitutes. |
| D11 | Which prediction time is the product supposed to support: enrollment, post-first-semester, or latest confirmed record? | These are three different feature sets. The UCI model stops at second-semester curricular fields. A pooled multi-year GWA is a different variable. | Do not feed latest-confirmed GWA into a 1st-sem or 2nd-sem column. Do not quote the 0.5766 enrollment figure as an EduPredict result. |
| D12 | Will dropout labels be the UCI three-class target, or a future UCC retention label? | No local dropout label is stored. Synthesizing one from grades would build the outcome from the predictors. | Do not create a local Dropout, Enrolled, or Graduate label from current rows. |
| D13 | Where do Low, Moderate, and High cuts come from, if they are ever shown? | The scoring PDF says they should come from validation and calibration, not from splitting probability into thirds. The heuristic uses 0.30 and 0.60. The saved artifacts store no cuts. | Do not select a cut in the next experiment. Do not copy the heuristic cuts onto P(Dropout). |
| D14 | Will the two approved PDFs be committed? | They are untracked. A later checkout cannot repeat this item reading from git alone. | This review does not commit the PDFs. |

Until D2, D5, D6, D10, D11, and D12 are recorded, there is no scientifically defensible reduced feature set to train.

## Experiments that are defensible after those decisions

Each experiment below is a design. None of them was run. None of them adds data, invents a grade formula, or chooses a risk cut.

### E1. Keep the current employability candidate as a Pianda-only research fit

Run only if D2 says UCC items are new scales. The experiment is a confirmation, not a new fit: the saved pipeline is used only on Pianda `CompetenceMean` and `PsyCapMean`. UCC answers are not passed through it. No second training on the same 266 rows is required. The M25 comparison already selected linear regression.

If D2 instead requires an equivalence study, that study needs new responses. It is not a scikit-learn rerun of this repository.

### E2. Scholarship-only policy check, without a new algorithm search

Run only if D5 accepts the binary collapse and D6 chooses a missing-data rule. The UCI file has no missing scholarship values, so D6 cannot be estimated from that file. The useful product of E2 is a written scoring rule: the existing candidate may be discussed only for rows that already have a published status, and a missing status refuses the score. Retraining logistic regression, random forest, and gradient boosting on the same single column will reproduce the tie at CV macro F1 0.3492. That rerun does not add information. Enrolled F1 and a calibration plot stay part of the report. No probability cut is chosen.

If D5 rejects the collapse, the reduced UCI candidate has no remaining feature. It is retired as a deployment candidate and left as a research baseline. It is not replaced by a model that needs unavailable columns.

### E3. Age add-on, only after D7

If age is allowed, pre-specify the enrollment date and exclude rows with no birthdate. Do not impute. Fit the M25 protocol on the UCI training split with two pre-specified sets: scholarship alone, and scholarship plus age at enrollment. Keep the existing holdout untouched until the algorithm and the feature set are fixed. Report per-class F1, dropout false negatives, and the Brier score for P(Dropout). Do not add a third feature in the same run. Age remains a sensitive attribute and needs an explicit fairness note in the report. This experiment is blocked now, because the application does not store a usable enrollment date.

### E4. Term-definition memo, with no model

If D8 and D9 are opened, the next artifact is a definition, not an estimator. It has to state how a confirmed `school_year` and semester label become "1st sem" or "2nd sem", how Midyear and Summer are treated, how repeated subjects are counted, and how a missing earlier term is represented without a zero. Compare that definition with the UCI credited, enrolled, approved, evaluations, and without-evaluations fields using the distributions already measured. Train a count model only if that memo concludes the definitions match. This review does not conclude that they match.

### E5. Local-outcome design, separate from the UCI file

If D11 and D12 choose a UCC decision time and a UCC label, the reduced feature set should be native: the Philippine GWA as itself, the local failure count, the local scholarship categories, and the number of confirmed terms, with `limited_history` and the provisional-INC flag kept as eligibility rules rather than as silent recodes. Labels have to be collected later, with consent. That is a new dataset and a new model. It is not a transfer of `dropout_candidate_v1`, and it does not use a converted 0–20 grade. Do not start it by manufacturing labels from the grades already stored.

### E6. What stays unrun

- Any model that places A, S, B, C, P, or E into a UCI column.
- Any model that converts GWA, admission grade, or previous-qualification grade.
- Any model that fills missing scholarship, age, or grades.
- Any selection of Low, Moderate, or High.
- Any refit of the 36-predictor exploratory model for deployment.
- FastAPI, Laravel changes, and schema changes.

## Reduced set that can be discussed today

The only reduced set that has actually been estimated is UCI `Scholarship holder` under a complete-case 0/1 coding that the UCI file already satisfies. Its measured holdout behavior is too weak for use, and the EduPredict collapse plus the missing-profile coercion are unresolved. No broader reduced set is feasible until D5–D12 are decided. Calling a larger set feasible by substituting GWA, program code, or living arrangement would invent the mappings this review refuses.

## Verification

Performed for this review, without retraining:

- Extracted both PDFs (4 questionnaire pages, 9 scoring pages) and checked that codes A1–A6, S1–S6, B1–B6, C1–C8, P1–P6, and E1–E4 are present.
- Compared those C and P sentences with the Pianda questionnaire and the codebook. No English sentence is identical.
- Reloaded both joblib artifacts. `production_validated` is false and `risk_thresholds` is null. Employability inputs are the two Pianda means. Dropout input is `Scholarship holder`.
- Recomputed the scholarship-by-target counts on the UCI file and the grade ranges cited in the feasibility report.
- Read `FeatureBuilder`, `GwaCalculator`, registration, the importer, and the questionnaire seeder. Did not query live student rows: MySQL host `db` was not reachable, and no row-level student data was exported.
- Ran `ml/.venv/bin/python -m pytest tests/test_m25.py -q` from `ml/`. Result: 10 passed, 1 failed. The failure is `test_manifests_keep_context_out_of_predictors`, which asserts `approved_pdf_status()["item_mapping_blocked"] is True`. Both PDFs are now on disk, so that flag is false. The assertion matched the M25 commit, when the PDF directory was empty. This review does not change the test. Artifact reload, schema, archive, and the other M25 checks in that module passed.

`ml/src/features/ucc.py` still describes item mapping as blocked because the PDFs were missing at M25. That module was left unchanged. The item reading now lives in `ucc_employability_mapping_review.md`. Updating `ucc.py` or its test is a later code change, not part of this decision file.

## Stop

No training script is run from this review. No FastAPI application is added. No Laravel class, migration, or existing report is edited. The next code change waits on D1–D14.
