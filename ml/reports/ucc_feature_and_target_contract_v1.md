# UCC feature and target contract, v1

**Status:** D1 handoff. Proposed rules are not official UCC policy.  
**Date:** 2026-11-06  
**Parent:** `d3709a8` on `feature/d1-academic-feature-definitions`  
**Evidence narrative:** `ml/reports/d1_academic_feature_definitions.md`  
**Code:** `ml/src/d1/contract.py` (pure helpers; no database; no model call)

Compatibility class is one of `direct`, `needs approved rule`, `requires new collection`, or `incompatible`.  
Usability for a UCC student scored by an M42 artifact is one of `usable`, `potentially derivable`, `unavailable`, or `incompatible`.  
`potentially derivable` means a local number can be computed after a written rule. It does **not** mean the number may be placed in the source column.

No M42/M43 dropout or employability input is `direct` or `usable` from current UCC records.

## 1. Fields the application already has

Verified from migrations and services. These are inputs to the placeholder heuristic or to grade display. They are not M42 columns.

| Local field | Verified meaning | M42 usability |
| --- | --- | --- |
| `grade_reports.school_year`, `semester` | Labels. Semester is not a degree-term index. | unusable as term identity |
| `grade_reports.status`, `version`, `supersedes_id`, `superseded_at` | Current rows are confirmed and not superseded. | usable only as a **filter** for a future local count |
| `subject_grades.subject_code`, `subject_name` | One stored course row. Duplicates are allowed. | potentially derivable as a **row count** after an inclusion rule |
| `subject_grades.units` | `decimal(4,2)` credit units | potentially derivable as a **sum**, which is a different feature from the row count |
| `final_grade`, `remarks`, `is_failed`, `is_incomplete` | Application pass, fail, INC, drop, and UD tokens | incompatible with UCI grade and with “approved” until a rule is confirmed |
| `midterm_grade`, `final_exam_grade` | Grade tokens, not evaluation counts | incompatible |
| `is_major_subject` | Defaults false. Splits GWA units. | unavailable as a UCI feature |
| `students.year_level` | Copied at registration. Not recomputed. | unusable as term position |
| `students.enrollment_year` | Calendar year of app registration | unusable as term position or age-at-enrollment date |
| `students.semesters_completed` | Count of distinct current year+semester labels, then copied onto the student | unusable as “two degree semesters” |
| `socioeconomic_profiles.scholarship_status` | Five encrypted labels, or null. Not read by new predictions. | unavailable for scoring; see section 4 |
| Program, curriculum, transfer, LOA, credited units, outcome status | Not stored, except current `students.program_id` | unavailable |

`major_units` and `other_units` on a feature snapshot are cumulative GWA credit units, not per-term course counts.

## 2. M42/M43 academic and scholarship inputs

