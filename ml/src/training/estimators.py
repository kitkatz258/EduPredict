"""Estimators whose imbalance handling is fixed before any score is read."""

from __future__ import annotations

import numpy as np
from sklearn.ensemble import GradientBoostingClassifier
from sklearn.utils.class_weight import compute_sample_weight


class BalancedGradientBoostingClassifier(GradientBoostingClassifier):
    """Gradient boosting with balanced sample weights. sklearn's GB classifier has no class_weight."""

    def fit(self, X, y, sample_weight=None):
        balanced = compute_sample_weight(class_weight="balanced", y=y)
        if sample_weight is not None:
            balanced = np.asarray(sample_weight, dtype=float) * balanced
        return super().fit(X, y, sample_weight=balanced)
