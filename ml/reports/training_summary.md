# M25 training summary

Generated: 2026-10-21T22:41:22+08:00
Seed: 42
Library versions: {"joblib": "1.5.2", "matplotlib": "3.10.7", "numpy": "2.3.4", "openpyxl": "3.1.5", "pandas": "2.3.3", "scikit-learn": "1.7.2", "scipy": "1.16.2"}

These artifacts are research candidates. They are not production-validated. Panel approval of the UCC questionnaire is not evidence that pilot reliability, construct equivalence, or external validity has been completed.

## What was trained

- Employability v1: Linear Regression predicting the native EVS mean from CompetenceMean and PsyCapMean.
- Dropout candidate: Logistic Regression using only Scholarship holder.
- Dropout exploratory reference, not saved: Gradient Boosting Classifier using all 36 UCI predictors.

The employability model beat the mean baseline on mean CV RMSE.

## Blockers

- Approved PDFs missing: src/cursor/reference/approved-questionnaire/EduPredict_Questionnaire.pdf, src/cursor/reference/approved-questionnaire/EduPredict_Scoring_and_Research_Basis.pdf. Item-level UCC mapping and UCC section scoring were stopped.
- UCC-to-Portuguese grade transformation: unresolved. Curricular and admission grades are excluded from the dropout candidate.
- Age at enrollment: birthdate exists on the institution list and is nullable; the current feature contract does not emit it. Left as REVIEW.
- Archived employability workbook CRC does not match the loose workbook. The archive was not merged.
- Pianda sample is vocational secondary students in Aceh, observed with a same-occasion self-report employability scale. It is not a UCC employment outcome.
- Sampling description disagrees between the Mendeley record (simple random) and the 2025 article (cluster sample of 17 schools).

## Display rule for a later readiness index

When a native prediction is inside [1, 5], readiness = ((prediction - 1) / 4) * 100. The function does not round. It is not a probability, and it is not a Low/Moderate/High rule. Thresholds from the unread consultation draft were not implemented.

## Reproducibility

- Employability comparison rerun matched: True
- Reduced dropout comparison rerun matched: True