| Source column | Source meaning | Possible UCC source | Definition that would be required | Timing | Aggregation | Missingness and edges | Class | Usability | Risk if forced |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Curricular units 1st sem (enrolled) | Integer count of curricular units enrolled in degree semester 1. This file: 0–26, median 6. | Subject rows on a report that is **explicitly** degree term 1 | Count rows, not `SUM(units)`. State INC, drop, UD, fail, NSTP, zero-unit, and repeat rules. | Only after that term is confirmed. Leakage at enrollment and if a later upload is treated as term 1. | One term. Not the cumulative GWA pool. | Missing term stays missing. Zero is a real source value, so it cannot mean “not uploaded.” | needs approved rule **and** requires new collection (the term index) | potentially derivable as a local row count; **incompatible** as this column today | Scale mix (12.00 credit units written as “12 courses”); future-term leakage |
| Curricular units 1st sem (approved) | Integer count approved in degree semester 1. This file: 0–26, median 5. | Rows classified passed on that same term | A confirmed pass rule. The app’s ≤ 3.00 rule is not that confirmation. INC is not “not approved” by default. | Same as enrolled, and only with final results | Count of passing rows under the same policy | Do not set approved to 0 because the grade is INC or the term is missing | needs approved rule | potentially derivable only as a labeled local pass count | Portuguese approval is a different construct; Philippine 5.00 is a failure, not a low UCI grade |
| Curricular units 2nd sem (enrolled) and (approved) | The same counts for degree semester 2 | The same, on explicit term 2 | Term 2 must exist. Term 1 must not be inferred from it. | `after_second_semester` on `uci_two_sem_units_m42`. Using the columns earlier is leakage. | Separate from the first-semester pair | A student with only term 2 on file does not have term 1 | needs approved rule and requires new collection | incompatible for the model until both explicit terms exist | Hindsight from a later semester |
| Curricular units 1st/2nd sem (evaluations) | Integer count of evaluations. 1st sem median 8, max 45. Not an M42 input. | None. Midterm and final cells are tokens. | A real evaluation count. Two tokens cannot be added to produce it. | After the term, and still the wrong variable | Not implemented | Must not be manufactured | incompatible | incompatible | Invented 0–2 per subject |
| Curricular units 1st/2nd sem (credited) | Integer count credited that semester. 1st sem median 0, max 20. Not an M42 input. | None | Transfer or equivalency counts from a registrar source | After the term, if the field existed | Not a sum of current-term units | Median 0 must not be copied in for “no column” | requires new collection | unavailable | Silent zeros |
| Curricular units 1st/2nd sem (without evaluations) | Integer count. The 2nd-sem official description still says 1st semester. Not an M42 input. | None. INC/DRP/UD are status tokens. | A count of units that had no evaluations | After the term | Not a status-token count | Do not code INC as this column | incompatible | incompatible | Status tokens relabeled as the UCI count |
| Curricular units 1st/2nd sem (grade) | Average between 0 and 20, higher is better. Excluded by M42. | Philippine GWA, 1.00 best, 5.00 fail, unit-weighted, NSTP/INC/drop omitted, provisional when any INC exists | No conversion is defined. Do not invent one. | Wrong even after the term if the average is cumulative or from another term | Unit-weighted local GWA is not this average | Portal INC-as-4.00 is an open registrar question, not a license to convert | incompatible | incompatible | Direction and scale reversal |
| Scholarship holder | 0 or 1, yes or no, no missing values in the UCI file | Encrypted five-way status, only if a published profile exists | Owner definition of holder, plus a missing-data rule that refuses unknowns | After a published, sourced answer. The current assessment does not ask. | Not a sum | Missing, draft, blank, unrecognized, and `has_scholarship=false` stay unknown | needs approved rule | unavailable for the model | False or null becoming 0 |
| Target | Dropout, Enrolled, or Graduate at the end of normal course duration | None | A UCC status and time horizon from registrar records | The outcome time, which is after the predictors | Not applicable | Do not build labels from GWA, failures, or questionnaire answers | requires new collection | unavailable | Training on a proxy outcome |
| Employability target (Pianda EVS / UCC E) | Pianda is a same-occasion survey mean. UCC E is a different 10-item section. | No later employment record | Choose self-perceived readiness or later employment and do not swap the names | Same-occasion versus follow-up are different claims | Not an academic-unit feature | No pilot rows in this repository | requires new collection for a hiring claim; C/P and E stay blocked for the source model | unavailable as a UCC employment label | Calling a survey mean a hiring probability |

M42 model cards that match the first four academic rows and the scholarship row stay `ucc_inference: blocked`. `src.m43` may score them only with the source column names and in-range source values.

## 3. D2 implementation contract

D2 is not started. The helpers below are the only implementation added in D1. They are safe to keep because they cannot emit a model payload.

### 3.1 Safe to call

| Function | Contract |
| --- | --- |
| `operational_counts(rows, policy)` | Returns `course_row_count` and `credit_unit_sum` only when every inclusion flag and `repeat_mode` are set. `uci_equivalent` is false. `as_uci_columns()` raises `UnresolvedAcademicRule`. |
| `CountPolicy()` with any `None` | Raises. The illustrative all-rows policy in the tests is not approved. |
| `select_degree_term(records, index)` | Uses only an explicit `degree_term_index`. Status `missing`, `ambiguous`, or `not_a_uci_semester` is a hard stop. `as_uci_columns()` raises even when status is `explicit`. |
| `assert_source_timing` | `after_first_semester` requires explicit index 1. `after_second_semester` requires explicit indexes 1 and 2. Success does not authorize values. |
| `classify_scholarship` | Returns `missing`, `draft`, `blank`, `reported_none`, `reported_award`, or `unrecognized`. `uci_value` is always `None`. `as_uci_binary()` raises. A boolean `has_scholarship` is ignored. |
| Current-report rule, already in Laravel | Any future local count uses confirmed, not superseded reports. This rule is verified application behavior. It does not identify degree term 1. |

