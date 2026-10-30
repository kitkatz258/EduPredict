# Deployment feature compatibility (M42)

No live FastAPI or Laravel predictor change is made. Candidate schemas below are research contracts. `allowed_on_source_schema_only` means Pianda or UCI columns with their native coding. It does **not** mean UCC answers may be sent.

| Contract | Task | Columns | UCC inference | Status | Reason |
| --- | --- | --- | --- | --- | --- |
| pianda-cp-reduced-m42 | employability_regression | CompetenceMean, PsyCapMean | blocked | allowed_on_source_schema_only | Source-native Pianda CompetenceMean (C1–C10) and PsyCapMean (PsyCap1–PsyCap14). UCC 10-item C/P means are not measurement-equivalent. |
| pianda-cp-internship-expanded-m42 | employability_regression | CompetenceMean, PsyCapMean, InternshipMean | blocked | allowed_on_source_schema_only | Expanded source-native comparison. InternshipMean is the Pianda SIE1–SIE14 mean. EduPredict stores an internship count, not that 14-item scale. |
| uci-scholarship-only-m42 | dropout_classification | Scholarship holder | blocked | pending_mapping | UCI Scholarship holder is 0/1. EduPredict scholarship_status categories and missing-profile coercion are not verified equivalents. |
| uci-first-sem-units-m42 | dropout_classification | Curricular units 1st sem (enrolled), Curricular units 1st sem (approved) | blocked | pending_unit_definition | Source-native first-semester enrolled and approved unit counts. UCC subject-unit totals have no verified first-semester index or approval definition. Grade columns are excluded. |
| uci-two-sem-units-m42 | dropout_classification | Curricular units 1st sem (enrolled), Curricular units 1st sem (approved), Curricular units 2nd sem (enrolled), Curricular units 2nd sem (approved) | blocked | pending_unit_definition | Source-native first- and second-semester enrolled and approved counts. Using second-semester results for an earlier prediction is leakage. UCC term identity is unverified. Grade columns are excluded. |

## Explicitly blocked

- All UCC item codes ucc-panel-revised-60-v2 A1–A10, S1–S10, B1–B10, C1–C10, P1–P10, E1–E10.
- UCC section means, including a 10-item CompetenceMean or PsyCapMean computed from UCC C/P.
- Philippine GWA and the UCI grade columns: Previous qualification (grade), Admission grade, Curricular units 1st sem (grade), Curricular units 2nd sem (grade).
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
