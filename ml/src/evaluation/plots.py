"""Charts drawn only from measured evaluation outputs."""

from __future__ import annotations

from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np


def _save(figure, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    figure.tight_layout()
    figure.savefig(path, dpi=120)
    plt.close(figure)


def regression_bars(rows: list[dict], path: Path) -> None:
    names = [row["algorithm"].replace(" ", "\n") for row in rows]
    values = [row["cv_rmse_mean"] for row in rows]
    errors = [row["cv_rmse_std"] for row in rows]
    figure, axis = plt.subplots(figsize=(8, 4.5))
    axis.bar(range(len(names)), values, yerr=errors, color="#1B5E20", ecolor="#666666", capsize=4)
    axis.set_xticks(range(len(names)), names, fontsize=8)
    axis.set_ylabel("Repeated CV RMSE (native 1–5 score)")
    axis.set_title("Employability algorithm comparison")
    _save(figure, path)


def regression_scatter(y_true, y_pred, path: Path) -> None:
    figure, axis = plt.subplots(figsize=(5, 5))
    axis.scatter(y_true, y_pred, color="#1B5E20", alpha=0.75)
    low = min(float(np.min(y_true)), float(np.min(y_pred)), 1.0)
    high = max(float(np.max(y_true)), float(np.max(y_pred)), 5.0)
    axis.plot([low, high], [low, high], color="#666666", linewidth=1)
    axis.set_xlabel("Observed employability mean")
    axis.set_ylabel("Predicted employability mean")
    axis.set_title("Holdout predictions, selected candidate")
    _save(figure, path)


def classification_bars(full_rows: list[dict], reduced_rows: list[dict], path: Path) -> None:
    names = [row["algorithm"] for row in full_rows]
    figure, axis = plt.subplots(figsize=(9, 4.5))
    positions = np.arange(len(names))
    width = 0.38
    axis.bar(
        positions - width / 2,
        [row["cv_f1_macro_mean"] for row in full_rows],
        width,
        yerr=[row["cv_f1_macro_std"] for row in full_rows],
        label="Exploratory full feature set",
        color="#1B5E20",
        capsize=3,
    )
    reduced_by_name = {row["algorithm"]: row for row in reduced_rows}
    axis.bar(
        positions + width / 2,
        [reduced_by_name[name]["cv_f1_macro_mean"] for name in names],
        width,
        yerr=[reduced_by_name[name]["cv_f1_macro_std"] for name in names],
        label="Reduced scholarship feature",
        color="#A5D6A7",
        capsize=3,
    )
    axis.set_xticks(positions, [name.replace(" ", "\n") for name in names], fontsize=8)
    axis.set_ylabel("Repeated CV macro F1")
    axis.set_ylim(0, 1)
    axis.legend(fontsize=8)
    axis.set_title("Dropout algorithm comparison")
    _save(figure, path)


def confusion(matrix: list[list[int]], labels: list[str], title: str, path: Path) -> None:
    values = np.asarray(matrix)
    figure, axis = plt.subplots(figsize=(5.2, 4.4))
    image = axis.imshow(values, cmap="Greens")
    axis.set_xticks(range(len(labels)), labels, rotation=20, ha="right")
    axis.set_yticks(range(len(labels)), labels)
    axis.set_xlabel("Predicted")
    axis.set_ylabel("Observed")
    axis.set_title(title)
    for row in range(values.shape[0]):
        for column in range(values.shape[1]):
            axis.text(column, row, str(int(values[row, column])), ha="center", va="center")
    figure.colorbar(image, ax=axis, fraction=0.046)
    _save(figure, path)


def calibration(curve: dict, title: str, path: Path) -> None:
    figure, axis = plt.subplots(figsize=(5, 5))
    axis.plot([0, 1], [0, 1], color="#666666", linewidth=1)
    predicted = curve.get("mean_predicted") or []
    observed = curve.get("fraction_positive") or []
    if predicted:
        axis.plot(predicted, observed, marker="o", color="#1B5E20")
    axis.set_xlabel("Mean predicted P(Dropout)")
    axis.set_ylabel("Observed dropout fraction")
    axis.set_title(title)
    axis.set_xlim(0, 1)
    axis.set_ylim(0, 1)
    _save(figure, path)
