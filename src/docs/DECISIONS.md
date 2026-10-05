# EduPredict implementation decisions

One line per decision. Record choices made where the spec is silent.

- M0: Installed Livewire 4.4 (latest stable compatible with Laravel 12) as class-based components; did not pin to v3.
- M0: Demo table lists `users` until domain tables exist in later milestones; it is a public development page at `/demo/table`.
- M0: Application timezone is `Asia/Manila`; currency is PHP (no money library yet).
- M0: Guest welcome page is a branded landing with a non-functional sign-in card until Breeze auth lands in M1.
- M0: Chart.js is imported in `resources/js/app.js` as `window.Chart`; Alpine is not installed separately.
- M0: Uploaded grade files will be deleted after confirmation (recorded here as required by the spec; implemented in M3).
- M1: Added `users.must_change_password` in the role-fields migration so M2 staff accounts can force a password change without another schema edit.
- M1: Seeded students use student numbers `SYN-####` and emails `syntheticN@edupredict.test`; they are synthetic demo data, never production records.
- M1: Demo account password is `Password123!` (development only).
- M2: Student self-registration requires an exact match on student number and program, and a case-insensitive match on first and last name, against `institution_students`.
- M2: Staff accounts are created with a generated temporary password and `must_change_password` until the first successful change.
- M2: Dean creation requires `college_id`; department-head creation requires `program_id`; faculty program is optional.
- M2: Adviser assignment uses one faculty picker plus a multi-select student list (one checked student is single assignment; several is bulk).
- M2: CSV import keeps valid rows and reports invalid rows instead of aborting the whole file.
- M2: Administrators cannot deactivate their own account.
- M3: Grade-report `source` values are `manual`, `pasted`, `pdf_text`, `grid_ocr`, `generic_ocr`, or `ai_extracted` (spec listed only manual|ai_extracted).
- M3: Dropped (DRP/W) rows are excluded from GPA; INC uses `grades.inc_gpa_weight` (default 4.00, confirm with registrar); NSTP prefixes are excluded; PATHFIT counts.
- M3: Confirmed grade reports may be deleted by the student and re-entered; a second confirmed report for the same term is rejected.
- M3: `is_major_subject` defaults to false until a program curriculum list exists.
- M3: Real portal samples live in gitignored `cursor/samples/` as `grade_sample_a.png`, `grade_sample_b.png`, and `grade_sample_c.pdf` (the spec’s `Screenshot_2026-10-05_*` names are accepted as alternates). OCR descriptions stay uppercase as printed; program text stays the full “Bachelor of Science in Information Systems” heading. OCR tests skip only when those files or python/tesseract are missing.
- M4: Socioeconomic and skills rows stay readable only by the owning student. Staff do not get the raw income or skills forms.
- M4: Completeness is an equal split of academic (a confirmed grade report), socioeconomic (saved and not a draft), and skills (saved and not a draft). An empty skills list can still be marked complete.
- M4: Skills are entered one item per line; paired lists use `name | detail`. No new tables — the M1 socioeconomic and skills tables already match the spec, and socioeconomic columns stay encrypted.
- M5: Seeded questionnaire items stay `is_draft=true` and `is_active=true`. Students answer every active item; the draft flag marks the research scale, and the non-clinical footer always shows.
- M5: Procrastination scores rise with delaying behavior. The other four constructs rise with the positive behavior. Reverse items use `6 - value`. Construct score is `(mean - 1) / 4 * 100`.
- M5: Retakes insert a new response. `FeatureBuilder::build()` uses the latest submission's construct scores.
- M5.5: Draft socioeconomic and skills rows, and unconfirmed grade reports, are omitted from `FeatureSet`. Confirmed-term count from `AcademicSummary` decides `limited_history`, not the denormalized `students.semesters_completed` column.
- M5.5: Placeholder weights live in `HeuristicPredictor`. Household size and living arrangement are snapshotted and not scored. Income and employment use small weights. `+` helps the student and `-` hurts, including on dropout where a higher probability is `-`.
- M5.5: `http` and `onnx` predictor drivers throw until a later phase. The placeholder version string is the `HeuristicPredictor::MODEL_VERSION` constant.
