# Dropout data audit

Research audit of the local UCI file. The raw file was not modified.

## Provenance

- Citation: Realinho, V., Vieira Martins, M., Machado, J., & Baptista, L. (2021). *Predict Students' Dropout and Academic Success* [Dataset]. UCI Machine Learning Repository. https://doi.org/10.24432/C5MC89
- Introductory paper: Martins, M. V., Tolledo, D., Machado, J., Baptista, L. M. T., & Realinho, V. (2021). Early prediction of student's performance in higher education: a case study. https://doi.org/10.1007/978-3-030-72657-7_16
- Data article used for the attribute grouping and the renumbering discrepancy: Martins et al., *Data* 2022, 7(11), 146. https://doi.org/10.3390/data7110146
- UCI record: id 697, 4424 instances, 36 features, donated 12 December 2021, target column `Target`, missing values reported as none.
- License: the UCI API payload retrieved for id 697 did not contain a license field. No license was inferred. The local file was not replaced with a fresh download.
- UCI lists `data.csv` at 520.7 KB. The local file is 533230 bytes, SHA-256 `3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae`.
- Recommended source split recorded by UCI: 80% train and 20% test. This experiment uses that split and does not use the test split for selection.

## File verification

- Rows: 4424. Columns after cleanup: 37 (36 predictors + Target).
- Empty cells: none. Duplicate rows: none.
- Target counts: Dropout 1421, Enrolled 794, Graduate 2209.
- Header quirks preserved in the raw file and removed only in memory: UTF-8 BOM on the first column name = True; columns with trailing whitespace = ['Daytime/evening attendance\t'].
- `Scholarship holder` takes only the values 0 and 1.
- Course codes in this file are the original UCI codes (33, 171, 8014, and so on). Application-mode codes are likewise the original non-consecutive codes. The MDPI data article's tables renumber several of those fields to 1…K and report different means. This experiment one-hot encodes the codes that are in the file. It does not apply the article's renumbering.
- Documented grade scales, confirmed against observed ranges rather than converted: previous qualification and admission grades are on 0–200; curricular-unit grade averages are on 0–20. No Philippine GWA conversion is defined.
- The UCI description of `Curricular units 2nd sem (without evaluations)` says "1st semester". The column name says 2nd semester. The column was kept under its header and not relabeled.

## Target

The target remains the three source labels: Dropout, Enrolled, and Graduate. P(Dropout) is the predicted probability of the Dropout class. No Low, Moderate, or High cut point is estimated.
