"""Construct-level employability readiness experiment on the verified Pianda means."""

from __future__ import annotations

import numpy as np
import pandas as pd
from sklearn.base import clone
from sklearn.dummy import DummyRegressor
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.linear_model import LinearRegression, Ridge
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import RepeatedKFold, cross_validate
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import StandardScaler

from src.common import (
    N_REPEATS,
    N_SPLITS,
    RANDOM_SEED,
    RMSE_SELECTION_MARGIN,
    TEST_SIZE,
)
from src.data.employability import EMPLOYABILITY_TARGET, V1_PREDICTORS
from src.data.partitions import employability_train_test_split
from src.features.schemas import FrameSchema
from src.training.selection import select_candidate

PREFERENCE = [
    "Linear Regression",
    "Ridge Regression",
    "Random Forest Regressor",
    "Gradient Boosting Regressor",
]


def _pipeline(estimator, scale: bool) -> Pipeline:
    steps = [
        ("schema", FrameSchema(V1_PREDICTORS, value_rule="likert_mean")),
    ]
    if scale:
        steps.append(("scale", StandardScaler()))
    steps.append(("model", estimator))
    return Pipeline(steps)


def candidate_estimators(seed: int = RANDOM_SEED) -> dict[str, Pipeline]:
    return {
        "Linear Regression": _pipeline(LinearRegression(), scale=True),
        "Ridge Regression": _pipeline(Ridge(alpha=1.0), scale=True),
        "Random Forest Regressor": _pipeline(
            RandomForestRegressor(random_state=seed, n_jobs=1),
            scale=False,
        ),
        "Gradient Boosting Regressor": _pipeline(
            GradientBoostingRegressor(random_state=seed),
            scale=False,
        ),
    }


def _regression_scores(y_true, y_pred) -> dict[str, float]:
    return {
        "mae": float(mean_absolute_error(y_true, y_pred)),
        "rmse": float(np.sqrt(mean_squared_error(y_true, y_pred))),
        "r2": float(r2_score(y_true, y_pred)),
    }


def _cv_row(name: str, result: dict, selectable: bool) -> dict:
    rmse = -result["test_rmse"]
    train_rmse = -result["train_rmse"]
    return {
        "algorithm": name,
        "selectable": selectable,
        "cv_mae_mean": float((-result["test_mae"]).mean()),
        "cv_mae_std": float((-result["test_mae"]).std(ddof=1)),
        "cv_rmse_mean": float(rmse.mean()),
        "cv_rmse_std": float(rmse.std(ddof=1)),
        "cv_r2_mean": float(result["test_r2"].mean()),
        "cv_r2_std": float(result["test_r2"].std(ddof=1)),
        "cv_train_rmse_mean": float(train_rmse.mean()),
        "cv_train_r2_mean": float(result["train_r2"].mean()),
        "rmse_generalization_gap": float(rmse.mean() - train_rmse.mean()),
        "n_folds": int(len(rmse)),
    }


def train_employability(
    predictors: pd.DataFrame,
    target: pd.Series,
    *,
    seed: int = RANDOM_SEED,
    n_splits: int = N_SPLITS,
    n_repeats: int = N_REPEATS,
    test_size: float = TEST_SIZE,
) -> dict:
    if list(predictors.columns) != list(V1_PREDICTORS):
        raise ValueError("Employability training must use CompetenceMean and PsyCapMean only.")
    if target.name not in {None, EMPLOYABILITY_TARGET}:
        raise ValueError("Unexpected employability target name.")
    forbidden = [column for column in predictors.columns if column.startswith("EVS") or "Employability" in column]
    if forbidden:
        raise ValueError(f"Target columns leaked into predictors: {forbidden}.")

    x_train, x_test, y_train, y_test = employability_train_test_split(
        predictors,
        target,
        seed=seed,
        test_size=test_size,
    )
    cv = RepeatedKFold(n_splits=n_splits, n_repeats=n_repeats, random_state=seed)
    scoring = {
        "mae": "neg_mean_absolute_error",
        "rmse": "neg_root_mean_squared_error",
        "r2": "r2",
    }
    rows = []
    fitted = {}
    models = candidate_estimators(seed)
    models["Mean baseline"] = _pipeline(DummyRegressor(strategy="mean"), scale=False)
    for name, pipeline in models.items():
        result = cross_validate(
            pipeline,
            x_train,
            y_train,
            cv=cv,
            scoring=scoring,
            n_jobs=1,
            return_train_score=True,
            error_score="raise",
        )
        row = _cv_row(name, result, selectable=name != "Mean baseline")
        estimator = clone(pipeline)
        estimator.fit(x_train, y_train)
        holdout_pred = estimator.predict(x_test)
        train_pred = estimator.predict(x_train)
        row["holdout"] = _regression_scores(y_test, holdout_pred)
        row["train_resubstitution"] = _regression_scores(y_train, train_pred)
        rows.append(row)
        fitted[name] = estimator

    decision = select_candidate(
        rows,
        "cv_rmse_mean",
        higher=False,
        margin=RMSE_SELECTION_MARGIN,
        preference=PREFERENCE,
    )
    selected_name = decision["algorithm"]
    selected = fitted[selected_name]
    baseline = next(row for row in rows if row["algorithm"] == "Mean baseline")
    selected_row = next(row for row in rows if row["algorithm"] == selected_name)
    interpretation = _interpret(selected)
    return {
        "rows": rows,
        "decision": decision,
        "pipeline": selected,
        "interpretation": interpretation,
        "beats_mean_baseline_on_cv_rmse": selected_row["cv_rmse_mean"] < baseline["cv_rmse_mean"],
        "split": {
            "seed": seed,
            "test_size": test_size,
            "n_splits": n_splits,
            "n_repeats": n_repeats,
            "train_rows": int(len(x_train)),
            "test_rows": int(len(x_test)),
        },
        "holdout_predictions": {
            "y_true": y_test.to_numpy(dtype=float),
            "y_pred": selected.predict(x_test).astype(float),
        },
        "input_columns": list(V1_PREDICTORS),
        "target": EMPLOYABILITY_TARGET,
    }


def _interpret(pipeline: Pipeline) -> dict:
    model = pipeline.named_steps["model"]
    columns = list(V1_PREDICTORS)
    if hasattr(model, "coef_"):
        coefficients = np.asarray(model.coef_, dtype=float).ravel()
        return {
            "kind": "coefficients_on_scaled_features" if "scale" in pipeline.named_steps else "coefficients",
            "intercept": float(model.intercept_),
            "terms": [
                {"feature": column, "coefficient": float(value)}
                for column, value in zip(columns, coefficients, strict=True)
            ],
        }
    importances = np.asarray(model.feature_importances_, dtype=float)
    return {
        "kind": "impurity_importance",
        "terms": [
            {"feature": column, "importance": float(value)}
            for column, value in zip(columns, importances, strict=True)
        ],
    }
