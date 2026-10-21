"""M25 split definitions. The arguments are part of the published partition."""

from __future__ import annotations

from sklearn.model_selection import train_test_split


def dropout_train_test_split(predictors, target, *, seed: int, test_size: float):
    """Stratify on the three-class UCI target. Do not stratify on a binary recode."""
    return train_test_split(
        predictors,
        target,
        test_size=test_size,
        random_state=seed,
        shuffle=True,
        stratify=target,
    )


def employability_train_test_split(predictors, target, *, seed: int, test_size: float):
    """M25 employability partition. It is not stratified."""
    return train_test_split(
        predictors,
        target,
        test_size=test_size,
        random_state=seed,
        shuffle=True,
    )
