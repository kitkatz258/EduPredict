"""Write the M25 reports from measured results."""

from __future__ import annotations

import json
from pathlib import Path

from src.features.catalog import employability_manifest, dropout_manifest
from src.features.ucc import SPEC_SECTIONS, source_to_ucc_mapping


def _num(value, digits=4) -> str:
    if value is None:
        return "n/a"
    return f"{float(value):.{digits}f}"


def _table(headers: list[str], rows: list[list[str]]) -> str:
    lines = ["| " + " | ".join(headers) + " |", "| " + " | ".join("---" for _ in headers) + " |"]
    for row in rows:
        lines.append("| " + " | ".join(row) + " |")
    return "\n".join(lines)


def _manifest_table(rows: list[dict]) -> str:
    return _table(
        ["Feature", "Role", "Decision", "Meaning", "Leakage", "Missing data", "Fairness", "Inference", "UCC mapping"],
        [
            [
                row["name"],
                row["role"],
                row["decision"],
                row["meaning"],
                row["leakage"],
                row["missing"],
                row["fairness"],
                row["inference"],
                row["mapping"],
            ]
            for row in rows
        ],
    )


def _regression_table(rows: list[dict]) -> str:
    return _table(
        ["Algorithm", "CV MAE", "CV RMSE", "CV R²", "Train RMSE", "RMSE gap", "Holdout MAE", "Holdout RMSE", "Holdout R²"],
        [
            [
                row["algorithm"],
                f"{_num(row['cv_mae_mean'])} ± {_num(row['cv_mae_std'])}",
                f"{_num(row['cv_rmse_mean'])} ± {_num(row['cv_rmse_std'])}",
                f"{_num(row['cv_r2_mean'])} ± {_num(row['cv_r2_std'])}",
                _num(row["cv_train_rmse_mean"]),
                _num(row["rmse_generalization_gap"]),
                _num(row["holdout"]["mae"]),
                _num(row["holdout"]["rmse"]),
                _num(row["holdout"]["r2"]),
            ]
            for row in rows
        ],
    )


def _classification_table(rows: list[dict]) -> str:
    rendered = []
    for row in rows:
        holdout = row.get("holdout")
        rendered.append(
            [
                row["algorithm"],
                f"{_num(row['cv_f1_macro_mean'])} ± {_num(row['cv_f1_macro_std'])}",
                f"{_num(row['cv_f1_weighted_mean'])} ± {_num(row['cv_f1_weighted_std'])}",
                f"{_num(row['cv_accuracy_mean'])} ± {_num(row['cv_accuracy_std'])}",
                f"{_num(row['cv_roc_auc_ovr_mean'])} ± {_num(row['cv_roc_auc_ovr_std'])}",
                _num(row["cv_train_f1_macro_mean"]),
                _num(row["f1_generalization_gap"]),
                _num(holdout["f1_macro"] if holdout else None),
                _num(holdout["dropout_false_negatives"] if holdout else None, 0),
                _num(holdout["roc_auc_dropout_vs_rest"] if holdout else None),
                _num(holdout["brier_dropout"] if holdout else None),
            ]
        )
    return _table(
        [
            "Algorithm",
            "CV macro F1",
            "CV weighted F1",
            "CV accuracy",
            "CV ROC-AUC OvR",
            "Train macro F1",
            "F1 gap",
            "Holdout macro F1",
            "Holdout dropout FN",
            "Holdout dropout ROC-AUC",
            "Holdout Brier P(Dropout)",
        ],
        rendered,
    )


def _per_class(block: dict) -> str:
    return _table(
        ["Class", "Precision", "Recall", "F1", "Support"],
        [
            [
                label,
                _num(values["precision"]),
                _num(values["recall"]),
                _num(values["f1"]),
                str(values["support"]),
            ]
            for label, values in block["per_class"].items()
        ],
    )


def _confusion(block: dict) -> str:
    headers = ["Observed \\ Predicted", *block["labels"]]
    rows = []
    for label, counts in zip(block["labels"], block["confusion_matrix"], strict=True):
        rows.append([label, *[str(count) for count in counts]])
    return _table(headers, rows)


