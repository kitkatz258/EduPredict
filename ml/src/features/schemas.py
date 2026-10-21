"""Schema checks that keep targets and questionnaire context out of predictors."""

from __future__ import annotations

import pandas as pd
from sklearn.base import BaseEstimator, TransformerMixin

from src.common import LIKERT_MAX, LIKERT_MIN


class FrameSchema(BaseEstimator, TransformerMixin):
    """Require an exact column set and a named value rule. Extra columns are rejected."""

    def __init__(self, columns: list[str] | tuple[str, ...], value_rule: str = "any"):
        self.columns = columns
        self.value_rule = value_rule

    def fit(self, X, y=None):
        frame = self._frame(X)
        self.n_features_in_ = frame.shape[1]
        return self

    def transform(self, X):
        frame = self._frame(X)
        return frame.loc[:, self.columns].copy()

    def _frame(self, X) -> pd.DataFrame:
        if not isinstance(X, pd.DataFrame):
            raise TypeError("Predictors must be a pandas DataFrame with named columns.")
        expected = [str(column) for column in self.columns]
        actual = list(X.columns)
        missing = [column for column in expected if column not in actual]
        extra = [column for column in actual if column not in expected]
        if missing or extra:
            raise ValueError(
                "Predictor schema mismatch. "
                f"Missing: {missing or 'none'}. Extra: {extra or 'none'}."
            )
        frame = X.loc[:, expected]
        if frame.isna().any().any():
            raise ValueError("Missing predictor values are not imputed.")
        self._check_values(frame)
        return frame

    def _check_values(self, frame: pd.DataFrame) -> None:
        if self.value_rule == "any":
            return
        if self.value_rule == "likert_mean":
            numeric = frame.apply(pd.to_numeric, errors="coerce")
            if numeric.isna().any().any():
                raise ValueError("Likert means must be numeric.")
            outside = (numeric < LIKERT_MIN) | (numeric > LIKERT_MAX)
            if outside.any().any():
                raise ValueError(
                    f"Likert means must stay inside [{LIKERT_MIN}, {LIKERT_MAX}]."
                )
            return
        if self.value_rule == "binary01":
            numeric = frame.apply(pd.to_numeric, errors="coerce")
            if numeric.isna().any().any():
                raise ValueError("Binary predictors must be numeric 0 or 1.")
            allowed = numeric.isin([0, 1])
            if not allowed.all().all():
                raise ValueError("Binary predictors must be exactly 0 or 1.")
            return
        raise ValueError(f"Unknown value rule: {self.value_rule}")
