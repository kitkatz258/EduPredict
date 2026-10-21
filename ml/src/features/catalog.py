"""Per-column audit decisions shared by the reports and tests."""

from __future__ import annotations

from src.data.dropout import (
    FIRST_SEMESTER_COLUMNS,
    PREDICTOR_COLUMNS,
    REDUCED_PREDICTORS,
    SECOND_SEMESTER_COLUMNS,
    TARGET_COLUMN,
)
from src.data.employability import (
    AVERAGE_COMPETENCE,
    AVERAGE_EMPLOYABILITY,
    AVERAGE_INTERNSHIP,
    AVERAGE_PSYCAP,
    COMPETENCE_ITEMS,
    COMPETENCE_MEAN,
    EMPLOYABILITY_ITEMS,
    EMPLOYABILITY_TARGET,
    INTERNSHIP_ITEMS,
    PSYCAP_ITEMS,
    PSYCAP_MEAN,
    V1_PREDICTORS,
)

NOMINAL_DROPOUT_COLUMNS = (
    "Marital status",
    "Application mode",
    "Course",
    "Previous qualification",
    "Nacionality",
    "Mother's qualification",
    "Father's qualification",
    "Mother's occupation",
    "Father's occupation",
)
BINARY_DROPOUT_COLUMNS = (
    "Daytime/evening attendance",
    "Displaced",
    "Educational special needs",
    "Debtor",
    "Tuition fees up to date",
    "Gender",
    "Scholarship holder",
    "International",
)
NUMERIC_DROPOUT_COLUMNS = tuple(
    column
    for column in PREDICTOR_COLUMNS
    if column not in NOMINAL_DROPOUT_COLUMNS and column not in BINARY_DROPOUT_COLUMNS
)
GRADE_COLUMNS_BLOCKED = (
    "Previous qualification (grade)",
    "Admission grade",
    "Curricular units 1st sem (grade)",
    "Curricular units 2nd sem (grade)",
)


def _row(**kwargs) -> dict:
    return kwargs


