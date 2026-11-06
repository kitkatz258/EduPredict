# D1 — UCC academic feature definitions and feasibility

**Status:** research-and-design checkpoint. No student scoring, no `FeatureBuilder` change, no migration, no retraining.  
**Date:** 2026-11-06  
**Branch:** `feature/d1-academic-feature-definitions`  
**Parent:** `d3709a8` (`M43: Load M42 research artifacts through an isolated offline scorer without changing live prediction.`)  
**Q3.1:** not on this branch. Commits `51ce99c` and `a99a016` exist in the repository and are not ancestors of HEAD. `src/cursor/EduPredict_Q3_1_Updated_UX_and_QA.md` is an untracked specification, not a completed Q3.1 result. Questionnaire code was not modified.

This memo separates three kinds of statement:

| Mark | Meaning |
| --- | --- |
| **Verified** | Observed in repository code, schema, the local UCI file, or the UCI dataset record retrieved for this checkpoint. |
| **Proposed** | A recommendation for later review. Not official UCC policy and not an approved mapping. |
| **Unresolved** | Cannot be decided from the repository. Needs UCC confirmation or a new collection. |

The companion matrix and the fail-closed D2 contract are `ml/reports/ucc_feature_and_target_contract_v1.md`. M43 memos remain the 2026-11-02 inventory. This document is the D1 feasibility decision.

Nothing here authorizes writing a UCC grade, scholarship flag, or questionnaire answer into an M42 column.

## 1. What was inspected

Code and schema, read only:

- `grade_reports` / `subject_grades` migrations and `GradeReport`, `SubjectGrade`
- `GradeScale`, `GwaCalculator`, `AcademicSummary`, `GradeReportWriter`, `GradeRowNormalizer`, `GradeReportForm`
- `students`, `institution_students`, `programs` migrations
- `FeatureBuilder`, `FeatureSet`, `PredictionRequester`
- `socioeconomic_profiles`, `SocioeconomicProfile`, `SocioeconomicProfileRequest`, `config/edupredict.php`
- `AssessmentWizard` steps
- `src/docs/ml-feature-contract.md`, `DECISIONS.md`, `KNOWN_GAPS.md`, `M33-RESEARCH-INFERENCE.md`
- M42/M43 reports, `ml/src/m42/compatibility.py`, `ml/src/m43/validation.py`, and the three M42 dropout model cards

No MariaDB, Supabase, or student row was read. No live prediction was requested.

UCI recheck on 2026-11-06:

- Public record: UCI id 697, DOI 10.24432/C5MC89, API `https://archive.ics.uci.edu/api/dataset?id=697`. Variable descriptions quoted below are from that payload. The repository does not store a separate codebook file.
- Local file `ml/data/raw/dropout/data.csv`: 533,230 bytes, 4,424 data rows, SHA-256 `3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae` (same digest as M25/M42). Reloaded in this session; the file was not modified.

| Column | Official description (UCI id 697) | This file |
| --- | --- | --- |
| Curricular units 1st sem (enrolled) | Number of curricular units enrolled in the 1st semester. Integer. No missing values. | min 0, max 26, median 6, all integral |
| Curricular units 1st sem (approved) | Number of curricular units approved in the 1st semester. Integer. | min 0, max 26, median 5, all integral |
| Curricular units 1st sem (evaluations) | Number of evaluations to curricular units in the 1st semester. Integer. | min 0, max 45, median 8, all integral |
| Curricular units 1st sem (credited) | Number of curricular units credited in the 1st semester. Integer. | min 0, max 20, median 0, all integral |
| Curricular units 1st sem (grade) | Grade average in the 1st semester (between 0 and 20). | min 0, max 18.875, median about 12.2857, not integral |
| Curricular units 2nd sem (enrolled) / (approved) | Number enrolled / approved in the 2nd semester. | enrolled max 23 median 6; approved max 20 median 5; both integral |
| Curricular units 2nd sem (without evaluations) | Official text still says “in the 1st semester” and spells “evalutions”. Column name says 2nd. Not relabeled. | integer count, not a grade token |
| Scholarship holder | `1 – yes`, `0 – no`. Integer. Dataset reports no missing values. | only `0` and `1` |
| Target | Dropout, enrolled, or graduate at the end of the normal duration of the course. | not a UCC field |

A median of 6 enrolled curricular units, stored as integers up to 26, is a **count of units/courses**. It is not a sum of Philippine credit weights. `subject_grades.units` is `decimal(4,2)`. M42.1 said a sum of those units was the derivable enrolled feature. That sentence is withdrawn here: the sum is a different measurement, even when the arithmetic is possible.

Zero is a real enrolled/approved value on the UCI file (the minimum is 0). A missing UCC term must not be stored as 0.

M42 artifacts that would consume academic or scholarship inputs, all with `ucc_inference: blocked`:

| Artifact | Columns | Timing on the card |
| --- | --- | --- |
| `uci_first_sem_units_m42` | 1st sem enrolled, 1st sem approved | `after_first_semester` |
| `uci_two_sem_units_m42` | those two, plus 2nd sem enrolled and approved | `after_second_semester` |
| `uci_scholarship_only_m42` | `Scholarship holder` | `after_published_profile` |

Grade, credited, evaluations, and without-evaluations columns are not inputs to these three artifacts. `ml/src/m43/validation.py` rejects UCC keys, extra columns, and UCI grade columns.

## 2. Task A — what “first” and “second” degree term would mean

**Proposed definition, not UCC policy.** “First degree semester” means the first academic term the student actually took in the current UCC program. “Second degree semester” means the next regular term in that same program. Neither phrase means:

- `grade_reports.semester = First` or `Second` (those are semesters of a school year)
- the earliest report currently stored
- `students.year_level`
- `students.enrollment_year`
- `AcademicSummary.semesters_completed`

**Verified:** no table stores a degree-term index, an admission type, a leave of absence, a transfer, or the program a grade report belonged to. `programs` has a name and a code only. There is no curriculum or subject-catalog table.

| Case | What the schema can show | Verdict |
| --- | --- | --- |
| Freshman whose only upload is AY 2025–2026, semester First, and `year_level` is 1 | A confirmed report with those labels, plus a registration year level | **Unresolved.** Year level is copied from `institution_students` at account creation and is not recomputed from grades. The upload can still be a later term that was typed as First. |
| Student uploads Second or Midyear before any First | Those labels on a current report | **Verified** that the strings can be stored. **Unresolved** as degree-term position. |
| Transferee registered as year 3 | `year_level = 3` and whatever reports they upload | **Unresolved.** No prior-institution or credited-unit field. Application mode on UCI includes Transfer and Change of course; UCC stores neither code. |
| Late upload of a third-year report | One current report whose school year is recent | **Verified** that this can be the only current report. Treating it as degree term 1 would be false and can leak a later term into a first-semester model. |
| Repeater | The same `subject_code` on more than one current report, or twice in one report | **Verified** that neither a unique attempt index nor a “latest attempt” rule exists. Duplicate codes in one paste are a warning, not a rejection (`GradeRowNormalizer`). |
| Shifter | `students.program_id` is the current program | **Unresolved.** Grade reports have no `program_id`, so earlier terms cannot be separated from the new program. |
| LOA and return | Nothing | **Requires new collection.** |
| Academic-year sequence | `school_year` is a string, usually `YYYY-YYYY`. The form offers eight recent years and can keep a parsed year outside that list. Reports sort by `school_year`, then by the semester **string**, then id. | **Verified** sort. Alphabetical semester order is First, Midyear, Second, Summer. That is not a calendar and not degree-term order. A missing year is invisible. |
| Midyear / Summer | Form choices are First, Second, Midyear. The paste parser can also set Summer. The database column is a free string up to 32 characters. | **Verified** storage. **Proposed:** Midyear and Summer are not UCI 1st or 2nd semester columns. Whether they count as degree terms at UCC is **unresolved**. |
| Incomplete history | `semesters_completed` is the count of distinct current `school_year\|semester` pairs, including Midyear and Summer | **Verified** that two uploaded labels can clear `limited_history` (threshold 2) without being degree terms 1 and 2. Absence of an earlier term is not distinguishable from a student who has only completed one term. |

`enrollment_year` is the Asia/Manila calendar year of app registration (`StudentRegistrationService` and `StudentAccountProvisioner`), not the year the student entered UCC.

**Proposed** minimal fields before term identity is auditable. These are not a migration:

- `degree_term_index` — positive integer, entered explicitly, never inferred
- `term_type` — `first`, `second`, `midyear`, `summer`, or `other`
- `school_year` and `semester` kept as labels
- `program_id` of the program **at that term**
- `admission_context` — freshman, transferee, shifter, returnee, or unknown
- provenance — who attested the index, when, and from which record
- a flag that earlier terms are known to be absent, which must not default to “history is complete”

Until that attestation exists, first-semester and second-semester model inputs **fail closed**. `ml/src/d1/contract.py` `select_degree_term` returns `missing` when the index is absent, including when `semester` is First and `year_level` is 1. Two records with the same index are `ambiguous`. Midyear and Summer are `not_a_uci_semester`. A present term 2 does not invent term 1 and does not authorize second-semester columns (`assert_source_timing`).

## 3. Task B — course counts, credit units, and grade tokens

### 3.1 The same rows produce different numbers

Illustrative rows, not a student record. Units are the values UCC subject rows store. Status uses the application token rules in `GradeScale`.