def write_reports(bundle: dict, directory: Path) -> None:
    directory.mkdir(parents=True, exist_ok=True)
    (directory / "employability_data_audit.md").write_text(_employability_audit(bundle), encoding="utf-8")
    (directory / "dropout_data_audit.md").write_text(_dropout_audit(bundle), encoding="utf-8")
    (directory / "employability_feature_manifest.md").write_text(_employability_features(bundle), encoding="utf-8")
    (directory / "dropout_feature_manifest.md").write_text(_dropout_features(bundle), encoding="utf-8")
    (directory / "employability_model_comparison.md").write_text(_employability_models(bundle), encoding="utf-8")
    (directory / "dropout_model_comparison.md").write_text(_dropout_models(bundle), encoding="utf-8")
    (directory / "training_summary.md").write_text(_summary(bundle), encoding="utf-8")
    (directory / "metrics.json").write_text(
        json.dumps(_public_metrics(bundle), indent=2, sort_keys=True),
        encoding="utf-8",
    )


def _public_metrics(bundle: dict) -> dict:
    employability = bundle["employability"]
    dropout = bundle["dropout"]
    return {
        "generated_at": bundle["generated_at"],
        "library_versions": bundle["versions"],
        "seed": bundle["seed"],
        "employability": {
            "split": employability["split"],
            "decision": employability["decision"],
            "beats_mean_baseline_on_cv_rmse": employability["beats_mean_baseline_on_cv_rmse"],
            "rows": [
                {key: value for key, value in row.items() if key != "selectable"}
                for row in employability["rows"]
            ],
            "interpretation": employability["interpretation"],
            "reproducibility_match": bundle["reproducibility"]["employability"],
        },
        "dropout": {
            "exploratory_decision": dropout["exploratory"]["decision"],
            "reduced_decision": dropout["reduced"]["decision"],
            "exploratory_rows": dropout["exploratory"]["rows"],
            "reduced_rows": dropout["reduced"]["rows"],
            "timing": dropout["timing"],
            "reproducibility_match": bundle["reproducibility"]["dropout_reduced"],
        },
    }


def _employability_audit(bundle: dict) -> str:
    files = bundle["files"]["employability"]
    verification = bundle["verification"]["employability"]
    archive = bundle["archive"]
    file_rows = [
        [record["path"], str(record["bytes"]), record["sha256"]]
        for record in files
    ]
    archive_rows = []
    for entry in archive["files"]:
        comparison = entry.get("loose_comparison") or {}
        archive_rows.append(
            [
                entry.get("name", ""),
                str(entry.get("unpacked_bytes", "")),
                "" if entry.get("crc32") is None else f"{entry['crc32']:08x}",
                comparison.get("loose_name", ""),
                str(comparison.get("same_size", "")),
                str(comparison.get("same_crc32", "")),
            ]
        )
    check_rows = [
        [
            check["stored_column"],
            check["derived_name"],
            str(len(check["items"])),
            f"{check['max_absolute_difference']:.1e}",
            _num(check["min"]),
            _num(check["max"]),
        ]
        for check in verification["checks"]
    ]
    return f"""# Employability data audit

Research audit of the local Pianda workbook. This file does not change the source workbook.

## Provenance

- Citation: Pianda, D., Hilmiana, & Widianto, S. (2026). *Dataset of student internship experience, competence, psychological capital and employability's vocational student* (Version 4) [Dataset]. Mendeley Data. https://doi.org/10.17632/hmn3b4c6c4.4
- Associated article used for the sampling cross-check: Pianda, D., Hilmiana, Widianto, S., & Sartika, D. (2025). The influence employability of vocational students through internship experiences and 21st-century competencies: a moderated mediation model. *Cogent Education*. https://doi.org/10.1080/2331186X.2025.2476285
- Workbook core properties: creator IBM SPSS Export Facility; last modified by teukudidi pianda1; modified timestamp 2026-06-14T09:02:46Z.
- Questionnaire core properties: creator ASUS; last modified by teukudidi pianda1; modified timestamp 2026-06-11T13:15:00Z.
- License: the retrieved Mendeley Version 4 page did not display a license. No license was inferred.
- Population stated by the record: 266 vocational students in Aceh Province who had internship experience, surveyed August–December 2022.
- Local row count: {verification["rows"]}. Missing cells: {verification["missing_cells"]}. Duplicate rows: {verification["duplicate_rows"]}.

{_table(["File", "Bytes", "SHA-256"], file_rows)}

## Archive check

`Dataset.rar` is a RAR5 archive. Members were listed in Python and compared by unpacked size and CRC-32. No member was extracted into the training table.

{_table(["Archive member", "Unpacked bytes", "CRC-32", "Loose file", "Same size", "Same CRC-32"], archive_rows)}

The loose questionnaire matches the archive member byte for byte. The loose workbook has the same unpacked size as `Dataset/Raw Dataset (Excel).xlsx` and a different CRC-32, so the archived workbook is not byte-identical. Training uses only the loose `Raw Dataset.xlsx` that was audited sheet by sheet.

## Scoring verification

The codebook and the bilingual questionnaire both use a 1–5 Likert scale from strongly disagree to strongly agree. Neither document states a reverse-scored item. Every item in the sheet is an integer in {{1, 2, 3, 4, 5}}.

Each stored `AVERAGE` column equals the unweighted arithmetic mean of its items. The maximum absolute difference is below 1e-9, so the published means are accepted.

{_table(["Stored column", "Derived name", "Items", "Max abs diff", "Min", "Max"], check_rows)}

Target definition: `EmployabilityMean`, the unweighted mean of EVS1–EVS12. Observed range {_num(verification["target_min"])} to {_num(verification["target_max"])}, mean {_num(verification["target_mean"])}, sample standard deviation {_num(verification["target_std"])}. The range lies inside 1–5, so the display rescale `((score - 1) / 4) * 100` is defined. That rescale is an Employability Readiness index, not a probability of employment. Model metrics below are on the native 1–5 mean.

## Instrument notes that were not rewritten

- The questionnaire midpoint is printed in Indonesian as "Tidak setuju maupun tidak menolak" and in English as "Neither Agree nor Disagree".
- The codebook labels school status `1 = country` and `2 = private`. The value was not recoded to public or negeri.
- Codebook subscale labels include the spellings "Sosial Capital" and "Envirionmental Monitoring". Subscale groups follow the codebook cell layout and are not model inputs.
- PsyCap4 refers to learning during the pandemic.
- Mendeley Version 4 says the sample was drawn by simple random sampling. The 2025 article, section 3.1, says random cluster sampling of 266 students in 17 institutions from 15,435 Class XII students in 220 schools. Both descriptions are recorded; neither was edited.
- The students are SMK (vocational secondary) students aged about 16–18 in the codebook, not UCC undergraduates.
- Respondent codes and signatures are on the questionnaire form and are not columns in the workbook.

## Approved UCC PDFs

{bundle["ucc_status_text"]}
"""


