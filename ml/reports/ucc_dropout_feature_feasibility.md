# UCC dropout feature feasibility

Review date: 2026-10-21. Read-only audit of commit `d1615b10`, `FeatureBuilder`, grade calculation, student registration, and the questionnaire that the application can store. No model was retrained. No Laravel file, schema, or existing ML artifact was changed. The saved dropout candidate is not production-ready.

The candidate `ml/models/dropout_candidate_v1.joblib` is a logistic regression on one UCI column, `Scholarship holder`. Its metadata sets `production_validated` to false and `risk_thresholds` to null. The exploratory 36-predictor gradient boosting model was not saved. This review does not promote either one.

## What was audited

Code and schema:

- `App\Services\Prediction\FeatureBuilder` and `FeatureSet`
- `AcademicSummary`, `GwaCalculator`, `GradeScale`, and grade-report versioning
- `institution_students`, `students`, `grade_reports`, `subject_grades`, `socioeconomic_profiles`, and the questionnaire tables
- `StudentRegistrationService` and `InstitutionStudentImporter`
- `config/edupredict.php` and `QuestionnaireItemSeeder`
- The UCI file already audited in M25: 4,424 rows, 36 predictors, semicolon-separated, SHA-256 `3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae`

The configured application database is MySQL, host `db`, database `edupredict`. That host was not reachable in this session, so live row counts of saved grades, registrations, and questionnaire responses were not read. The findings below are about what the application can store and what `FeatureBuilder` emits. They are not counts of pilot students.

## Classification rule

Each of the 36 UCI predictors is placed in one class.

| Class | Meaning |
| --- | --- |
| Directly usable | The application stores the same measurement, with the same coding and timing, and `FeatureBuilder` can pass it through without a recode. |
| Usable only after a verified transformation | A related value exists, and a written rule could turn it into the UCI field. The rule is not verified, so the field stays out of the next training run until that rule is accepted. This review does not invent the rule. |
| Unavailable | The application does not store the UCI measurement. |
| Inappropriate | A local value exists, and placing it in the UCI column would treat a different construct, codebook, or grade scale as if it were the UCI field. |

Directly usable predictors: **none**.

## FeatureBuilder output

`FeatureBuilder` is the only inference mapping. It ignores draft socioeconomic profiles, archived skill rows, and grade reports that are not confirmed or that have been superseded. It emits:

- Philippine GWA on the 1.00–5.00 scale, rounded to two decimals, plus major and other GWA
- Failed-subject counts, unit totals, semester count, and `limited_history` when the number of distinct confirmed school-year and semester pairs is below 2
- `scholarship_status` and a boolean `has_scholarship`
- Employment, income bracket, household size, living arrangement, internet, device, and study space
- Counts of technical skills, certifications, internships, and other work. `project_count` is always 0
- Five questionnaire construct scores from the latest submitted response

It does not emit age, program code, year level, enrollment year, admission grade, gender, nationality, parental background, debtor status, tuition status, curricular-unit counts, or the UCC sections A, S, B, C, P, and E.

`has_scholarship` is true only when a published profile has a non-empty status other than `none`. A missing profile, a draft profile, or a blank status becomes false, not missing. The UCI pipeline rejects missing predictors and does not impute. Scoring a student with no published scholarship answer as `0` would invent a "no scholarship" value. That coercion is a blocker for the current candidate, whose only input is this binary flag.

The allowed scholarship statuses are `none`, `partial`, `full`, `government`, and `private`. The M25 manifest proposed collapsing every status other than `none` to UCI `1`. It also said category-level equivalence is not claimed. This review keeps that collapse in the "verified transformation" class. It is implemented in code and it is not verified against the Portuguese scholarship-holder field.

