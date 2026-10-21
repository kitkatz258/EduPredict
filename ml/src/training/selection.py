"""Pre-specified model selection. Holdout scores are not an input."""

from __future__ import annotations


def select_candidate(rows: list[dict], metric: str, *, higher: bool, margin: float, preference: list[str]) -> dict:
    selectable = [row for row in rows if row["selectable"]]
    if not selectable:
        raise ValueError("No selectable candidate was evaluated.")
    if higher:
        best_value = max(row[metric] for row in selectable)
        contenders = [row for row in selectable if best_value - row[metric] <= margin]
    else:
        best_value = min(row[metric] for row in selectable)
        contenders = [row for row in selectable if row[metric] - best_value <= margin]
    rank = {name: index for index, name in enumerate(preference)}
    unknown = [row["algorithm"] for row in contenders if row["algorithm"] not in rank]
    if unknown:
        raise ValueError(f"Selection preference is missing {unknown}.")
    contenders.sort(key=lambda row: rank[row["algorithm"]])
    chosen = contenders[0]
    return {
        "algorithm": chosen["algorithm"],
        "metric": metric,
        "higher_is_better": higher,
        "margin": margin,
        "preference": preference,
        "contenders": [row["algorithm"] for row in contenders],
        "best_metric_value": best_value,
    }