def _dropout_audit(bundle: dict) -> str:
    record = bundle["files"]["dropout"]
    quirks = bundle["verification"]["dropout_quirks"]
    counts = bundle["verification"]["dropout_counts"]
    return f"""# Dropout data audit

Research audit of the local UCI file. The raw file was not modified.

## Provenance

- Citation: Realinho, V., Vieira Martins, M., Machado, J., & Baptista, L. (2021). *Predict Students' Dropout and Academic Success* [Dataset]. UCI Machine Learning Repository. https://doi.org/10.24432/C5MC89
- Introductory paper: Martins, M. V., Tolledo, D., Machado, J., Baptista, L. M. T., & Realinho, V. (2021). Early prediction of student's performance in higher education: a case study. https://doi.org/10.1007/978-3-030-72657-7_16
- Data article used for the attribute grouping and the renumbering discrepancy: Martins et al., *Data* 2022, 7(11), 146. https://doi.org/10.3390/data7110146
- UCI record: id 697, 4424 instances, 36 features, donated 12 December 2021, target column `Target`, missing values reported as none.
- License: the UCI API payload retrieved for id 697 did not contain a license field. No license was inferred. The local file was not replaced with a fresh download.
- UCI lists `data.csv` at 520.7 KB. The local file is {record["bytes"]} bytes, SHA-256 `{record["sha256"]}`.
- Recommended source split recorded by UCI: 80% train and 20% test. This experiment uses that split and does not use the test split for selection.

## File verification

- Rows: 4424. Columns after cleanup: 37 (36 predictors + Target).
- Empty cells: none. Duplicate rows: none.
- Target counts: Dropout {counts["Dropout"]}, Enrolled {counts["Enrolled"]}, Graduate {counts["Graduate"]}.
- Header quirks preserved in the raw file and removed only in memory: UTF-8 BOM on the first column name = {quirks["utf8_bom_on_first_column"]}; columns with trailing whitespace = {quirks["trailing_tab_columns"]}.
- `Scholarship holder` takes only the values 0 and 1.
- Course codes in this file are the original UCI codes (33, 171, 8014, and so on). Application-mode codes are likewise the original non-consecutive codes. The MDPI data article's tables renumber several of those fields to 1…K and report different means. This experiment one-hot encodes the codes that are in the file. It does not apply the article's renumbering.
- Documented grade scales, confirmed against observed ranges rather than converted: previous qualification and admission grades are on 0–200; curricular-unit grade averages are on 0–20. No Philippine GWA conversion is defined.
- The UCI description of `Curricular units 2nd sem (without evaluations)` says "1st semester". The column name says 2nd semester. The column was kept under its header and not relabeled.

## Target

The target remains the three source labels: Dropout, Enrolled, and Graduate. P(Dropout) is the predicted probability of the Dropout class. No Low, Moderate, or High cut point is estimated.
"""


