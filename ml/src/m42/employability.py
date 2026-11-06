"""Source-native Pianda employability experiments for M42.

Does not modify M25 training or saved M25/M33 artifacts.
UCC questionnaire means are not used as training rows.
"""

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

from src.common import RMSE_SELECTION_MARGIN
from src.data.employability import (
    COMPETENCE_MEAN,
    EMPLOYABILITY_TARGET,
    INTERNSHIP_ITEMS,
    PSYCAP_MEAN,
    V1_PREDICTORS,
)
from src.data.partitions import employability_train_test_split
from src.features.schemas import FrameSchema
from src.m42.common import M42_N_REPEATS, M42_N_SPLITS, M42_SEED, M42_TEST_SIZE
from src.training.selection import select_candidate

PREFERENCE = [
    "LinearRegression",
    "Ridge",
    "RandomForestRegressor",
    "GradientBoostingRegressor",
]

CP_COLUMNS = list(V1_PREDICTORS)
CP_INTERNSHIP_COLUMNS = [COMPETENCE_MEAN, PSYCAP_MEAN, "InternshipMean"]


def _pipeline(columns: list[str], estimator, scale: bool) -> Pipeline:
    steps = [("schema", FrameSchema(columns, value_rule="likert_mean"))]
    if scale:
        steps.append(("scale", StandardScaler()))
    steps.append(("model", estimator))
    return Pipeline(steps)


def _estimators(columns: list[str], seed: int) -> dict[str, Pipeline]:
    return {
        "LinearRegression": _pipeline(columns, LinearRegression(), True),
        "Ridge": _pipeline(columns, Ridge(alpha=1.0), True),
        "RandomForestRegressor": _pipeline(
            columns, RandomForestRegressor(random_state=seed, n_jobs=1), False
        ),
        "GradientBoostingRegressor": _pipeline(
            columns, GradientBoostingRegressor(random_state=seed), False
        ),
        "DummyRegressor": _pipeline(columns, DummyRegressor(strategy="mean"), False),
    }


def _scores(y_true, y_pred) -> dict[str, float]:
    return {
        "mae": float(mean_absolute_error(y_true, y_pred)),
        "rmse": float(np.sqrt(mean_squared_error(y_true, y_pred))),
        "r2": float(r2_score(y_true, y_pred)),
    }


def _json_ready_interpretation(pipeline: Pipeline, columns: list[str]) -> dict:
    model = pipeline.named_steps["model"]
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
    if hasattr(model, "feature_importances_"):
        importances = np.asarray(model.feature_importances_, dtype=float)
        return {
            "kind": "impurity_importance",
            "terms": [
                {"feature": column, "importance": float(value)}
                for column, value in zip(columns, importances, strict=True)
            ],
        }
    return {"kind": "dummy_mean", "constant": float(getattr(model, "constant_", [None])[0])}


def train_regression(
    predictors: pd.DataFrame,
    target: pd.Series,
    columns: list[str],
    *,
    seed: int = M42_SEED,
    n_splits: int = M42_N_SPLITS,
    n_repeats: int = M42_N_REPEATS,
    test_size: float = M42_TEST_SIZE,
) -> dict:
    forbidden = [
        column
        for column in predictors.columns
        if str(column).startswith("EVS") or "Employability" in str(column) or str(column).startswith("E")
    ]
    if forbidden:
        raise ValueError(f"Target or UCC E columns leaked into predictors: {forbidden}.")
    if list(predictors.columns) != list(columns):
        raise ValueError(f"Expected columns {columns}, got {list(predictors.columns)}.")
    x_train, x_test, y_train, y_test = employability_train_test_split(
        predictors, target, seed=seed, test_size=test_size
    )
    cv = RepeatedKFold(n_splits=n_splits, n_repeats=n_repeats, random_state=seed)
    scoring = {
        "mae": "neg_mean_absolute_error",
        "rmse": "neg_root_mean_squared_error",
        "r2": "r2",
    }
    rows = []
    fitted = {}
    for name, pipeline in _estimators(columns, seed).items():
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
        rmse = -result["test_rmse"]
        train_rmse = -result["train_rmse"]
        estimator = clone(pipeline)
        estimator.fit(x_train, y_train)
        row = {
            "algorithm": name,
            "selectable": name != "DummyRegressor",
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
            "holdout": _scores(y_test, estimator.predict(x_test)),
            "train_resubstitution": _scores(y_train, estimator.predict(x_train)),
        }
        rows.append(row)
        fitted[name] = estimator
    decision = select_candidate(
        rows,
        "cv_rmse_mean",
        higher=False,
        margin=RMSE_SELECTION_MARGIN,
        preference=PREFERENCE,
    )
    selected = fitted[decision["algorithm"]]
    return {
        "columns": columns,
        "rows": rows,
        "decision": decision,
        "pipeline": selected,
        "interpretation": _json_ready_interpretation(selected, columns),
        "split": {
            "seed": seed,
            "test_size": test_size,
            "n_splits": n_splits,
            "n_repeats": n_repeats,
            "train_rows": int(len(x_train)),
            "test_rows": int(len(x_test)),
        },
        "target": EMPLOYABILITY_TARGET,
        "holdout_predictions": {
            "y_true": y_test.to_numpy(dtype=float).tolist(),
            "y_pred": selected.predict(x_test).astype(float).tolist(),
        },
    }


def build_predictor_frame(verified: dict, columns: list[str]) -> pd.DataFrame:
    frame = verified["predictors"].copy()
    if "InternshipMean" in columns:
        source = verified.get("internship")
        if source is None:
            raise ValueError("InternshipMean was requested but not supplied from source items.")
        frame = frame.copy()
        frame["InternshipMean"] = source
    return frame.loc[:, columns]


def internship_mean_from_source(employability_frame: pd.DataFrame) -> pd.Series:
    return employability_frame.loc[:, list(INTERNSHIP_ITEMS)].mean(axis=1)
