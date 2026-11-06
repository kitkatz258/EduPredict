# Data audit (M42)

Research audit of the local Pianda v4 workbook and UCI dropout file. Raw files were not modified. This file does not replace `employability_data_audit.md` or `dropout_data_audit.md`.

## Questionnaire sources used in M42

| File | Role |
| --- | --- |
| `SystemQuestions.pdf` | Exact 60-item v2 statements (`ucc-panel-revised-60-v2`). |
| `EduPredict_Scoring_and_Research_Basis_Updated.pdf` | Updated 60-item scoring and research-basis consultation draft. |
| Historic `ucc-panel-approved-v1` (36 items) | Preserved in Laravel. Not rescored. Not used as the M42 instrument. |

The previous 36-item scoring PDF is not the scoring source for M42.

## Pianda employability (Mendeley v4)

- Citation: Pianda, D., Hilmiana, Widianto, S., & Sartika, D. (2026). Dataset of student internship experience, competence, psychological capital and employability's vocational student (Version 4) [Dataset]. Mendeley Data. https://doi.org/10.17632/hmn3b4c6c4.4
- Local workbook: `ml/data/raw/employability/Raw Dataset.xlsx` SHA-256 `f5e6fcdbb4e5687871a0b1727ca1f2ba3e1e7e6f785895ad763324617bcc064f` (84057 bytes)
- Questionnaire: `ml/data/raw/employability/Survey questionnaire.docx` SHA-256 `c8b23356b2de6adde7f6ef4fdf0211da19abd5bfe9c848019000feb9f23cf981`
- Archive listed, not merged: `ml/data/raw/employability/Dataset.rar` SHA-256 `2a00ca9e5db291df91132ba82ccb5e8094a03b49aa6532bde2227ab3677feec3`
- License: the retrieved Mendeley Version 4 page did not display a license. No license was inferred. Raw records are gitignored and were not committed.
- Rows: 266. Missing cells: 0. Duplicate rows: 0.
- Likert items: integers in {1,2,3,4,5}. Codebook and bilingual questionnaire state no reverse-scored item. M42 did not invent reverse codes.
- Stored AVERAGE columns match unweighted item means (max abs diff 0).
- CompetenceMean: unweighted mean of C1–C10. Observed range 1.0000–5.0000.
- PsyCapMean: unweighted mean of PsyCap1–PsyCap14. Observed range 1.3571–5.0000.
- EmployabilityMean (target): unweighted mean of EVS1–EVS12. Observed range 1.0000–5.0000, mean 3.9702, SD 0.5880. Native 1–5 range is verified, so a later display index `((score-1)/4)*100` is defined. That index is not a probability of employment.
- InternshipMean: unweighted mean of SIE1–SIE14. Observed range 1.2143–5.0000. Optional expanded comparison only.
- Population: 266 vocational (SMK) students in Aceh with internship experience, codebook ages 16–18. Not UCC CLAS undergraduates.
- Sampling descriptions on record (not reconciled): Mendeley simple random; 2025 article cluster sample; scoring PDF Data in Brief title "stratified".

## UCI dropout

- Citation: Realinho, V., Vieira Martins, M., Machado, J., & Baptista, L. (2021). Predict Students' Dropout and Academic Success [Dataset]. UCI Machine Learning Repository. https://doi.org/10.24432/C5MC89
- Local file: `ml/data/raw/dropout/data.csv` SHA-256 `3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae` (533230 bytes)
- License: the UCI API payload retrieved for id 697 did not contain a license field. No license was inferred.
- Rows: 4424. Columns after BOM/whitespace cleanup: 37. Empty cells: 0. Duplicate rows: 0.
- Target counts: Dropout 1421, Enrolled 794, Graduate 2209.
- Three-class labels are preserved. No Low/Moderate/High outcome was created.
- Header quirks in the raw file: UTF-8 BOM = True; trailing-tab columns = ['Daytime/evening attendance\t'].
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