On the UCI file the flag is associated with the outcome, which is why a one-feature model can beat a majority baseline and still be a weak classifier. Dropout is 1,287 of 3,325 students with scholarship 0 (rate 0.3871) and 134 of 1,099 students with scholarship 1 (rate 0.1219). The saved model standardizes that column (training-split mean 0.252614, scale 0.434511). The Dropout-class coefficient on the scaled column is -0.326819. The holdout confusion matrix in `dropout_model_comparison.md` predicts Dropout for 680 of 885 rows and predicts Enrolled for none. Holdout dropout precision is 0.3897 and Enrolled F1 is 0. The low false-negative count (19 of 284) comes from over-predicting Dropout. It is not evidence that the candidate is ready to use.

## Grades

Confirmed current reports supply subject rows: code, name, units, midterm token, final-exam token, final grade, remarks, and a major-subject flag. `GwaCalculator` builds a unit-weighted average.

- Allowed numeric finals are 1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00, and 5.00. Lower is better. The passing maximum is 3.00. Failure is a final of 5.00 or a remark of FAILED.
- INC and dropped or withdrawn rows are left out of the average. A failed NSTP row is counted as a failure and then excluded from the average because subject codes with the NSTP prefix are excluded.
- If any INC row is present, the result is provisional. `FeatureBuilder` still emits the rounded GWA. It does not emit the provisional flag or the incomplete count.
- Distinct confirmed `school_year|semester` pairs become `semesters_completed`. Parser labels include First, Second, Midyear, and Summer. Reports are ordered by school year, then by the semester string, then by id. That string order is alphabetical, so First, Midyear, Second, Summer is not an academic calendar order.
- There is no stored index for "first semester of the degree" versus "latest uploaded term". `year_level` is copied from the institution list at registration and is not recomputed from grades.

UCI grade scales, checked on this file rather than converted:

| UCI field | Documented scale | Observed on this file |
| --- | --- | --- |
| Previous qualification (grade), Admission grade | 0–200 | 95 to 190 |
| Curricular units 1st sem (grade) | 0–20, higher is better | 0 to 18.875, median 12.2857 |
| Curricular units 2nd sem (grade) | 0–20, higher is better | 0 to 18.5714, median 12.2000 |

A Philippine GWA and a Portuguese 0–20 mean differ in direction, range, inclusions, and aggregation. No conversion is defined. This review does not create one. Direct substitution is inappropriate.

First-semester credited units have median 0 and maximum 20. Enrolled and approved have medians 6 and 5. Evaluations have median 8 and maximum 45, so an evaluation count is not a midterm-plus-final flag. Those distributions are why enrolled units, approved units, credited units, and evaluation counts are not treated as one local field.

## Registration

`institution_students` stores student number, name, program, year level, optional email, optional birthdate, and `is_registered`. The importer requires student number, last name, first name, program code, and year level. Birthdate and email are optional.

`StudentRegistrationService` copies student number, program, and year level onto `students`. It sets `semesters_completed` to 0. It sets `enrollment_year` to the calendar year of registration in `Asia/Manila`, not to a historical year of first enrollment. A third-year student who registers in 2026 is stored with enrollment year 2026.

The users table has no gender column. Registration does not collect marital status, application mode, application order, previous qualification, nationality, parental education or occupation, displacement, special needs, debtor status, tuition status, international-student status, admission grade, or daytime versus evening attendance.

Birthdate stays on the institution list. `FeatureBuilder` does not read it, and the feature snapshot is required to exclude birthdate. Age at enrollment is therefore not available to the current predictor. Computing it would also need a reference date. Using `enrollment_year` as that date would measure age at registration for students who were already enrolled.

## Questionnaire information the application can use

| Source | What exists | Relation to the UCI model and to UCC |
| --- | --- | --- |
| Seeded `draft-v1` | 20 Likert items, five constructs, reverse scoring, scores stored as 0–100. Items are marked draft. | Not A, S, B, C, P, or E. Not a UCI column. The scoring PDF says B does not change dropout probability. |
| Socioeconomic profile | Categorical income, scholarship, employment, living arrangement, internet, device, study space. Draft rows are ignored. | Scholarship is the only proposed UCI link, and only after the unverified binary collapse. Income and living arrangement are different constructs from parental occupation and Displaced. |
| Approved PDF sections A, S, B, C, P, E | 36 items in the untracked PDFs. Not seeded. | A, S, and B are context. C and P are employability inputs only after equivalence work that has not been done. E is not a predictor. None of them is a UCI column. |

