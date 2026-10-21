# Employability model comparison

Native target: EmployabilityMean, the verified EVS item mean on the 1–5 scale.

Predictors: CompetenceMean and PsyCapMean only.

Protocol, fixed before scores were read:

- Seed 42.
- Holdout 54 rows (20%); training partition 212 rows.
- RepeatedKFold, 5 splits × 5 repeats, on the training partition only.
- Linear and ridge models standardize the two means inside the pipeline. Tree models do not.
- Hyperparameters are library defaults except `Ridge(alpha=1.0)`, which is the library default, and `random_state` on the tree models.
- Selection metric: lower mean CV RMSE. If another selectable model is within 0.01 RMSE, the earlier model in this order is kept: Linear Regression, Ridge Regression, Random Forest Regressor, Gradient Boosting Regressor.
- The mean baseline is reported and is not selectable.
- Holdout columns are confirmatory. They were not used to choose the algorithm.

## Measured results

| Algorithm | CV MAE | CV RMSE | CV R² | Train RMSE | RMSE gap | Holdout MAE | Holdout RMSE | Holdout R² |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Linear Regression | 0.2831 ± 0.0327 | 0.3664 ± 0.0462 | 0.5708 ± 0.1532 | 0.3613 | 0.0051 | 0.1993 | 0.2957 | 0.7461 |
| Ridge Regression | 0.2832 ± 0.0327 | 0.3663 ± 0.0462 | 0.5711 ± 0.1527 | 0.3613 | 0.0050 | 0.1995 | 0.2958 | 0.7460 |
| Random Forest Regressor | 0.3502 ± 0.0440 | 0.4514 ± 0.0534 | 0.3565 ± 0.1924 | 0.2371 | 0.2143 | 0.2582 | 0.3772 | 0.5869 |
| Gradient Boosting Regressor | 0.3390 ± 0.0477 | 0.4460 ± 0.0617 | 0.3716 ± 0.1994 | 0.2654 | 0.1805 | 0.2426 | 0.3894 | 0.5596 |
| Mean baseline | 0.4477 ± 0.0430 | 0.5865 ± 0.0686 | -0.0471 ± 0.0887 | 0.5860 | 0.0005 | 0.3777 | 0.5880 | -0.0040 |

Selected research candidate: **Linear Regression**. Contenders inside the margin: Linear Regression, Ridge Regression.

Mean-baseline comparison on CV RMSE: the selected model has a lower mean CV RMSE than predicting the training-partition mean.

RMSE gap is validation RMSE minus training-fold RMSE. A positive gap means the training folds fit more closely than the validation folds.

## Interpretation of the saved training-partition fit

Kind: coefficients_on_scaled_features.

| Term | Value |
| --- | --- |
| CompetenceMean | 0.296275 |
| PsyCapMean | 0.197444 |

Intercept: 3.962657

The saved pipeline was fit on the training partition only. Refitting it on all 266 rows would make the stored holdout numbers refer to a different model.

With about 212 training rows and a 54-row holdout, fold and holdout metrics are uncertain. The two predictors and the target are same-occasion self-reports, so a high R² would still not establish employment prediction or UCC validity.

Figure: `figures/employability_rmse.png`. Holdout scatter: `figures/employability_holdout_scatter.png`.

Reproducibility rerun of this comparison matched: True.