def employability_manifest() -> list[dict]:
    rows = []
    demographics = {
        "Gender": "Codebook 1 = Male, 2 = Female. Not a v1 predictor.",
        "Age": "Codebook 1 = 16 years, 2 = 17 years, 3 = 18 years. The sheet stores the code, not a year of age.",
        "school status": "Codebook 1 = country, 2 = private. The word country is not recoded.",
        "school accreditation": "Codebook 1 = A (excellent), 2 = B (good).",
    }
    for name, meaning in demographics.items():
        rows.append(
            _row(
                name=name,
                meaning=meaning,
                type_="integer code",
                role="demographic context",
                decision="EXCLUDE",
                leakage="Not the employability target. Excluded from v1 so the experiment stays on the two specified constructs.",
                missing="No missing values in the audited sheet. No imputation.",
                fairness="Gender, age code, school status, and accreditation can proxy group differences. They are not model inputs.",
                inference="Not in the v1 schema. School fields describe SMK Aceh, not a UCC college record.",
                mapping="No verified UCC equivalent in the missing approved PDFs.",
            )
        )
    for name in INTERNSHIP_ITEMS:
        rows.append(
            _row(
                name=name,
                meaning="Student Internship Experience Likert item, 1 strongly disagree to 5 strongly agree. No reverse scoring is stated.",
                type_="integer Likert",
                role="source item",
                decision="EXCLUDE",
                leakage="Same-occasion self-report, but not an employability-target item. Held out of v1.",
                missing="No missing values. No imputation.",
                fairness="Internship access may differ by placement; not used in v1.",
                inference="UCC's six specified sections do not include an SIE block.",
                mapping="UNMATCHED. Optional internship models were not trained.",
            )
        )
    rows.append(
        _row(
            name=AVERAGE_INTERNSHIP,
            meaning="Unweighted mean of SIE1–SIE14. Matches the recomputed mean exactly.",
            type_="float",
            role="derived field",
            decision="EXCLUDE",
            leakage="Derived from excluded internship items.",
            missing="No missing values.",
            fairness="Same as the SIE items.",
            inference="Not available as a verified UCC section.",
            mapping="UNMATCHED.",
        )
    )
    for name in COMPETENCE_ITEMS:
        rows.append(
            _row(
                name=name,
                meaning="Competence Likert item. Used only to recompute CompetenceMean.",
                type_="integer Likert",
                role="source item for a derived predictor",
                decision="EXCLUDE",
                leakage="Not a target item. Entering both the items and the mean would double-count the construct.",
                missing="No missing values. Items outside 1–5 would be rejected.",
                fairness="Self-report competence can reflect opportunity as well as skill.",
                inference="UCC specifies C1–C8, which is not this 10-item scale.",
                mapping="UNMATCHED at item level. The derived mean is the v1 predictor.",
            )
        )
    rows.append(
        _row(
            name=AVERAGE_COMPETENCE,
            meaning="Stored unweighted mean of C1–C10. Verified equal to the recomputed CompetenceMean.",
            type_="float",
            role="verification column",
            decision="EXCLUDE",
            leakage="Not supplied to the model; the pipeline receives the recomputed mean.",
            missing="No missing values.",
            fairness="Aggregate of the competence items.",
            inference="Do not replace this with a UCC section mean.",
            mapping="Source construct only. UCC C1–C8 equivalence is unresolved.",
        )
    )
    for name in PSYCAP_ITEMS:
        rows.append(
            _row(
                name=name,
                meaning="Psychological Capital Likert item. Used only to recompute PsyCapMean.",
                type_="integer Likert",
                role="source item for a derived predictor",
                decision="EXCLUDE",
                leakage="Not a target item. Not entered beside the mean.",
                missing="No missing values. Items outside 1–5 would be rejected.",
                fairness="Self-report psychological capital.",
                inference="UCC specifies P1–P6, which is not this 14-item scale.",
                mapping="UNMATCHED at item level. The derived mean is the v1 predictor.",
            )
        )
    rows.append(
        _row(
            name=AVERAGE_PSYCAP,
            meaning="Stored unweighted mean of PsyCap1–PsyCap14. Verified equal to the recomputed PsyCapMean.",
            type_="float",
            role="verification column",
            decision="EXCLUDE",
            leakage="Not supplied to the model; the pipeline receives the recomputed mean.",
            missing="No missing values.",
            fairness="Aggregate of the PsyCap items.",
            inference="Do not replace this with a UCC section mean.",
            mapping="Source construct only. UCC P1–P6 equivalence is unresolved.",
        )
    )
    for name in EMPLOYABILITY_ITEMS:
        rows.append(
            _row(
                name=name,
                meaning="Employability of Vocational Students Likert item. Part of the target construct.",
                type_="integer Likert",
                role="target item",
                decision="EXCLUDE",
                leakage="Direct target leakage if used as a predictor.",
                missing="No missing values.",
                fairness="Self-perceived employability, not an employment outcome.",
                inference="UCC E1–E4 is a descriptive comparison and is also excluded.",
                mapping="UNMATCHED. E is never a predictor.",
            )
        )
    rows.append(
        _row(
            name=AVERAGE_EMPLOYABILITY,
            meaning="Stored unweighted mean of EVS1–EVS12. This is the continuous employability target.",
            type_="float 1–5",
            role="target",
            decision="EXCLUDE",
            leakage="Target. Training uses the recomputed mean under the name EmployabilityMean.",
            missing="No missing values.",
            fairness="Same-instrument outcome. High scores are common in this sheet.",
            inference="Not an input. A later display rescale is not a probability.",
            mapping="Source target only. Not UCC E.",
        )
    )
    rows.append(
        _row(
            name=COMPETENCE_MEAN,
            meaning="Unweighted mean of C1–C10 after the stored average was verified.",
            type_="float 1–5",
            role="predictor",
            decision="KEEP",
            leakage="Separate items from EVS. Same-occasion common-method bias remains.",
            missing="Rejected if any source item is missing. No imputation.",
            fairness="Self-report construct.",
            inference="Available to this research candidate only as the Pianda 10-item mean.",
            mapping="Not equivalent to UCC C1–C8.",
        )
    )
    rows.append(
        _row(
            name=PSYCAP_MEAN,
            meaning="Unweighted mean of PsyCap1–PsyCap14 after the stored average was verified.",
            type_="float 1–5",
            role="predictor",
            decision="KEEP",
            leakage="Separate items from EVS. Same-occasion common-method bias remains.",
            missing="Rejected if any source item is missing. No imputation.",
            fairness="Self-report construct.",
            inference="Available to this research candidate only as the Pianda 14-item mean.",
            mapping="Not equivalent to UCC P1–P6.",
        )
    )
    return rows