S3 and S4 (device and internet) resemble the profile's device and internet fields. The profile fields are categories, not 1–5 items, and the scoring PDF says section S is not a model input. They are not written into UCI columns by this review.

## All 36 predictors

| UCI predictor | Class | Local evidence | Timing constraint |
| --- | --- | --- | --- |
| Marital status | Unavailable | Not collected. Sensitive attribute. | Enrollment field in the UCI design. |
| Application mode | Unavailable | Registration is a match to the institution list. There is no Portuguese application-mode code. | Enrollment. |
| Application order | Unavailable | Not collected. The UCI file contains 0, which the source describes as first choice. | Enrollment. |
| Course | Inappropriate | `program_id` exists for UCC programs. UCI course values are original codes such as 33, 171, and 8014. Inserting a UCC program into that codebook would relabel a different institution's course. | Enrollment. Program is known at registration and is still the wrong code. |
| Daytime/evening attendance | Unavailable | Not collected. | Enrollment. |
| Previous qualification | Unavailable | Not collected. `year_level` is not a prior-qualification code. | Enrollment. |
| Previous qualification (grade) | Unavailable | No prior grade is stored. The UCI scale is 0–200. | Enrollment. A future high-school grade would still need a conversion this review does not supply. |
| Nacionality | Unavailable | Not collected. Sensitive attribute. The UCI spelling is retained in the source file. | Enrollment. |
| Mother's qualification | Unavailable | Not collected. Family background. | Enrollment. |
| Father's qualification | Unavailable | Not collected. Family background. | Enrollment. |
| Mother's occupation | Unavailable | Not collected. The PSOC tables are for career matching, not parental occupation. | Enrollment. |
| Father's occupation | Unavailable | Not collected. | Enrollment. |
| Admission grade | Unavailable | No entrance or admission grade is stored. The UCI scale is 0–200. | Enrollment. |
| Displaced | Inappropriate | `living_arrangement` can be with family, boarding, dormitory, relative, or renting. The UCI displaced flag is a different definition. Collapsing living arrangement to displaced would invent a mapping. | Enrollment. |
| Educational special needs | Unavailable | Not collected. Sensitive attribute. | Enrollment. |
| Debtor | Unavailable | Not collected. The UCI snapshot date is not independently stated. | Do not treat as known at enrollment until the source timing is verified. |
| Tuition fees up to date | Unavailable | Not collected. Scholarship status is not tuition status. Snapshot date unverified. | Same as Debtor. |
| Gender | Unavailable | No gender column on users, students, or institution students. The exploratory UCI model used it. The candidate excludes it. | Enrollment. Also a sensitive attribute. |
| Scholarship holder | Usable only after a verified transformation | Published `scholarship_status` and `has_scholarship` exist. The five local categories are not the UCI 0/1 definition. Missing or draft profiles become false. | Known only after a published profile. Unsafe to score as 0 when the profile is absent. |
| Age at enrollment | Usable only after a verified transformation | Optional `institution_students.birthdate`. `enrollment_year` is the registration calendar year. `FeatureBuilder` does not emit age. No missing-birthdate rule exists, and imputation is not allowed. | Enrollment, once a real enrollment date is defined. Sensitive attribute. |
| International | Unavailable | Not collected. | Enrollment. |
| Curricular units 1st sem (credited) | Unavailable | No recognition-of-prior-units field. On this UCI file the median is 0, which fits recognized credit rather than units appearing on a term report. | After that semester only, and only if the field existed. |
| Curricular units 1st sem (enrolled) | Usable only after a verified transformation | Subject rows with units exist after a term is confirmed. A count still needs a rule for subjects versus units, repeats, dropped rows, NSTP, summer, and which term is "1st sem". | Leakage if used at enrollment. |
| Curricular units 1st sem (evaluations) | Unavailable | Midterm and final exam cells are grade tokens, not a count of evaluations. UCI evaluations run up to 45. | After that semester only. |
| Curricular units 1st sem (approved) | Usable only after a verified transformation | Passed rows can be counted only after the same term rules. Local pass is a final of 3.00 or better, with 5.00 as failure. That is not a verified UCI approval definition. | Leakage if used at enrollment. |
| Curricular units 1st sem (grade) | Inappropriate | The stored summary is a unit-weighted Philippine GWA. Direction, scale, NSTP exclusion, and INC exclusion differ from the UCI 0–20 mean. No conversion is verified. | Leakage if used at enrollment. A cumulative GWA is also the wrong column after later terms exist. |
| Curricular units 1st sem (without evaluations) | Unavailable | INC, DRP, and W are local grade tokens. They are not this UCI count. | After that semester only. |
| Curricular units 2nd sem (credited) | Unavailable | Same gap as the first-semester credited field. | Leakage at enrollment and after only the first semester. |
| Curricular units 2nd sem (enrolled) | Usable only after a verified transformation | Same counting gap as the first semester, plus the term must actually be the second semester. | Leakage before that semester's results are confirmed. |
| Curricular units 2nd sem (evaluations) | Unavailable | Same as the first-semester evaluations field. The UCI description of the without-evaluations column says 1st semester while the header says 2nd. The column was not relabeled. | Leakage before the second semester. |
| Curricular units 2nd sem (approved) | Usable only after a verified transformation | Same as the first-semester approved field. | Leakage before the second semester. |
| Curricular units 2nd sem (grade) | Inappropriate | Same scale block as the first-semester grade. | Leakage before the second semester. Latest-term GWA must not be written into this column. |
| Curricular units 2nd sem (without evaluations) | Unavailable | Same as the first-semester field. | Leakage before the second semester. |
| Unemployment rate | Unavailable | Portuguese macroeconomic series. Not stored. | A current Philippine rate would be a different variable. |
| Inflation rate | Unavailable | Not stored. | Same. |
| GDP | Unavailable | Not stored. | Same. |

