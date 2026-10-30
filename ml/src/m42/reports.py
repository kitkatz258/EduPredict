"""Write M42 markdown reports from measured results. Numbers are not invented."""

from __future__ import annotations

from src.m42.common import CURRENT_QUESTIONNAIRE_PDF, CURRENT_SCORING_PDF
from src.m42.compatibility import CONTRACTS, GRADE_COLUMNS
from src.m42.mapping import (
    EQUIVALENCE_NOTE,
    PIANDA_COMPETENCE,
    PIANDA_PSYCAP,
    academic_feature_rows,
    construct_summary,
    questionnaire_rows,
)
from src.questionnaire.v2_instrument import VERSION


def _interpretation_block(interpretation: dict) -> str:
    kind = interpretation.get("kind", "")
    lines = [f"Kind: `{kind}`."]
    if "intercept" in interpretation:
        lines.append(f"Intercept: {_num(interpretation['intercept'], 6)}")
    terms = interpretation.get("terms") or []
    if terms:
        value_key = "coefficient" if "coefficient" in terms[0] else "importance"
        lines.append("")
        lines.append(
            _table(
                ["Feature", value_key.title()],
                [[term["feature"], _num(term[value_key], 6)] for term in terms],
            )
        )
    return "\n".join(lines)


def _num(value, digits=4) -> str:
    if value is None:
        return "not run"
    return f"{float(value):.{digits}f}"


def _table(headers: list[str], rows: list[list[str]]) -> str:
    lines = [
        "| " + " | ".join(headers) + " |",
        "| " + " | ".join("---" for _ in headers) + " |",
    ]
    for row in rows:
        lines.append("| " + " | ".join(row) + " |")
    return "\n".join(lines)


