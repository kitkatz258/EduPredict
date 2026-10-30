# Employability experiments (M42)

Research-only source-native regression on the Pianda workbook. Target: `EmployabilityMean`, the unweighted mean of EVS1–EVS12 on the verified 1–5 scale. R² is not an accuracy percentage. UCC inference is blocked; UCC students were not scored.

Protocol, fixed before scores were read:

- Seed 42. Holdout 54 rows (20%); training 212 rows.
- RepeatedKFold, 5 splits × 5 repeats, training partition only.
- LinearRegression and Ridge standardize inside the pipeline. Tree models and DummyRegressor do not.
- Selection: lower mean CV RMSE. Within 0.01 RMSE, earlier algorithm wins: LinearRegression, Ridge, RandomForestRegressor, GradientBoostingRegressor.
- DummyRegressor (mean) is reported and is not selectable.
- Holdout was not used to choose the algorithm.
- EVS items, EmployabilityMean, and UCC E items are excluded from predictors.

## A. Reduced C/P-only (source-native candidate experiment)

Predictors: Pianda `CompetenceMean` (C1–C10) and `PsyCapMean` (PsyCap1–PsyCap14).
UCC 10-item C/P means are **not** these columns. Deployment inference of UCC scores is **rejected**.

| Algorithm | CV MAE | CV RMSE | CV R² | Train RMSE | RMSE gap | Holdout MAE | Holdout RMSE | Holdout R² |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| LinearRegression | 0.2831 ± 0.0327 | 0.3664 ± 0.0462 | 0.5708 ± 0.1532 | 0.3613 | 0.0051 | 0.1993 | 0.2957 | 0.7461 |
| Ridge | 0.2832 ± 0.0327 | 0.3663 ± 0.0462 | 0.5711 ± 0.1527 | 0.3613 | 0.0050 | 0.1995 | 0.2958 | 0.7460 |
| RandomForestRegressor | 0.3502 ± 0.0440 | 0.4514 ± 0.0534 | 0.3565 ± 0.1924 | 0.2371 | 0.2143 | 0.2582 | 0.3772 | 0.5869 |
| GradientBoostingRegressor | 0.3390 ± 0.0477 | 0.4460 ± 0.0617 | 0.3716 ± 0.1994 | 0.2654 | 0.1805 | 0.2426 | 0.3894 | 0.5596 |
| DummyRegressor | 0.4477 ± 0.0430 | 0.5865 ± 0.0686 | -0.0471 ± 0.0887 | 0.5860 | 0.0005 | 0.3777 | 0.5880 | -0.0040 |

Selected by CV RMSE: **LinearRegression**. Contenders: LinearRegression, Ridge.

Interpretation of the training-partition fit:

Kind: `coefficients_on_scaled_features`.
Intercept: 3.962657

| Feature | Coefficient |
| --- | --- |
| CompetenceMean | 0.296275 |
| PsyCapMean | 0.197444 |

## B. Expanded internship comparison (not deployment-compatible)

Predictors: CompetenceMean, PsyCapMean, and InternshipMean (SIE1–SIE14). EduPredict stores an internship **count**, not this 14-item Likert mean. Every Pianda respondent had internship experience. This set is a labeled source-native comparison only.

| Algorithm | CV MAE | CV RMSE | CV R² | Train RMSE | RMSE gap | Holdout MAE | Holdout RMSE | Holdout R² |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| LinearRegression | 0.2788 ± 0.0304 | 0.3616 ± 0.0405 | 0.5799 ± 0.1630 | 0.3518 | 0.0098 | 0.2121 | 0.3001 | 0.7385 |
| Ridge | 0.2788 ± 0.0304 | 0.3614 ± 0.0405 | 0.5803 ± 0.1626 | 0.3518 | 0.0097 | 0.2123 | 0.3003 | 0.7382 |
| RandomForestRegressor | 0.3321 ± 0.0342 | 0.4233 ± 0.0477 | 0.4284 ± 0.2097 | 0.1697 | 0.2536 | 0.2336 | 0.3465 | 0.6515 |
| GradientBoostingRegressor | 0.3345 ± 0.0399 | 0.4302 ± 0.0508 | 0.4089 ± 0.2193 | 0.2084 | 0.2218 | 0.2237 | 0.3376 | 0.6691 |
| DummyRegressor | 0.4477 ± 0.0430 | 0.5865 ± 0.0686 | -0.0471 ± 0.0887 | 0.5860 | 0.0005 | 0.3777 | 0.5880 | -0.0040 |

Selected by CV RMSE on this expanded set: **LinearRegression**. Contenders: LinearRegression, Ridge.

This expanded winner is **not** a deployment candidate.

## Display index

If a native prediction stays inside 1–5, `((prediction - 1) / 4) * 100` may be shown. Clamp out-of-range display values for presentation only; store the native score. The index is not P(employment).

C-only and P-only single-construct fits: **not run** as deployment candidates. Both source constructs are defensibly calculated; the specified reduced experiment uses both.

Reproducibility rerun of the C/P comparison matched: True
