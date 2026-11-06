# UCC term identity and curricular-count semantics (M43 decision memo)

**Later decision:** D1 (2026-11-06) is the feasibility contract. Read `d1_academic_feature_definitions.md` and `ucc_feature_and_target_contract_v1.md`. This memo stays as the M43 inventory.

**Status:** documentation only. No FeatureBuilder change, no migration, no student scoring.  
**Date:** 2026-11-02  
**Branch:** `feature/m43-research-inference`  
**Purpose:** record why current EduPredict fields cannot be treated as UCI first/second degree-term inputs, and list testable rules that remain **pending human confirmation**.

This memo does not authorize mapping UCC grade rows onto `uci_first_sem_units_m42` or `uci_two_sem_units_m42`.

## 1. What UCI actually stores

Source: UCI dataset id 697, DOI [10.24432/C5MC89](https://doi.org/10.24432/C5MC89), local file `ml/data/raw/dropout/data.csv` (SHA-256 `3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae`), audited in `ml/reports/dropout_data_audit.md` and `ml/api/uci_source_support.json`.

The M42 first-semester candidate uses only:

- `Curricular units 1st sem (enrolled)`
- `Curricular units 1st sem (approved)`

The two-semester candidate adds the matching 2nd-sem enrolled and approved columns. Grade, credited, evaluations, and without-evaluations columns were excluded in M42.

### Count of curricular units, not Philippine credit units

UCI names these fields **curricular units** (Portuguese *unidades curriculares*): a count of courses/modules enrolled or approved in that degree semester. On the local file they are integers.

Observed support on this file (`uci_source_support.json` and a direct reload):

| Field | Min | Max | Median | Type |
| --- | --- | --- | --- | --- |
| Curricular units 1st sem (enrolled) | 0 | 26 | 6 | integer |
| Curricular units 1st sem (approved) | 0 | 26 | 5 | integer |
| Curricular units 1st sem (evaluations) | 0 | 45 | 8 | integer |
| Curricular units 1st sem (credited) | 0 | 20 | 0 | integer |

A median of **6 enrolled courses** in a semester is a course count. A median of 6 Philippine credit units would be an unusually light load; UCC subject rows commonly carry 3.00 credit units each. Evaluations reaching 45 also cannot be “two exam tokens per subject.”

**Do not assume** `SUM(subject_grades.units)` equals UCI enrolled or approved units. That sum is a **weighted credit-unit total** (`decimal(4,2)` on `subject_grades.units`). UCI enrolled/approved are **integer course counts**. Equating them is an unverified transformation.

Evaluated, credited, and without-evaluation UCI fields remain **unavailable**. No 0–20 grade conversion is defined. M42 left those columns out rather than inventing a mapping.

## 2. Why current UCC fields fail first/second degree-term identity

| Local field | What it actually is | Why it is not UCI 1st/2nd sem |
| --- | --- | --- |
| `grade_reports.school_year` | UI string, typically `YYYY-YYYY` from eight recent options | Academic-year label, not a degree-term index. A third-year student can upload `2025-2026` as their first stored report. |
| `grade_reports.semester` | String. Form choices: `First`, `Second`, `Midyear`. Parser can also detect `Summer`. | “First” means first semester of that school year, not term 1 of the degree. Midyear/Summer have no UCI counterpart. |
| `students.year_level` | Copied from `institution_students` at registration | Not recomputed from grade reports. A transferee registered as year 3 still has no stored “degree semester 1.” |
| `students.enrollment_year` | Calendar year of **app registration** in `Asia/Manila` (`StudentRegistrationService`) | A student who enrolled at UCC in 2023 and registers in EduPredict in 2026 is stored as 2026. Not first-degree-term year. |
| `AcademicSummary.semesters_completed` | Count of distinct confirmed `school_year\|semester` pairs | Cardinality of uploaded current reports, not degree progress. Alphabetical order of semester strings is First, Midyear, Second, Summer — not a calendar. |
| `FeatureBuilder` `major_units` / `other_units` | Cumulative GWA units from **all** current confirmed rows | Pooled credit-unit weights, not per-term course counts. |

There is **no** stored degree-semester index. One confirmed later-term report would look like a first term if anyone treated “the earliest uploaded report” or “semester=First” as UCI 1st sem. That would be both a definition error and possible future-term leakage.

`year_level` and `enrollment_year` cannot recover missing earlier terms. They must not be used to fabricate a first-semester history.

## 3. Pass / approval meaning (not verified as UCI “approved”)

Local passing (`GradeScale` / config):

- Numeric finals: 1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00, 5.00. Lower is better.
- Passing maximum: 3.00. Failure: 5.00 or remark `FAILED`.
- INC / INCOMPLETE: no numeric GWA value; `is_incomplete` true; not a failure; GWA becomes provisional.
- DRP / W / WITHDRAWN: dropped; out of GWA; not a failure.
- UD / UNOFFICIAL: unofficially dropped; out of GWA; not a failure.

A **proposed** local “approved course” count (not implemented, **pending human confirmation**) would still not be the Portuguese approval definition. Do not ship it as UCI `approved`.

UCI `approved` stays source-native only until an owner-approved definition exists **and** can fail review.

## 4. Proposed rules that can be tested later (none implemented)

These are proposals. Each is **pending human confirmation**. None is an agent-approved mapping.

### Term identity

| ID | Proposed rule | Why it can fail |
| --- | --- | --- |
| T1 | Require an explicit degree-term key (`degree_term_index`, 1-based) stored when the report is confirmed, not inferred from `semester` or upload order. | Needs a schema/UI change and registrar confirmation of how transfers are numbered. |
| T2 | Refuse UCI 1st-sem scoring unless a confirmed current report is marked degree-term 1 **and** no later term is being treated as term 1. | Correct refusal; still needs the key in T1. |
| T3 | Refuse UCI 2nd-sem columns unless degree-term 2 is confirmed. Using them earlier is leakage (`uci_two_sem_units_m42` timing is `after_second_semester`). | Already true on the source side. |
| T4 | Do not infer term 1 from `year_level == 1` or from `enrollment_year`. | Those fields are registration metadata. |
| T5 | Midyear and Summer are **not** UCI 1st or 2nd sem by default. | **Pending:** exclude, attach to adjacent term, or store as a separate local term type. |
| T6 | A missing earlier term is missing. Do not write enrolled=0 / approved=0 to stand in for “not uploaded.” | Zero is a real UCI value (row 0 on the source file has 0 enrolled and 0 approved). |

### Course count versus credit-unit sum

| ID | Proposed rule | Why it can fail |
| --- | --- | --- |
| C1 | If a local analogue is ever built, count **subject rows** (courses), not `SUM(units)`. | UCC rows are credit-weighted. A 3-unit lecture and a 1-unit lab would contribute 4.00 to a sum and 2 to a count. |
| C2 | Keep credit-unit sums as a **separate** local feature, never written into UCI enrolled/approved columns. | Prevents silent scale mixing. |
| C3 | Credited / evaluations / without-evaluations stay blocked. | No recognition-of-prior-learning column. Midterm and final cells are grade tokens, not evaluation counts. INC/DRP/UD are status tokens, not “without evaluations.” |

### Repeats, NSTP, INC/DRP, superseded reports, program shifts

| ID | Proposed rule | Status |
| --- | --- | --- |
| R1 | Repeats: count distinct `subject_code` in that degree term, or count every attempt? | **Pending human confirmation.** UCI does not document a UCC repeat policy. |
| R2 | Transfers / credit recognition: no local credited-units field. Do not fill UCI credited with 0 because the UCI median is 0. | Blocked. |
| R3 | NSTP: currently excluded from GWA by prefix. Include or exclude in a course count? | **Pending.** GWA exclusion is not a UCI enrolment rule. |
| R4 | INC: incomplete, not unapproved and not failed. | Do not map INC to approved=0 or to a 5.00. **Pending** whether INC is enrolled-not-approved, excluded, or a local-only flag. |
| R5 | DRP / W / UD: not failures; out of GWA. | **Pending** whether they remain in an enrolled count. |
| R6 | Only current confirmed reports (`status=confirmed`, `superseded_at` null) count. Drafts and superseded versions do not. | This already matches FeatureBuilder grade scope. It still does not identify degree term 1. |
| R7 | Program shift: `program_id` can change; grade reports are not tagged with the program they belonged to. | **Pending** whether a shifted student’s earlier terms remain in a degree-term index. |
| R8 | A term is complete only after the student confirms the report, not when a PDF is parsed. | Already true for GWA. Completeness ≠ UCI semester close. |

## 5. Recommended collection design (proposal only)

If CLAS later wants a source-comparable academic progress table, store an explicit, optional degree-term record:

- `degree_term_index` (1, 2, …) confirmed by the student or registrar, never guessed from first upload
- `term_type` (`first`, `second`, `midyear`, `summer`, `other`)
- `school_year`, `semester` kept as labels
- subject **count** and credit-unit **sum** as separate numbers
- pass/fail/INC/DRP/UD kept as local statuses, not recoded into Portuguese approval
- missing earlier terms stored as missing, not zero

No migration is made in M43. FeatureBuilder is unchanged.

## 6. Residual blockers

- No UCC-validated first/second degree-term identity.
- Course count ≠ credit-unit sum.
- Approval, NSTP, repeats, Midyear/Summer, INC/DRP/UD, and program-shift counting are unconfirmed.
- Evaluated / credited / without-evaluation UCI features remain unavailable.
- No Philippine-to-Portuguese grade conversion.
- Source holdout metrics are not UCC validity.

**M43 may score UCI unit models only with source-native UCI columns in the isolated research loader.**