def _regression_table(rows: list[dict]) -> str:
    return _table(
        [
            "Algorithm",
            "CV MAE",
            "CV RMSE",
            "CV R²",
            "Train RMSE",
            "RMSE gap",
            "Holdout MAE",
            "Holdout RMSE",
            "Holdout R²",
        ],
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
        holdout = row.get("holdout") or {}
        rendered.append(
            [
                row["algorithm"],
                f"{_num(row['cv_f1_macro_mean'])} ± {_num(row['cv_f1_macro_std'])}",
                f"{_num(row['cv_f1_weighted_mean'])} ± {_num(row['cv_f1_weighted_std'])}",
                f"{_num(row['cv_accuracy_mean'])} ± {_num(row['cv_accuracy_std'])}",
                f"{_num(row.get('cv_roc_auc_ovr_mean'))} ± {_num(row.get('cv_roc_auc_ovr_std'))}",
                _num(row.get("cv_train_f1_macro_mean")),
                _num(row.get("f1_generalization_gap")),
                _num(holdout.get("f1_macro")),
                _num(holdout.get("accuracy")),
                _num(holdout.get("dropout_false_negatives"), 0),
                _num(holdout.get("roc_auc_dropout_vs_rest")),
                _num(holdout.get("brier_dropout")),
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
            "Holdout accuracy",
            "Holdout dropout FN",
            "Holdout dropout ROC-AUC",
            "Holdout Brier P(Dropout)",
        ],
        rendered,
    )


def _per_class_table(holdout: dict) -> str:
    per_class = holdout.get("per_class") or {}
    return _table(
        ["Class", "Precision", "Recall", "F1", "Support"],
        [
            [
                label,
                _num(stats["precision"]),
                _num(stats["recall"]),
                _num(stats["f1"]),
                str(stats["support"]),
            ]
            for label, stats in per_class.items()
        ],
    )


def _confusion_table(holdout: dict) -> str:
    labels = holdout.get("labels") or ["Dropout", "Enrolled", "Graduate"]
    matrix = holdout.get("confusion_matrix") or []
    header = ["Observed \\ Predicted", *labels]
    rows = []
    for label, row in zip(labels, matrix):
        rows.append([label, *[str(int(value)) for value in row]])
    return _table(header, rows)


def write_data_audit(bundle: dict) -> str:
    emp = bundle["employability_audit"]
    drop = bundle["dropout_audit"]
    files = bundle["files"]
    return f"""# Data audit (M42)

Research audit of the local Pianda v4 workbook and UCI dropout file. Raw files were not modified. This file does not replace `employability_data_audit.md` or `dropout_data_audit.md`.

## Questionnaire sources used in M42

| File | Role |
| --- | --- |
| `{CURRENT_QUESTIONNAIRE_PDF}` | Exact 60-item v2 statements (`{VERSION}`). |
| `{CURRENT_SCORING_PDF}` | Updated 60-item scoring and research-basis consultation draft. |
| Historic `ucc-panel-approved-v1` (36 items) | Preserved in Laravel. Not rescored. Not used as the M42 instrument. |

The previous 36-item scoring PDF is not the scoring source for M42.

## Pianda employability (Mendeley v4)

- Citation: Pianda, D., Hilmiana, Widianto, S., & Sartika, D. (2026). Dataset of student internship experience, competence, psychological capital and employability's vocational student (Version 4) [Dataset]. Mendeley Data. https://doi.org/10.17632/hmn3b4c6c4.4
- Local workbook: `{files['employability'][0]['path']}` SHA-256 `{files['employability'][0]['sha256']}` ({files['employability'][0]['bytes']} bytes)
- Questionnaire: `{files['employability'][1]['path']}` SHA-256 `{files['employability'][1]['sha256']}`
- Archive listed, not merged: `{files['employability'][2]['path']}` SHA-256 `{files['employability'][2]['sha256']}`
- License: the retrieved Mendeley Version 4 page did not display a license. No license was inferred. Raw records are gitignored and were not committed.
- Rows: {emp['rows']}. Missing cells: {emp['missing_cells']}. Duplicate rows: {emp['duplicate_rows']}.
- Likert items: integers in {{1,2,3,4,5}}. Codebook and bilingual questionnaire state no reverse-scored item. M42 did not invent reverse codes.
- Stored AVERAGE columns match unweighted item means (max abs diff 0).
- CompetenceMean: unweighted mean of C1–C10. Observed range {emp['competence_min']:.4f}–{emp['competence_max']:.4f}.
- PsyCapMean: unweighted mean of PsyCap1–PsyCap14. Observed range {emp['psycap_min']:.4f}–{emp['psycap_max']:.4f}.
- EmployabilityMean (target): unweighted mean of EVS1–EVS12. Observed range {emp['target_min']:.4f}–{emp['target_max']:.4f}, mean {emp['target_mean']:.4f}, SD {emp['target_std']:.4f}. Native 1–5 range is verified, so a later display index `((score-1)/4)*100` is defined. That index is not a probability of employment.
- InternshipMean: unweighted mean of SIE1–SIE14. Observed range {emp['internship_min']:.4f}–{emp['internship_max']:.4f}. Optional expanded comparison only.
- Population: 266 vocational (SMK) students in Aceh with internship experience, codebook ages 16–18. Not UCC CLAS undergraduates.
- Sampling descriptions on record (not reconciled): Mendeley simple random; 2025 article cluster sample; scoring PDF Data in Brief title "stratified".

## UCI dropout

- Citation: Realinho, V., Vieira Martins, M., Machado, J., & Baptista, L. (2021). Predict Students' Dropout and Academic Success [Dataset]. UCI Machine Learning Repository. https://doi.org/10.24432/C5MC89
- Local file: `{files['dropout']['path']}` SHA-256 `{files['dropout']['sha256']}` ({files['dropout']['bytes']} bytes)
- License: the UCI API payload retrieved for id 697 did not contain a license field. No license was inferred.
- Rows: {drop['rows']}. Columns after BOM/whitespace cleanup: 37. Empty cells: 0. Duplicate rows: 0.
- Target counts: Dropout {drop['counts']['Dropout']}, Enrolled {drop['counts']['Enrolled']}, Graduate {drop['counts']['Graduate']}.
- Three-class labels are preserved. No Low/Moderate/High outcome was created.
- Header quirks in the raw file: UTF-8 BOM = {drop['quirks']['utf8_bom_on_first_column']}; trailing-tab columns = {drop['quirks']['trailing_tab_columns']}.
- Grade scales confirmed on this file: previous qualification and admission 95–190 on the documented 0–200 scale; curricular-unit grades 0–18.875 (1st) and 0–18.5714 (2nd) on 0–20. **No Philippine GWA conversion is defined. Grade columns are excluded from M42 reduced training.**
- First-semester enrolled units: min 0, max 26, median 6. Approved: min 0, max 26, median 5. Credited median 0. Evaluations median 8, max 45.
- `Curricular units 2nd sem (without evaluations)` documentation says 1st semester; the column name says 2nd. Not relabeled.

## Leakage checks

- EVS1–EVS12 and EmployabilityMean are never predictors.
- UCC E1–E10 are never predictors.
- A, S, and B means are never UCI predictors and do not change dropout probability.
- Second-semester curricular fields are labeled after-second-semester only.
- Live EduPredict student rows were not read or written.
- UCC inference is blocked. These source files are not UCC student records.
"""


def write_questionnaire_mapping() -> str:
    q_rows = questionnaire_rows()
    item_table = _table(
        [
            "Code",
            "Verbatim UCC statement",
            "UCC construct",
            "Source candidate",
            "Match",
            "Model use",
            "Reason",
            "Leakage",
            "Availability",
        ],
        [
            [
                row["code"],
                row["statement"],
                row["ucc_construct"],
                row["source_variable"] or "none",
                row["matching_status"],
                row["model_use"],
                row["reason"],
                row["leakage"],
                row["availability"],
            ]
            for row in q_rows
        ],
    )
    summary = _table(
        ["Group", "UCC items", "Source items", "Allowed role", "Restriction", "Validated", "Model use"],
        [
            [
                row["group"],
                str(row["items"]),
                str(row.get("source_items", "n/a")),
                row["allowed_research_role"],
                row["restriction"],
                "no",
                row["model_use"],
            ]
            for row in construct_summary()
        ],
    )
    academic = _table(
        ["UCC field", "UCC definition", "Source field", "Status", "Reason", "When available", "Leakage"],
        [
            [
                row["ucc_field"],
                row["ucc_definition"],
                row["source_field"],
                row["status"],
                row["reason"],
                row["availability"],
                row["leakage"],
            ]
            for row in academic_feature_rows()
        ],
    )
    pianda_c = _table(
        ["Pianda", "Codebook English statement"],
        [[code, text] for code, text in PIANDA_COMPETENCE.items()],
    )
    pianda_p = _table(
        ["Pianda", "Codebook English statement"],
        [[code, text] for code, text in PIANDA_PSYCAP.items()],
    )
    exact = sum(1 for row in q_rows if row["matching_status"] == "exact")
    conceptual = sum(1 for row in q_rows if row["matching_status"] == "conceptual")
    none = sum(1 for row in q_rows if row["matching_status"] == "none")
    return f"""# Questionnaire feature mapping (M42)

Instrument: `{VERSION}` transcribed from `{CURRENT_QUESTIONNAIRE_PDF}`. Scoring rules from `{CURRENT_SCORING_PDF}`. Historic 36-item `ucc-panel-approved-v1` is preserved and is not this mapping.

{EQUIVALENCE_NOTE}

Exact English matches: **{exact}**. Conceptual: **{conceptual}**. None: **{none}**. Construct mapping for C and P is **pending**. UCI grade conversion is **blocked**.

## Construct roles

{summary}

## All 60 items

{item_table}

## Pianda competence codebook (source, not UCC)

{pianda_c}

## Pianda psychological capital codebook (source, not UCC)

{pianda_p}

Unmatched Pianda competence items with no close UCC sentence include leadership (C4), creative innovation (C7), and media creation (C9, C10). Unmatched PsyCap items include the pandemic item (PsyCap4), teacher-help (PsyCap13), and contribution to others (PsyCap14). A 10-item UCC P mean is not a shortened 14-item Pianda mean.

## Non-questionnaire academic and profile fields

{academic}

M42 does not feed A/S/B means, GWA, or invented missing values into UCI columns.
"""


def write_employability_experiments(bundle: dict) -> str:
    reduced = bundle["employability"]["cp_reduced"]
    expanded = bundle["employability"]["internship_expanded"]
    parts = [
        "# Employability experiments (M42)",
        "",
        "Research-only source-native regression on the Pianda workbook. Target: `EmployabilityMean`, the unweighted mean of EVS1–EVS12 on the verified 1–5 scale. R² is not an accuracy percentage. UCC inference is blocked; UCC students were not scored.",
        "",
        "Protocol, fixed before scores were read:",
        "",
        f"- Seed {reduced['split']['seed']}. Holdout {reduced['split']['test_rows']} rows ({int(reduced['split']['test_size']*100)}%); training {reduced['split']['train_rows']} rows.",
        f"- RepeatedKFold, {reduced['split']['n_splits']} splits × {reduced['split']['n_repeats']} repeats, training partition only.",
        "- LinearRegression and Ridge standardize inside the pipeline. Tree models and DummyRegressor do not.",
        "- Selection: lower mean CV RMSE. Within 0.01 RMSE, earlier algorithm wins: LinearRegression, Ridge, RandomForestRegressor, GradientBoostingRegressor.",
        "- DummyRegressor (mean) is reported and is not selectable.",
        "- Holdout was not used to choose the algorithm.",
        "- EVS items, EmployabilityMean, and UCC E items are excluded from predictors.",
        "",
        "## A. Reduced C/P-only (source-native candidate experiment)",
        "",
        "Predictors: Pianda `CompetenceMean` (C1–C10) and `PsyCapMean` (PsyCap1–PsyCap14).",
        "UCC 10-item C/P means are **not** these columns. Deployment inference of UCC scores is **rejected**.",
        "",
        _regression_table(reduced["rows"]),
        "",
        f"Selected by CV RMSE: **{reduced['decision']['algorithm']}**. Contenders: {', '.join(reduced['decision']['contenders'])}.",
        "",
        "Interpretation of the training-partition fit:",
        "",
        _interpretation_block(reduced["interpretation"]),
        "",
        "## B. Expanded internship comparison (not deployment-compatible)",
        "",
        "Predictors: CompetenceMean, PsyCapMean, and InternshipMean (SIE1–SIE14). EduPredict stores an internship **count**, not this 14-item Likert mean. Every Pianda respondent had internship experience. This set is a labeled source-native comparison only.",
        "",
        _regression_table(expanded["rows"]),
        "",
        f"Selected by CV RMSE on this expanded set: **{expanded['decision']['algorithm']}**. Contenders: {', '.join(expanded['decision']['contenders'])}.",
        "",
        "This expanded winner is **not** a deployment candidate.",
        "",
        "## Display index",
        "",
        "If a native prediction stays inside 1–5, `((prediction - 1) / 4) * 100` may be shown. Clamp out-of-range display values for presentation only; store the native score. The index is not P(employment).",
        "",
        "C-only and P-only single-construct fits: **not run** as deployment candidates. Both source constructs are defensibly calculated; the specified reduced experiment uses both.",
        "",
        "Reproducibility rerun of the C/P comparison matched: "
        + str(bundle["reproducibility"]["employability_cp"]),
        "",
    ]
    return "\n".join(parts)


def write_dropout_experiments(bundle: dict) -> str:
    dropout = bundle["dropout"]
    parts = [
        "# Dropout experiments (M42)",
        "",
        "Three-class UCI target: Dropout, Enrolled, Graduate. No Low/Moderate/High labels. Grade columns excluded. A/S/B questionnaire means excluded. P(Dropout) is source-trained, not UCC-calibrated.",
        "",
        "Protocol, fixed before scores were read:",
        "",
        "- Seed 42. Stratified 20% holdout. RepeatedStratifiedKFold 5 × 5 on the training partition.",
        "- DummyClassifier (most frequent), multinomial LogisticRegression (lbfgs, max_iter=2000, class_weight=balanced), RandomForestClassifier (balanced), GradientBoostingClassifier with balanced sample weights.",
        "- Selection: higher mean CV macro F1. Within 0.01, earlier algorithm wins: Logistic Regression, Random Forest Classifier, Gradient Boosting Classifier.",
        "- Holdout did not choose the algorithm.",
        "- Full 36-feature exploratory model: **not re-run** in M42. See `dropout_model_comparison.md` from M25.",
        "",
        "M25 previously measured full-feature CV macro F1 for Gradient Boosting Classifier: 0.7211 ± 0.0153 (36 predictors, including grades and second-semester fields). That figure is **not** an EduPredict result and was not used to select an M42 candidate.",
        "",
    ]
    for spec_id, result in dropout.items():
        holdout = next(
            row["holdout"]
            for row in result["rows"]
            if row["algorithm"] == result["decision"]["algorithm"]
        )
        parts.extend(
            [
                f"## {result['label']}",
                "",
                f"Contract: `{spec_id}`. Timing: `{result['timing']}`. Deployment status: **{result['deployment_status']}**.",
                "",
                result["note"],
                "",
                f"Columns: {', '.join(result['columns'])}.",
                "",
                f"Split: train {result['split']['train_rows']}, holdout {result['split']['test_rows']}. Class counts in the full file: {result['split']['class_counts']}.",
                "",
                _classification_table(result["rows"]),
                "",
                f"Selected: **{result['decision']['algorithm']}**. Contenders: {', '.join(result['decision']['contenders'])}.",
                "",
                "Holdout per-class metrics for the selected algorithm:",
                "",
                _per_class_table(holdout),
                "",
                _confusion_table(holdout),
                "",
                f"Dropout false negatives: {holdout.get('dropout_false_negatives')} "
                f"(rate {_num(holdout.get('dropout_false_negative_rate'))}).",
                f"Holdout accuracy {_num(holdout.get('accuracy'))}, macro F1 {_num(holdout.get('f1_macro'))}, "
                f"weighted F1 {_num(holdout.get('f1_weighted'))}.",
                f"P(Dropout) ROC-AUC {_num(holdout.get('roc_auc_dropout_vs_rest'))}; "
                f"OvR macro ROC-AUC {_num(holdout.get('roc_auc_ovr_macro'))}; "
                f"Brier {_num(holdout.get('brier_dropout'))}.",
                "",
            ]
        )
    parts.extend(
        [
            "Reproducibility rerun of the first-semester units comparison matched: "
            + str(bundle["reproducibility"]["dropout_first_sem"]),
            "",
            "UCC unit-count inference: **blocked**. Evaluations, credited units, and without-evaluations fields remain source-only and were not treated as deployment-feasible because EduPredict does not store matching counts.",
            "",
        ]
    )
    return "\n".join(parts)


def write_model_comparison(bundle: dict) -> str:
    emp = bundle["employability"]["cp_reduced"]
    intern = bundle["employability"]["internship_expanded"]
    dropout = bundle["dropout"]
    emp_sel = next(row for row in emp["rows"] if row["algorithm"] == emp["decision"]["algorithm"])
    intern_sel = next(row for row in intern["rows"] if row["algorithm"] == intern["decision"]["algorithm"])
    dummy = next(row for row in emp["rows"] if row["algorithm"] == "DummyRegressor")
    lines = [
        "# Model comparison (M42)",
        "",
        "Regression metrics are not compared with classification metrics. External holdout scores are **not** UCC validity.",
        "",
        "## Employability (native 1–5 EVS mean)",
        "",
        _table(
            ["Experiment", "Features", "Selected algorithm", "CV RMSE", "Holdout MAE", "Holdout RMSE", "Holdout R²", "UCC inference"],
            [
                [
                    "C/P reduced",
                    "CompetenceMean, PsyCapMean",
                    emp["decision"]["algorithm"],
                    f"{_num(emp_sel['cv_rmse_mean'])} ± {_num(emp_sel['cv_rmse_std'])}",
                    _num(emp_sel["holdout"]["mae"]),
                    _num(emp_sel["holdout"]["rmse"]),
                    _num(emp_sel["holdout"]["r2"]),
                    "blocked",
                ],
                [
                    "Internship expanded",
                    "C/P + InternshipMean",
                    intern["decision"]["algorithm"],
                    f"{_num(intern_sel['cv_rmse_mean'])} ± {_num(intern_sel['cv_rmse_std'])}",
                    _num(intern_sel["holdout"]["mae"]),
                    _num(intern_sel["holdout"]["rmse"]),
                    _num(intern_sel["holdout"]["r2"]),
                    "blocked; not deployment-compatible",
                ],
                [
                    "DummyRegressor baseline on C/P",
                    "mean of training target",
                    "DummyRegressor",
                    f"{_num(dummy['cv_rmse_mean'])} ± {_num(dummy['cv_rmse_std'])}",
                    _num(dummy["holdout"]["mae"]),
                    _num(dummy["holdout"]["rmse"]),
                    _num(dummy["holdout"]["r2"]),
                    "n/a",
                ],
            ],
        ),
        "",
        "M25 previously selected Linear Regression on the same two Pianda means (CV RMSE 0.3664 ± 0.0462). M42 re-runs that protocol with DummyRegressor named explicitly and stores a separate artifact. It does not replace `employability_candidate_v1.joblib`.",
        "",
        "## Dropout (three UCI classes)",
        "",
    ]
    drop_rows = []
    for result in dropout.values():
        selected = next(row for row in result["rows"] if row["algorithm"] == result["decision"]["algorithm"])
        holdout = selected["holdout"]
        drop_rows.append(
            [
                result["label"],
                str(len(result["columns"])),
                result["decision"]["algorithm"],
                f"{_num(selected['cv_f1_macro_mean'])} ± {_num(selected['cv_f1_macro_std'])}",
                _num(holdout["f1_macro"]),
                _num(holdout["accuracy"]),
                _num(holdout["per_class"]["Dropout"]["recall"]),
                result["timing"],
                result["deployment_status"],
            ]
        )
    majority = next(row for row in next(iter(dropout.values()))["rows"] if row["algorithm"] == "Majority baseline")
    drop_rows.append(
        [
            "Majority baseline (same split)",
            "n/a",
            "DummyClassifier most_frequent",
            f"{_num(majority['cv_f1_macro_mean'])} ± {_num(majority['cv_f1_macro_std'])}",
            _num(majority["holdout"]["f1_macro"]),
            _num(majority["holdout"]["accuracy"]),
            _num(majority["holdout"]["per_class"]["Dropout"]["recall"]),
            "n/a",
            "not a candidate",
        ]
    )
    lines.append(
        _table(
            [
                "Experiment",
                "n features",
                "Selected algorithm",
                "CV macro F1",
                "Holdout macro F1",
                "Holdout accuracy",
                "Holdout dropout recall",
                "Timing",
                "UCC status",
            ],
            drop_rows,
        )
    )
    lines.extend(
        [
            "",
            "M25 scholarship-only logistic regression had CV macro F1 0.3492 and holdout Enrolled F1 0. M42 reports the re-measured values above rather than copying those figures as new evidence.",
            "",
            "Full-feature M25 gradient boosting (36 predictors, including grades): CV macro F1 0.7211 ± 0.0153. **not run** in M42; not deployment-feasible.",
            "",
        ]
    )
    return "\n".join(lines)


def write_compatibility(bundle: dict) -> str:
    contract_table = _table(
        ["Contract", "Task", "Columns", "UCC inference", "Status", "Reason"],
        [
            [
                name,
                spec["task"],
                ", ".join(spec["columns"]),
                spec["ucc_inference"],
                spec["status"],
                spec["reason"],
            ]
            for name, spec in CONTRACTS.items()
        ],
    )
    return f"""# Deployment feature compatibility (M42)

No live FastAPI or Laravel predictor change is made. Candidate schemas below are research contracts. `allowed_on_source_schema_only` means Pianda or UCI columns with their native coding. It does **not** mean UCC answers may be sent.

{contract_table}

## Explicitly blocked

- All UCC item codes {VERSION} A1–A10, S1–S10, B1–B10, C1–C10, P1–P10, E1–E10.
- UCC section means, including a 10-item CompetenceMean or PsyCapMean computed from UCC C/P.
- Philippine GWA and the UCI grade columns: {', '.join(GRADE_COLUMNS)}.
- Invented missing scholarship, age, or unit values.
- A/S/B means as dropout predictors.
- E means as employability predictors.
- UCI course codes filled with UCC program codes.

## Test-only rejection cases

Synthetic fixtures used in `tests/test_m42_compatibility.py` (labeled test-only, not empirical performance):

1. UCC C1–C10 answers placed in a Pianda payload.
2. UCC GWA placed in a UCI grade column.
3. `input_origin=ucc` with numeric CompetenceMean/PsyCapMean.
4. Extra E1 or EVS1 column on a C/P frame.
5. First-semester unit model given second-semester columns.

Source-native holdout rows from the Pianda and UCI files remain valid for artifact reload tests.

## What M43 would still need

A human review of this mapping, a locked instrument, CVI/pilot reliability, a unit-definition memo, a scholarship missing-data rule, and local outcome labels. None of those is supplied by a source holdout score.
"""


def write_limitations() -> str:
    return f"""# Research limitations (M42)

## External population gap

Pianda respondents are vocational secondary students in Aceh, Indonesia, ages about 16–18, all with internship experience, surveyed in 2022. Intended EduPredict users are UCC CLAS undergraduates in Caloocan across multiple programs. UCI respondents are students in Portuguese higher education with administrative enrollment and curricular-unit records. Neither sample is UCC.

## Survey validation

`{VERSION}` is a panel-revised 60-item draft pending reapproval. Expert I-CVI, S-CVI/Ave, and per-section Cronbach alpha are **not observed**. The instrument must not be labeled panel-reapproved or psychometrically validated. Historic 36-item responses stay on `ucc-panel-approved-v1` and were not used as training data.

## Construct transfer

No UCC item is a verbatim Pianda item. C item counts now both equal 10; that is not equivalence. P is 10 versus 14. E is 10 versus 12 EVS target items and is excluded from predictors. Same-occasion self-report association on Pianda can produce a moderate R² without predicting hiring.

## Dropout transfer

Directly usable UCI predictors in EduPredict: **none**. Scholarship collapse is unverified. Unit counts lack a first-semester definition. Grades are the wrong scale and were excluded rather than converted. P(Dropout) from these models is source-trained and not UCC-calibrated. No Low/Moderate/High cut is defined.

## Calibration and labels

No UCC employment or dropout outcome labels exist in this repository. Synthesizing labels from current grades would leak the outcome from the predictors. Calibration on Portuguese or Indonesian samples does not calibrate UCC probabilities.

## What cannot be fed into the trained models

UCC questionnaire answers, UCC section means, Philippine GWA, invented missing values, A/S/B as dropout features, E as an employability feature, and UCI second-semester fields at enrollment.

## Missing UCC outcome-label blockers for M43

1. Locked, reapproved instrument with recorded CVI and a CLAS-similar pilot.
2. Equivalence study or an explicit decision that UCC C/P are new scales.
3. Consenting later UCC employability and retention labels, not reconstructed from GWA.
4. Written unit-count and scholarship rules that can fail review.
5. Local calibration and fairness review before any driver switch.

A research candidate that works on Pianda or UCI data is **not** validated on UCC data.
"""