### 3.2 Fail closed until a named UCC confirmation is cited in `DECISIONS.md`

- Do not infer degree term 1 or 2 from `semester`, upload order, `year_level`, `enrollment_year`, or `semesters_completed`.
- Do not add `degree_term_index` in a migration that backfills it.
- Do not treat Midyear or Summer as UCI semester 1 or 2.
- Do not sum `subject_grades.units` into `Curricular units … (enrolled)` or `(approved)`.
- Do not count midterm and final tokens as evaluations.
- Do not write 0 for a missing term, missing units, missing credited units, or missing scholarship.
- Do not map `none` / `partial` / `full` / `government` / `private` onto 0/1.
- Do not change `FeatureBuilder`, prediction snapshots, `PREDICTOR_DRIVER`, M33 routes, M42 joblib files, or questionnaire scoring.
- Do not connect `operational_counts` to `src.m43` or to `HttpResearchInferenceClient`.
- Do not synthesize dropout or employment labels.

### 3.3 What a later approved collector would have to store

Listed so D2 does not invent a thinner substitute. Not approved for implementation now.

- Explicit degree-term index, term type, school year, semester label, program at that term, attestor, and timestamp
- Separate stored course-row count and credit-unit sum, each with the inclusion policy version
- Pass, fail, INC, dropped, and unofficially dropped counts kept as local statuses
- Scholarship status plus source and “unknown” as a real state, distinct from `none` and from a missing form
- Outcome status from an authoritative enrollment or completion record, and a separate employability criterion if the claim is employment

## 4. Scholarship decision used by the contract

| State | Evidence in this repo | Contract label | Allowed UCI value |
| --- | --- | --- | --- |
| No profile | Row absent | `missing` | none |
| Draft | `is_draft` true | `draft` | none |
| Published empty | nullable column | `blank` | none |
| Published `none` | config label “No scholarship” | `reported_none` | none until confirmation |
| Published award category | `partial`, `full`, `government`, `private` | `reported_award` | none until confirmation |
| Expired, unknown, free tuition | no column | `unrecognized` or unavailable | none |
| Builder `hasScholarship: false` | hardcoded, then snapshot null | not evidence | none |

## 5. Worked fixture the tests lock

Five rows: 3.00 passed, 3.00 failed, 3.00 NSTP passed, 2.00 INC, 1.00 DRP.

| Policy | Course rows | Credit-unit sum | Passed rows | Passed credit units |
| --- | --- | --- | --- | --- |
| Every row, all statuses, NSTP included | 5 | 12.00 | 2 | 6.00 |
| Same, NSTP excluded | 4 | 9.00 | 1 | 3.00 |
| Flags left unset | refused | refused | refused | refused |

Lecture 3.00 plus laboratory 1.00, both passing: 2 courses and 4.00 credit units.

Both fixtures refuse a UCI dictionary. Scholarship fixtures for missing, draft, blank, `none`, `government`, `unknown`, `0`, and `expired` refuse 0 and 1. `has_scholarship=False` without a profile stays `missing`.

## 6. Tests and checks in this checkpoint

Ran, from `ml/`, with the existing virtualenv:

```bash
PYTHONPATH=. .venv/bin/python3 -m pytest tests/test_d1_academic_contract.py -q
```

Result on 2026-11-06: **21 passed**.

Also ran a read-only reload of `ml/data/raw/dropout/data.csv` for the integer ranges in the decision memo. The file hash matched `3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae`.

Not run, on purpose: PHPUnit, `migrate:fresh`, MariaDB or Supabase reads, FastAPI, `src.run_m42`, `src.m43` scoring, browser checks, and any student prediction. No UI behavior changed, so there was no browser flow to exercise. Shared databases were not reset. Nothing was pushed, merged, or deployed.