def _employability_features(bundle: dict) -> str:
    mapping = source_to_ucc_mapping()
    sections = _table(
        ["Codes", "Section", "Role in this milestone"],
        [[row["codes"], row["section"], row["role"]] for row in SPEC_SECTIONS],
    )
    crosswalk = _table(
        ["Source", "UCC specification", "Status", "Evidence"],
        [[row["source"], row["ucc"], row["status"], row["evidence"]] for row in mapping],
    )
    kept = ", ".join(row["name"] for row in employability_manifest() if row["decision"] == "KEEP")
    return f"""# Employability feature manifest

v1 predictors kept for training: {kept}.

Target items and the target mean are excluded. Internship items are excluded from v1 and were not used in a separate model. Demographic codes are excluded. UCC section means are not substituted for these predictors.

## Specification sections

The six section codes below are taken from the M25 specification because the approved PDFs are not in the workspace. They are not a verified transcription of those PDFs.

{sections}

## Source-to-UCC crosswalk

{crosswalk}

Item-level pairing was not created. Measurement equivalence is not claimed. Panel approval of the UCC questionnaire, as stated for this milestone, is not psychometric validation and is not external model validity.

## Every audited column

{_manifest_table(employability_manifest())}
"""


def _dropout_features(bundle: dict) -> str:
    rows = dropout_manifest()
    kept = [row["name"] for row in rows if row["decision"] == "KEEP"]
    review = [row["name"] for row in rows if row["decision"] == "REVIEW"]
    return f"""# Dropout feature manifest

Deployment-compatible candidate inputs (KEEP): {", ".join(kept)}.

REVIEW, not used in the saved candidate: {", ".join(review) if review else "none"}.

The exploratory reference model uses all 36 source predictors, including fields marked EXCLUDE for deployment. Those exclusions mean "do not put this field in the EduPredict candidate", not "delete it from the UCI reference model".

Curricular grades stay on the Portuguese 0–20 scale inside the exploratory model. Admission and previous-qualification grades stay on the 0–200 scale. No UCC conversion is applied.

A, S, B, C, P, and E questionnaire values are not written into any UCI column.

Inference timing used for the sensitivity check:

- At enrollment: all predictors except the twelve curricular-unit columns. Debtor and tuition remain in this cut because the source groups them with socioeconomic data, while their snapshot date is still unverified.
- After the first semester: enrollment features plus first-semester curricular columns. Second-semester columns are removed.
- Full reference: all 36 predictors, including second-semester results. This is late information and is not the saved candidate.

{_manifest_table(rows)}
"""


def _employability_models(bundle: dict) -> str:
    result = bundle["employability"]
    decision = result["decision"]
    interpretation = result["interpretation"]
    terms = _table(
        ["Term", "Value"],
        [[term.get("feature", ""), _num(term.get("coefficient", term.get("importance")), 6)] for term in interpretation["terms"]],
    )
    return f"""# Employability model comparison

Native target: EmployabilityMean, the verified EVS item mean on the 1–5 scale.

Predictors: CompetenceMean and PsyCapMean only.

Protocol, fixed before scores were read:

- Seed {result["split"]["seed"]}.
- Holdout {result["split"]["test_rows"]} rows ({result["split"]["test_size"]:.0%}); training partition {result["split"]["train_rows"]} rows.
- RepeatedKFold, {result["split"]["n_splits"]} splits × {result["split"]["n_repeats"]} repeats, on the training partition only.
- Linear and ridge models standardize the two means inside the pipeline. Tree models do not.
- Hyperparameters are library defaults except `Ridge(alpha=1.0)`, which is the library default, and `random_state` on the tree models.
- Selection metric: lower mean CV RMSE. If another selectable model is within {decision["margin"]} RMSE, the earlier model in this order is kept: {", ".join(decision["preference"])}.
- The mean baseline is reported and is not selectable.
- Holdout columns are confirmatory. They were not used to choose the algorithm.

## Measured results

{_regression_table(result["rows"])}

Selected research candidate: **{decision["algorithm"]}**. Contenders inside the margin: {", ".join(decision["contenders"])}.

Mean-baseline comparison on CV RMSE: the selected model {"has a lower" if result["beats_mean_baseline_on_cv_rmse"] else "does not have a lower"} mean CV RMSE than predicting the training-partition mean.

RMSE gap is validation RMSE minus training-fold RMSE. A positive gap means the training folds fit more closely than the validation folds.

## Interpretation of the saved training-partition fit

Kind: {interpretation["kind"]}.

{terms}

{"Intercept: " + _num(interpretation.get("intercept"), 6) if "intercept" in interpretation else ""}

The saved pipeline was fit on the training partition only. Refitting it on all 266 rows would make the stored holdout numbers refer to a different model.

With about {result["split"]["train_rows"]} training rows and a {result["split"]["test_rows"]}-row holdout, fold and holdout metrics are uncertain. The two predictors and the target are same-occasion self-reports, so a high R² would still not establish employment prediction or UCC validity.

Figure: `figures/employability_rmse.png`. Holdout scatter: `figures/employability_holdout_scatter.png`.

Reproducibility rerun of this comparison matched: {bundle["reproducibility"]["employability"]}.
"""


