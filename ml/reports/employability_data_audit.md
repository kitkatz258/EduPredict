# Employability data audit

Research audit of the local Pianda workbook. This file does not change the source workbook.

## Provenance

- Citation: Pianda, D., Hilmiana, & Widianto, S. (2026). *Dataset of student internship experience, competence, psychological capital and employability's vocational student* (Version 4) [Dataset]. Mendeley Data. https://doi.org/10.17632/hmn3b4c6c4.4
- Associated article used for the sampling cross-check: Pianda, D., Hilmiana, Widianto, S., & Sartika, D. (2025). The influence employability of vocational students through internship experiences and 21st-century competencies: a moderated mediation model. *Cogent Education*. https://doi.org/10.1080/2331186X.2025.2476285
- Workbook core properties: creator IBM SPSS Export Facility; last modified by teukudidi pianda1; modified timestamp 2026-06-14T09:02:46Z.
- Questionnaire core properties: creator ASUS; last modified by teukudidi pianda1; modified timestamp 2026-06-11T13:15:00Z.
- License: the retrieved Mendeley Version 4 page did not display a license. No license was inferred.
- Population stated by the record: 266 vocational students in Aceh Province who had internship experience, surveyed August–December 2022.
- Local row count: 266. Missing cells: 0. Duplicate rows: 0.

| File | Bytes | SHA-256 |
| --- | --- | --- |
| ml/data/raw/employability/Raw Dataset.xlsx | 84057 | f5e6fcdbb4e5687871a0b1727ca1f2ba3e1e7e6f785895ad763324617bcc064f |
| ml/data/raw/employability/Survey questionnaire.docx | 59719 | c8b23356b2de6adde7f6ef4fdf0211da19abd5bfe9c848019000feb9f23cf981 |
| ml/data/raw/employability/Dataset.rar | 132771 | 2a00ca9e5db291df91132ba82ccb5e8094a03b49aa6532bde2227ab3677feec3 |

## Archive check

`Dataset.rar` is a RAR5 archive. Members were listed in Python and compared by unpacked size and CRC-32. No member was extracted into the training table.

| Archive member | Unpacked bytes | CRC-32 | Loose file | Same size | Same CRC-32 |
| --- | --- | --- | --- | --- | --- |
| Dataset/Raw Dataset (Excel).xlsx | 84057 | 424c32b3 | Raw Dataset.xlsx | True | False |
| Dataset/Survey questionnaire.docx | 59719 | 7ef9d4db | Survey questionnaire.docx | True | True |
| Dataset | 0 | 00000000 |  |  |  |

The loose questionnaire matches the archive member byte for byte. The loose workbook has the same unpacked size as `Dataset/Raw Dataset (Excel).xlsx` and a different CRC-32, so the archived workbook is not byte-identical. Training uses only the loose `Raw Dataset.xlsx` that was audited sheet by sheet.

## Scoring verification

The codebook and the bilingual questionnaire both use a 1–5 Likert scale from strongly disagree to strongly agree. Neither document states a reverse-scored item. Every item in the sheet is an integer in {1, 2, 3, 4, 5}.

Each stored `AVERAGE` column equals the unweighted arithmetic mean of its items. The maximum absolute difference is below 1e-9, so the published means are accepted.

| Stored column | Derived name | Items | Max abs diff | Min | Max |
| --- | --- | --- | --- | --- | --- |
| AVERAGE (C) | CompetenceMean | 10 | 0.0e+00 | 1.0000 | 5.0000 |
| AVERAGE (PsyCap) | PsyCapMean | 14 | 0.0e+00 | 1.3571 | 5.0000 |
| AVERAGE (EVS) | EmployabilityMean | 12 | 0.0e+00 | 1.0000 | 5.0000 |
| AVERAGE (SIE) | InternshipMean | 14 | 0.0e+00 | 1.2143 | 5.0000 |

Target definition: `EmployabilityMean`, the unweighted mean of EVS1–EVS12. Observed range 1.0000 to 5.0000, mean 3.9702, sample standard deviation 0.5880. The range lies inside 1–5, so the display rescale `((score - 1) / 4) * 100` is defined. That rescale is an Employability Readiness index, not a probability of employment. Model metrics below are on the native 1–5 mean.

## Instrument notes that were not rewritten

- The questionnaire midpoint is printed in Indonesian as "Tidak setuju maupun tidak menolak" and in English as "Neither Agree nor Disagree".
- The codebook labels school status `1 = country` and `2 = private`. The value was not recoded to public or negeri.
- Codebook subscale labels include the spellings "Sosial Capital" and "Envirionmental Monitoring". Subscale groups follow the codebook cell layout and are not model inputs.
- PsyCap4 refers to learning during the pandemic.
- Mendeley Version 4 says the sample was drawn by simple random sampling. The 2025 article, section 3.1, says random cluster sampling of 266 students in 17 institutions from 15,435 Class XII students in 220 schools. Both descriptions are recorded; neither was edited.
- The students are SMK (vocational secondary) students aged about 16–18 in the codebook, not UCC undergraduates.
- Respondent codes and signatures are on the questionnaire form and are not columns in the workbook.

## Approved UCC PDFs

The approved-questionnaire directory exists, but these required PDFs are absent: src/cursor/reference/approved-questionnaire/EduPredict_Questionnaire.pdf; src/cursor/reference/approved-questionnaire/EduPredict_Scoring_and_Research_Basis.pdf. Item text, reverse-scoring exceptions, and consultation-draft bands were not read. Section codes used in the mapping note come from the M25 specification, not from a PDF transcription. UCC section means were not computed.
