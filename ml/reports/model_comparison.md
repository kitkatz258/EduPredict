# Model comparison (M42)

Regression metrics are not compared with classification metrics. External holdout scores are **not** UCC validity.

## Employability (native 1–5 EVS mean)

| Experiment | Features | Selected algorithm | CV RMSE | Holdout MAE | Holdout RMSE | Holdout R² | UCC inference |
| --- | --- | --- | --- | --- | --- | --- | --- |
| C/P reduced | CompetenceMean, PsyCapMean | LinearRegression | 0.3664 ± 0.0462 | 0.1993 | 0.2957 | 0.7461 | blocked |
| Internship expanded | C/P + InternshipMean | LinearRegression | 0.3616 ± 0.0405 | 0.2121 | 0.3001 | 0.7385 | blocked; not deployment-compatible |
| DummyRegressor baseline on C/P | mean of training target | DummyRegressor | 0.5865 ± 0.0686 | 0.3777 | 0.5880 | -0.0040 | n/a |

M25 previously selected Linear Regression on the same two Pianda means (CV RMSE 0.3664 ± 0.0462). M42 re-runs that protocol with DummyRegressor named explicitly and stores a separate artifact. It does not replace `employability_candidate_v1.joblib`.

## Dropout (three UCI classes)

| Experiment | n features | Selected algorithm | CV macro F1 | Holdout macro F1 | Holdout accuracy | Holdout dropout recall | Timing | UCC status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Scholarship holder only | 1 | Logistic Regression | 0.3492 ± 0.0088 | 0.3481 | 0.4802 | 0.9331 | after_published_profile | pending_mapping |
| First-semester enrolled and approved units (grades excluded) | 2 | Logistic Regression | 0.6061 ± 0.0126 | 0.6347 | 0.6802 | 0.5563 | after_first_semester | pending_unit_definition |
| First- and second-semester enrolled and approved units (grades excluded) | 4 | Random Forest Classifier | 0.6584 ± 0.0175 | 0.6678 | 0.7141 | 0.6514 | after_second_semester | pending_unit_definition |
| Majority baseline (same split) | n/a | DummyClassifier most_frequent | 0.2220 ± 0.0002 | 0.2221 | 0.4994 | 0.0000 | n/a | not a candidate |

M25 scholarship-only logistic regression had CV macro F1 0.3492 and holdout Enrolled F1 0. M42 reports the re-measured values above rather than copying those figures as new evidence.

Full-feature M25 gradient boosting (36 predictors, including grades): CV macro F1 0.7211 ± 0.0153. **not run** in M42; not deployment-feasible.