def _dropout_models(bundle: dict) -> str:
    dropout = bundle["dropout"]
    exploratory = dropout["exploratory"]
    reduced = dropout["reduced"]
    selected_full = next(row for row in exploratory["rows"] if row["algorithm"] == exploratory["decision"]["algorithm"])
    selected_reduced = next(row for row in reduced["rows"] if row["algorithm"] == reduced["decision"]["algorithm"])
    timing_rows = [
        [
            level,
            str(values["n_columns"]),
            values["algorithm"],
            f"{_num(values['cv_f1_macro_mean'])} ± {_num(values['cv_f1_macro_std'])}",
            _num(values["cv_f1_weighted_mean"]),
            _num(values["cv_accuracy_mean"]),
        ]
        for level, values in dropout["timing"].items()
    ]
    full_cv = next(row for row in exploratory["rows"] if row["algorithm"] == exploratory["decision"]["algorithm"])
    timing_rows.insert(
        0,
        [
            "full",
            "36",
            full_cv["algorithm"],
            f"{_num(full_cv['cv_f1_macro_mean'])} ± {_num(full_cv['cv_f1_macro_std'])}",
            _num(full_cv["cv_f1_weighted_mean"]),
            _num(full_cv["cv_accuracy_mean"]),
        ],
    )
    return f"""# Dropout model comparison

Target labels: Dropout, Enrolled, Graduate. Accuracy is secondary. No risk band is derived from P(Dropout).

Protocol, fixed before scores were read:

- Seed {exploratory["split"]["seed"]}.
- Stratified holdout at {exploratory["split"]["test_size"]:.0%}. Training rows {exploratory["split"]["train_rows"]}; holdout rows {exploratory["split"]["test_rows"]}.
- RepeatedStratifiedKFold, {exploratory["split"]["n_splits"]} × {exploratory["split"]["n_repeats"]}, on the training partition.
- Nominal source codes are one-hot encoded inside the pipeline. Numeric and binary fields are standardized only for logistic regression.
- Class imbalance: logistic regression and random forest use `class_weight="balanced"`. Gradient boosting uses balanced sample weights because that estimator has no `class_weight`.
- Other hyperparameters remain library defaults, with `random_state` fixed and logistic `max_iter=2000`.
- Selection metric for each feature set: higher mean CV macro F1. Within {reduced["decision"]["margin"]} macro F1, the earlier algorithm wins: {", ".join(reduced["decision"]["preference"])}.
- The majority-class baseline is not selectable.
- Holdout metrics did not choose the algorithm.
- The saved candidate is the reduced-feature winner. The full-feature winner is an exploratory reference and is not saved for deployment.

## A. Exploratory full-feature reference

{_classification_table(exploratory["rows"])}

Reference algorithm by the CV rule: **{exploratory["decision"]["algorithm"]}**. Contenders: {", ".join(exploratory["decision"]["contenders"])}.

### Confirmatory holdout, full reference

{_per_class(selected_full["holdout"])}

{_confusion(selected_full["holdout"])}

Dropout false negatives on the holdout: {selected_full["holdout"]["dropout_false_negatives"]} of the observed Dropout rows (rate {_num(selected_full["holdout"]["dropout_false_negative_rate"])}).

P(Dropout) versus the rest, holdout ROC-AUC {_num(selected_full["holdout"]["roc_auc_dropout_vs_rest"])}. Multiclass OvR macro ROC-AUC {_num(selected_full["holdout"]["roc_auc_ovr_macro"])}. Brier score for P(Dropout) {_num(selected_full["holdout"]["brier_dropout"])}.

### Out-of-fold training partition, same reference algorithm

Single stratified 5-fold, separate from the repeated CV used for selection.

{_per_class(exploratory["details"]["oof_training_partition"])}

{_confusion(exploratory["details"]["oof_training_partition"])}

## B. Reduced candidate

The only verified EduPredict-compatible predictor in this file is `Scholarship holder`.

{_classification_table(reduced["rows"])}

Saved research candidate: **{reduced["decision"]["algorithm"]}**. Contenders: {", ".join(reduced["decision"]["contenders"])}.

### Confirmatory holdout, reduced candidate

{_per_class(selected_reduced["holdout"])}

{_confusion(selected_reduced["holdout"])}

Dropout false negatives on the holdout: {selected_reduced["holdout"]["dropout_false_negatives"]} (rate {_num(selected_reduced["holdout"]["dropout_false_negative_rate"])}).

P(Dropout) ROC-AUC {_num(selected_reduced["holdout"]["roc_auc_dropout_vs_rest"])}. OvR macro ROC-AUC {_num(selected_reduced["holdout"]["roc_auc_ovr_macro"])}. Brier score {_num(selected_reduced["holdout"]["brier_dropout"])}.

### Out-of-fold training partition, reduced candidate

{_per_class(reduced["details"]["oof_training_partition"])}

{_confusion(reduced["details"]["oof_training_partition"])}

## Timing sensitivity

Same repeated CV protocol. The full row is the exploratory winner. The other rows score that same algorithm after future curricular columns are removed. They were not used to choose the saved candidate.

{_table(["Timing", "Predictors", "Algorithm", "CV macro F1", "CV weighted F1", "CV accuracy"], timing_rows)}

Figures: `figures/dropout_macro_f1.png`, `figures/dropout_full_confusion.png`, `figures/dropout_reduced_confusion.png`, `figures/dropout_full_calibration.png`, `figures/dropout_reduced_calibration.png`.

Reproducibility rerun of the reduced comparison matched: {bundle["reproducibility"]["dropout_reduced"]}.
"""