| Code | Units | Final | Application status |
| --- | --- | --- | --- |
| CCIS101 | 3.00 | 1.50 | passed (numeric ≤ 3.00) |
| CCIS102 | 3.00 | 5.00 | failed |
| NSTP1 | 3.00 | 1.75 | passed, and excluded from GWA by the `NSTP` prefix |
| PATHFIT1 | 2.00 | INC | incomplete; PATHFIT is not in the GWA exclusion list |
| CCIS103 | 1.00 | DRP | dropped |

| Measurement | Result | What it is |
| --- | --- | --- |
| Subject-row count | **5** | courses on the report |
| Sum of `units` | **12.00** | credit-unit total |
| Passed subject-row count, NSTP included | **2** | CCIS101 and NSTP1 |
| Sum of units on those passed rows | **6.00** | still not a course count |
| Passed subject-row count, NSTP excluded | **1** | CCIS101 only |
| Units that enter `GwaCalculator` | **6.00** | CCIS101 and the failed CCIS102. INC, DRP, and NSTP stay out of the average. The failure still increments `failedCount`. |

A second illustration: a 3.00 lecture and a 1.00 laboratory are **2** courses and **4.00** credit units. Both can be passing. Equality of the two numbers is a coincidence, not a definition.

`operational_counts` computes the row count and the credit sum only when every inclusion flag is explicit. The default policy raises `UnresolvedAcademicRule`. `as_uci_columns()` always raises. The all-rows policy used in tests is illustrative. It is not a UCC rule.

### 3.2 Local handling that is verified in the application

These are EduPredict rules. `KNOWN_GAPS.md` already says the UCC portal appears to count INC as 4.00, so the portal GWA can differ. That portal behavior was not re-observed here and is not adopted as policy.

| Situation | What the code does | UCI consequence |
| --- | --- | --- |
| Current grades | `GradeReport::scopeCurrent`: `status = confirmed` and `superseded_at` is null. Confirming a replacement stamps the previous current row for that `school_year` + `semester`. | **Verified** filter for any future local count. Drafts and superseded versions are not “the term.” There is still no degree-term index. |
| Numeric pass / fail | Scale 1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00, 5.00. Passing maximum 3.00. Failure is 5.00 or remark `FAILED`. Lower is better. | **Incompatible** with UCI “approved” and with the 0–20 higher-is-better average. No conversion is defined. |
| INC | Token `INC`, `is_incomplete` true, not a failure, omitted from GWA numerator and denominator. Any INC makes the GWA provisional. | **Unresolved** for a course count: enrolled-but-not-passed, excluded, or a local flag only. Do not recode INC as approved 0, as 5.00, or as “without evaluations.” |
| DRP / W / WITHDRAWN | Dropped, not a failure, out of GWA. | **Unresolved** whether the row stays in an enrolled count. |
| UD / unofficially dropped | Not a failure, out of GWA, counted with dropped subjects by `GwaCalculator` because `UD` is in `dropped_tokens`. | **Unresolved** as its own count. The D1 helper keeps the status separate so a later policy can choose; the live GWA path does not. |
| Failed subject | Stays in the GWA when it has units and is not NSTP, INC, or dropped. | A failure count is not “enrolled minus approved.” |
| NSTP prefix | Excluded from GWA after a failed NSTP row has already incremented the failure count. | GWA exclusion is not an enrollment rule. Include versus exclude is **unresolved**. |
| PATHFIT | Not excluded. | Counts in GWA when the grade is numeric. |
| Repeats | Every current row is pooled by `AcademicSummary`. No distinct-code collapse. | **Unresolved.** `repeat_mode=distinct_code` raises when the same code is included twice. `every_row` counts both and is still only a local count. |
| Units missing or non-numeric | Confirmation expects a numeric unit value; the helper refuses a null unit. | Missing units are not zero. |
| Evaluations | `midterm_grade` and `final_exam_grade` are nullable strings (grade tokens), at most two per subject. | **Incompatible.** Filling both tokens would invent 0–2, not the UCI count whose median is 8 and maximum is 45. |
| Credited / transfer / equivalency | No column. | **Unavailable.** Do not write the UCI median 0 into credited. |
| Without evaluations | INC, DRP, and UD are status tokens. | **Incompatible** with the UCI integer. The 2nd-semester description still says 1st semester. |

**Locally computable, after an explicit inclusion policy:** a subject-row count and a credit-unit sum on current rows of one already identified report.  
**Not established:** that either number is UCI enrolled or approved. Inference that needs those columns stays blocked.

## 4. Task C — scholarship yes, no, and unknown

**Verified inventory**

| Stored value | Config label | Where it can exist |
| --- | --- | --- |
| `none` | No scholarship | Encrypted `socioeconomic_profiles.scholarship_status` |
| `partial` | Partial scholarship | same |
| `full` | Full scholarship | same |
| `government` | Government scholarship | same |
| `private` | Private scholarship | same |