Class counts: directly usable 0, usable only after a verified transformation 5 (`Scholarship holder`, `Age at enrollment`, both enrolled-unit columns, both approved-unit columns), unavailable 25, inappropriate 6 (`Course`, `Displaced`, both grade columns). The five transformation-class fields are blocked until the decisions in `next_training_decisions.md` are made. They are not inputs to a new model in this review.

## Timing scenarios

M25 already scored the exploratory gradient boosting classifier after dropping future curricular columns. Those figures use UCI columns the application does not have. They are reference numbers for the source file, not EduPredict performance.

| M25 cut | Predictors | CV macro F1 of the exploratory winner |
| --- | --- | --- |
| Full source file | 36 | 0.7211 ± 0.0153 |
| After first semester, second-semester columns removed | 30 | 0.6888 ± 0.0164 |
| At enrollment, all 12 curricular columns removed | 24 | 0.5766 ± 0.0202 |

The enrollment cut still contains marital status, course, parental background, debtor, tuition, macro rates, and the other unavailable fields. It is not an enrollment model for UCC.

### Enrollment

Decision time: at registration, or any time before a confirmed college grade exists. Registration provides program, year level, and a registration-year value. It does not provide the 24 non-curricular UCI predictors except the proposed scholarship flag and, after a separate date rule, age.

Using any of the 12 curricular columns at this time is temporal leakage. Debtor and tuition stay out as well: they are unavailable, and their source snapshot time is unverified, so the M25 choice to leave them in the enrollment cut is not adopted for EduPredict.

`semesters_completed` is 0 until a grade report is confirmed. `limited_history` is true. The heuristic predictor does not change its score for that flag. The UCI candidate has no such flag. A student with no published profile would currently be scored as scholarship 0 if this candidate were wired up. That wiring is out of scope and would be incorrect.