def _summary(bundle: dict) -> str:
    employability = bundle["employability"]
    dropout = bundle["dropout"]
    return f"""# M25 training summary

Generated: {bundle["generated_at"]}
Seed: {bundle["seed"]}
Library versions: {json.dumps(bundle["versions"], sort_keys=True)}

These artifacts are research candidates. They are not production-validated. Panel approval of the UCC questionnaire is not evidence that pilot reliability, construct equivalence, or external validity has been completed.

## What was trained

- Employability v1: {employability["decision"]["algorithm"]} predicting the native EVS mean from CompetenceMean and PsyCapMean.
- Dropout candidate: {dropout["reduced"]["decision"]["algorithm"]} using only Scholarship holder.
- Dropout exploratory reference, not saved: {dropout["exploratory"]["decision"]["algorithm"]} using all 36 UCI predictors.

The employability model {"beat" if employability["beats_mean_baseline_on_cv_rmse"] else "did not beat"} the mean baseline on mean CV RMSE.

## Blockers

- Approved PDFs missing: {", ".join(bundle["ucc"]["missing"]) or "none"}. Item-level UCC mapping and UCC section scoring were stopped.
- UCC-to-Portuguese grade transformation: unresolved. Curricular and admission grades are excluded from the dropout candidate.
- Age at enrollment: birthdate exists on the institution list and is nullable; the current feature contract does not emit it. Left as REVIEW.
- Archived employability workbook CRC does not match the loose workbook. The archive was not merged.
- Pianda sample is vocational secondary students in Aceh, observed with a same-occasion self-report employability scale. It is not a UCC employment outcome.
- Sampling description disagrees between the Mendeley record (simple random) and the 2025 article (cluster sample of 17 schools).

## Display rule for a later readiness index

When a native prediction is inside [1, 5], readiness = ((prediction - 1) / 4) * 100. The function does not round. It is not a probability, and it is not a Low/Moderate/High rule. Thresholds from the unread consultation draft were not implemented.

## Reproducibility

- Employability comparison rerun matched: {bundle["reproducibility"]["employability"]}
- Reduced dropout comparison rerun matched: {bundle["reproducibility"]["dropout_reduced"]}
"""
