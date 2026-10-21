# M27 summary

Generated: 2026-10-22T00:15:50+08:00

Dropout is coded 1. Enrolled and Graduate are coded 0 and called Not Dropout.

Not Dropout is an observed grouping of UCI records labeled Enrolled or Graduate at the end of the normal course duration. It is not proof of future retention.

Model selection used training-partition Dropout F1. The M25 holdout was scored only as an exploratory reuse and is not an independent final validation. No model is production-validated, and no Low, Moderate, or High threshold was set.

| Feature set | Role | Columns | Selected by Dropout F1 | CV Dropout F1 | CV ROC-AUC | CV PR-AUC | CV Brier | CV accuracy | OOF FN | OOF FP |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| full | exploratory_reference | 36 | Logistic Regression | 0.7965 ± 0.0183 | 0.9235 ± 0.0120 | 0.8845 ± 0.0174 | 0.0996 ± 0.0072 | 0.8657 ± 0.0118 | 206 | 264 |
| after_first_semester | timing_sensitivity | 30 | Logistic Regression | 0.7717 ± 0.0160 | 0.9046 ± 0.0120 | 0.8586 ± 0.0187 | 0.1133 ± 0.0067 | 0.8484 ± 0.0110 | 231 | 312 |
| at_enrollment | timing_sensitivity | 24 | Logistic Regression | 0.6542 ± 0.0223 | 0.8236 ± 0.0165 | 0.7372 ± 0.0242 | 0.1644 ± 0.0063 | 0.7613 ± 0.0151 | 340 | 510 |
| scholarship_only | weak_compatibility_baseline | 1 | Logistic Regression | 0.5405 ± 0.0088 | 0.6116 ± 0.0115 | 0.3799 ± 0.0071 | 0.2341 ± 0.0040 | 0.5089 ± 0.0130 | 115 | 1623 |

Feature-timing limits:

- full: Logistic Regression. Uses all 36 UCI predictors, including second-semester curricular units and grades. Those fields are late academic results. This set is an exploratory reference, not an enrollment-time model, and it has no verified UCC mapping.
- after_first_semester: Logistic Regression. Drops the six second-semester curricular columns and keeps first-semester curricular results. The columns are appropriate only after those first-semester results exist. Grades stay on the UCI scale. No UCC conversion is applied.
- at_enrollment: Logistic Regression. Drops all twelve curricular-unit columns. Debtor and tuition fees up to date stay in this cut, matching the M25 timing definition, but their snapshot date is unverified and they may not be known at enrollment. Macroeconomic fields and demographic codes remain. They are UCI source fields. No UCC mapping was added.
- scholarship_only: Logistic Regression. Uses only the UCI Scholarship holder column coded 0 or 1 in the source file. This is a weak compatibility baseline. It is not a model of the five EduPredict scholarship statuses, and a missing UCC profile is not recoded to 0.

The full exploratory score uses second-semester curricular results. It is an upper reference on source fields EduPredict cannot supply as the same measurements. The enrollment cut still includes UCI fields with no verified local equivalent, and debtor and tuition timing remain unverified. Scholarship-only is the weak compatibility baseline. The five local scholarship statuses were not trained, and no grade conversion was invented.

Employability benchmark, not refit: Linear Regression in `employability_candidate_v1`, inputs ['CompetenceMean', 'PsyCapMean'], stored CV RMSE 0.3664. UCC questionnaire scores were not substituted.

Demo commands, from `ml/` with `PYTHONPATH=.`:

```bash
python -m src.demo_m27 dropout --offset 0
python -m src.demo_m27 employability --offset 0
```

Reproducibility: scholarship CV True; full selected algorithm CV True.

Artifacts:

- `ml/models/dropout_binary_full_exploratory_m27.joblib`: m27-binary-dropout-full-logistic_regression-2026-10-22T00:15:50+08:00
- `ml/models/dropout_binary_after_first_semester_m27.joblib`: m27-binary-dropout-after_first_semester-logistic_regression-2026-10-22T00:15:50+08:00
- `ml/models/dropout_binary_at_enrollment_m27.joblib`: m27-binary-dropout-at_enrollment-logistic_regression-2026-10-22T00:15:50+08:00
- `ml/models/dropout_binary_scholarship_baseline_m27.joblib`: m27-binary-dropout-scholarship_only-logistic_regression-2026-10-22T00:15:50+08:00
- `ml/reports/binary_dropout_model_comparison.md`
- `ml/reports/binary_dropout_metrics.json`

## Commands

```bash
export OMP_NUM_THREADS=1 OPENBLAS_NUM_THREADS=1 MKL_NUM_THREADS=1 NUMEXPR_NUM_THREADS=1
export PYTHONUNBUFFERED=1 PYTHONPATH=.
.venv/bin/python -m src.run_m27
.venv/bin/python -m pytest
.venv/bin/python -m src.demo_m27 dropout --offset 0
.venv/bin/python -m src.demo_m27 employability --offset 0
```

## Test results

From `ml/`, with one numeric thread and `PYTHONPATH=.`:

```bash
.venv/bin/python -m pytest -q --tb=line
```

Result: 20 passed in 13.43s.

The source-data demos were run from the same directory:

```bash
.venv/bin/python -m src.demo_m27 dropout --offset 0
.venv/bin/python -m src.demo_m27 employability --offset 0
```

Both print `SOURCE-DATA RESEARCH DEMO` and state that the row is not a UCC student prediction. The dropout demo used UCI row 1853 from the reused holdout. The observed label was Graduate, coded Not Dropout, and the full-model probability was 0.062467. The employability demo used Pianda row 181, with CompetenceMean 4.0000 and PsyCapMean 3.7857. The saved linear regression predicted 3.9217.