### Post-first-semester

Decision time: after the first semester's results are confirmed, and before later semesters are known. In the UCI design this is when the six first-semester curricular columns become legitimate, and when the six second-semester columns are still leakage.

EduPredict cannot identify that semester. A confirmed report has a school year and a label such as First or Second. Nothing marks it as the student's first college semester. A student can confirm a later term first. Alphabetical semester order does not recover the academic sequence when Midyear or Summer is present. Missing earlier terms are indistinguishable from a student who has only completed one term: both can show `semesters_completed` of 1 and `limited_history` true.

Even after a term is identified, first-semester grade remains inappropriate as a GWA substitute. Enrolled and approved counts remain blocked on an unverified counting rule. Credited units, evaluation counts, and units without evaluations are unavailable. Second-semester columns remain leakage.

A provisional GWA that omitted INC rows can still be emitted. The incomplete count is not in the feature set.

### Latest confirmed academic data

Decision time: whenever the student requests a prediction, using every confirmed report that has not been superseded. `AcademicSummary` pools those rows into one GWA and one failure count. `PredictionRequester` refreshes `semesters_completed` from that pool.

This is a different feature from both UCI semester grades. The UCI academic block stops at the second semester. A third-year or fourth-year pool includes later performance. Writing that pool into "1st sem (grade)" or "2nd sem (grade)" misstates the timing of the training feature. For an early-warning use, later grades also move the predictor toward the period in which dropout or graduation is already becoming visible. The UCI target is Dropout, Enrolled, or Graduate at the end of the normal course duration. A model trained on first-year curricular fields cannot be fed a multi-year GWA and still be that model.

`limited_history` warns when fewer than two confirmed terms are on file. It does not fill the missing terms, and it does not stop a GWA from being computed on the terms that happen to be present. Absence of history is not a zero failure count in the UCI sense, but a student with no confirmed rows does get `failed_subjects` of 0 and a null GWA. The null GWA cannot enter the UCI pipeline, which rejects missing values. Coding the null as 0 would be an imputation, and it is not proposed.

## Grading-scale incompatibilities

These are separate from missing columns.

- Philippine finals used here run from 1.00 (strongest) to 3.00 (minimum pass) and 5.00 (fail). UCI curricular grades are higher-is-better on 0–20. A large local number is a failure. A large UCI number is a strong grade.
- The local average weights by subject units and drops NSTP, INC, and dropped rows. The UCI field is the source's curricular-unit grade average, with its own inclusions.
- Previous-qualification and admission grades are on 0–200. Neither grade is stored. There is no local 0–200 field to rescale.
- Observed UCI curricular grades include 0. A local numeric final cannot be 0. Zero in the UCI grade column is not a Philippine incomplete.
- Major versus other GWA is a local program-shift indicator. It is not a UCI predictor.

## Fields that must stay out of UCI columns

The application stores employment status, income bracket, household size, internet, device, study space, skill counts, internship counts, and the five draft construct scores. They support the heuristic placeholder. They are not among the 36 UCI predictors. Questionnaire sections A, S, B, C, P, and E are context or employability measures in the scoring PDF. They are not dropout predictors and are not inserted into UCI fields.

## Blockers

1. No UCI predictor is directly usable.
2. The saved candidate depends on a scholarship collapse whose category equivalence was not claimed, and the live builder turns a missing profile into 0.
3. Age cannot be built: birthdate is optional, the stored enrollment year is the registration year, and age is not in the feature contract.
4. First-semester and second-semester identity cannot be built from the grade tables without a new definition of term order and missing history.
5. Curricular grades cannot cross the Philippine and Portuguese scales until a conversion exists. This review does not supply one.
6. The M25 enrollment and post-first-semester scores describe the UCI file with predictors EduPredict does not have.
7. Live database counts were not observed because host `db` was unreachable.

The dropout candidate is not production-ready. The employability candidate is not production-ready.