def dropout_manifest() -> list[dict]:
    meanings = {
        "Marital status": "UCI integer code. 1 single, 2 married, 3 widower, 4 divorced, 5 facto union, 6 legally separated.",
        "Application mode": "UCI original integer codes (1, 2, 5, 7, …, 57), not the renumbered MDPI appendix.",
        "Application order": "Integer order. UCI describes 0 as first choice through 9 as last choice. This file contains 0.",
        "Course": "UCI original course codes (33, 171, 8014, …, 9991), not a 1–17 index.",
        "Daytime/evening attendance": "1 daytime, 0 evening.",
        "Previous qualification": "UCI original qualification code, not the renumbered MDPI appendix.",
        "Previous qualification (grade)": "Continuous grade on the documented 0–200 scale.",
        "Nacionality": "UCI spelling retained. Original nationality codes, not a 1–21 index.",
        "Mother's qualification": "UCI original qualification code.",
        "Father's qualification": "UCI original qualification code.",
        "Mother's occupation": "UCI original occupation code.",
        "Father's occupation": "UCI original occupation code.",
        "Admission grade": "Continuous admission grade on the documented 0–200 scale.",
        "Displaced": "1 yes, 0 no.",
        "Educational special needs": "1 yes, 0 no.",
        "Debtor": "1 yes, 0 no. Snapshot date is not independently stated.",
        "Tuition fees up to date": "1 yes, 0 no. Snapshot date is not independently stated.",
        "Gender": "1 male, 0 female.",
        "Scholarship holder": "1 yes, 0 no.",
        "Age at enrollment": "Age in years at enrollment.",
        "International": "1 yes, 0 no.",
        "Unemployment rate": "Macroeconomic percent from the source context.",
        "Inflation rate": "Macroeconomic percent from the source context.",
        "GDP": "Macroeconomic value from the source context.",
        TARGET_COLUMN: "Dropout, Enrolled, or Graduate at the end of the normal course duration.",
    }
    for column in FIRST_SEMESTER_COLUMNS:
        meanings[column] = "First-semester curricular record. Available only after that semester's results."
    for column in SECOND_SEMESTER_COLUMNS:
        meanings[column] = "Second-semester curricular record. Available only after that semester's results. The UCI note for the without-evaluations field says 1st semester; the column name says 2nd."
    rows = []
    for name in PREDICTOR_COLUMNS + (TARGET_COLUMN,):
        if name == TARGET_COLUMN:
            decision = "EXCLUDE"
            role = "target"
            mapping = "Preserved as three labels. No Low/Moderate/High threshold is defined."
            availability = "Outcome, not an input."
        elif name in REDUCED_PREDICTORS:
            decision = "KEEP"
            role = "reduced predictor"
            mapping = (
                "Proposed binary collapse: EduPredict scholarship_status other than none "
                "corresponds to 1, and none corresponds to 0. Category-level equivalence is not claimed."
            )
            availability = "EduPredict already stores scholarship_status and has_scholarship."
        elif name in GRADE_COLUMNS_BLOCKED or name in FIRST_SEMESTER_COLUMNS or name in SECOND_SEMESTER_COLUMNS:
            decision = "EXCLUDE"
            role = "exploratory predictor only"
            mapping = "No verified UCC-to-Portuguese grade or curricular-unit transformation."
            availability = "Not available to the current EduPredict predictor without an undefined conversion."
        elif name == "Age at enrollment":
            decision = "REVIEW"
            role = "exploratory predictor only"
            mapping = "institution_students.birthdate is nullable and FeatureBuilder does not emit age. Not used in the candidate."
            availability = "Not available at current inference."
        else:
            decision = "EXCLUDE"
            role = "exploratory predictor only"
            mapping = "No documented EduPredict equivalent."
            availability = "Not available at current EduPredict inference."
        if name in NOMINAL_DROPOUT_COLUMNS:
            type_ = "nominal integer code"
        elif name in BINARY_DROPOUT_COLUMNS:
            type_ = "binary 0/1"
        elif name == TARGET_COLUMN:
            type_ = "categorical label"
        else:
            type_ = "numeric"
        fairness = "Source field."
        if name in {"Gender", "Age at enrollment", "Marital status", "Nacionality", "International", "Educational special needs"}:
            fairness = "Sensitive or group-related attribute. Included only in the exploratory reference model."
        if name in {"Mother's qualification", "Father's qualification", "Mother's occupation", "Father's occupation"}:
            fairness = "Family background. Exploratory reference only."
        rows.append(
            _row(
                name=name,
                meaning=meanings[name],
                type_=type_,
                role=role,
                decision=decision,
                leakage=(
                    "Target label."
                    if name == TARGET_COLUMN
                    else "Second-semester academic result if used for an earlier prediction."
                    if name in SECOND_SEMESTER_COLUMNS
                    else "First-semester academic result if used at enrollment."
                    if name in FIRST_SEMESTER_COLUMNS
                    else "No target leakage. Debtor and tuition snapshot time is unverified."
                    if name in {"Debtor", "Tuition fees up to date"}
                    else "No target leakage."
                ),
                missing="No empty cells in this file. No imputation.",
                fairness=fairness,
                inference=availability,
                mapping=mapping,
            )
        )
    return rows


def v1_predictor_names() -> tuple[str, ...]:
    return V1_PREDICTORS


def employability_target_name() -> str:
    return EMPLOYABILITY_TARGET