There is no stored value named `unknown`, `expired`, `draft`, or `yes`. Draft is `is_draft`, not a scholarship status. Published validation requires one of the five strings. Draft validation allows null (`SocioeconomicProfileRequest::draftRules`). The column is nullable. The value is encrypted. No award date, expiry, sponsor, or source (self-report versus registrar) is stored.

The active assessment does not collect it. `AssessmentWizard::STEPS` is questionnaire, skills, grades, and review. The old `socioeconomic` step aliases to skills. `SocioeconomicProfileRequest` is not referenced by a Livewire component or a route. Seeders and factories can still write the column. Existing ciphertext can remain. `StudentDataExporter` can export the decrypted value to the owner.

`FeatureBuilder` does not read the profile. It sets `scholarshipStatus` to null and `hasScholarship` to false because the property is a PHP bool. `PredictionRequester::snapshot` then sets both snapshot keys to null and lists them in `unavailable_inputs`. The estimate note says discontinued household fields are not treated as no scholarship or as zero. A hardcoded false is therefore **not** scholarship evidence.

UCI `Scholarship holder` is only 0 or 1, with no missing values in this file. Free tuition or local aid is mentioned as a possibility in `DECISIONS.md` (M37) and is not stored.

**Proposed classification, fail closed.** `classify_scholarship` returns a label and `uci_value = None`. `as_uci_binary()` always raises.

| Condition | Label | UCI 0/1 |
| --- | --- | --- |
| No profile row | `missing` | refuse |
| `is_draft` true, even if a status string is present | `draft` | refuse |
| Published and null or blank | `blank` | refuse |
| Published `none` | `reported_none` | refuse. A later collapse to 0 is **unresolved**, not applied. |
| Published `partial`, `full`, `government`, or `private` | `reported_award` | refuse. A later collapse to 1 is **unresolved**. |
| Any other string, including `unknown`, `expired`, `0`, `false`, or `no` | `unrecognized` | refuse |
| `has_scholarship` false with no profile | `missing` | the boolean is ignored |

`reported_none` and `reported_award` are descriptions of a stored self-report category. They are not a verified match to the Portuguese scholarship-holder flag, and they are not registrar evidence. Timing, if a future collector restores the question, is “after a published non-draft answer,” which is the M42 card’s `after_published_profile` only by analogy.

## 5. What M42/M43 can receive

| Source | Usable as an M42 input today? |
| --- | --- |
| A UCI or Pianda column already on the source row, passed to `src.m43` | Yes, inside the isolated loader. That path rejects UCC keys. |
| UCC subject rows, GWA, `major_units`, `semesters_completed`, `year_level`, `enrollment_year` | No |
| UCC `scholarship_status` or `has_scholarship` | No |
| UCC questionnaire means | No. Out of scope for this academic audit; M42.1 already blocked C/P equivalence. |
| A local course count or credit sum from `operational_counts` | No. The helper refuses UCI column names. |

Employability artifacts (`employability_cp_reduced_m42` and the internship expansion) do not take academic-record features. They stay source-native. This checkpoint does not reopen that mapping.

## 6. Outcome labels this repository does not have

Dropout validation against UCC students needs a **later observed status**, not a grade and not a questionnaire score. The UCI target is specifically dropout, still enrolled, or graduate at the end of the normal course duration. A UCC study would need its own definition and its own records, for example registrar enrollment by term (retained, leave, transferred out, withdrawn, completed). Those records are not in this schema. GWA, failed-subject counts, and placeholder risk bands must not be turned into those labels.

Employability validation splits into two claims. Pianda’s saved target is a same-occasion self-perception mean, not a hiring result. UCC section E is a different instrument and is already excluded from the M42 predictors. A claim about later employment needs a follow-up criterion and a source that can support it. No such rows are in the repository. This checkpoint does not synthesize any.

## 7. Confirmation still required

1. Whether degree term 1 restarts for transferees, shifters, and students who return from leave.
2. Whether Midyear and Summer increment a degree-term index, attach to a regular semester, or stay outside it.
3. Whether an enrolled count includes INC, DRP/W, UD, failed rows, NSTP, zero-unit rows, and repeated codes.
4. Whether “approved” for any future local count is the application’s ≤ 3.00 rule, and how that differs from the portal’s INC treatment.
5. Whether a UCC scholarship, a partial award, government or private aid, and free tuition are the same construct as UCI `Scholarship holder`.
6. Who may attest term index and scholarship, and from which office record.
7. The dropout status list and time horizon, and the employability criterion if the claim is later employment rather than self-perceived readiness.

Until those are written down by the responsible UCC office, the implementation contract in `ucc_feature_and_target_contract_v1.md` stays fail closed.
