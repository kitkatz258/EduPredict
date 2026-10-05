# 01 – EduPredict: Initial Project Build

> **Instructions for Cursor.** Read this whole file first, then build the project **milestone by milestone, in order (M0 → M12)**.
> After EACH milestone: (1) run `docker compose exec app php artisan migrate:fresh --seed`, (2) run `docker compose exec app php artisan test`,
> (3) fix failures, (4) `git add -A && git commit -m "M<n>: <title>"`. Do not skip ahead. Do not stop to ask questions; where this spec is silent,
> choose the simplest reasonable option and record it in `docs/DECISIONS.md` (one line per decision).
>
> Environment rules are in `cursor/rules/edupredict.mdc`. Paths below are relative to the Laravel root (`src/`).
> The Laravel 12 project and Docker (app + MariaDB 11.4 + phpMyAdmin) already exist and run. Do not re-create them.

---

## 1. Project summary

**EduPredict: Machine Learning for Student Employability and Dropout Prediction** is a web-based predictive analytics system built for
the University of Caloocan City (UCC) as the testing context, and designed to be adaptable to other institutions later.

It analyzes a student's **academic, socioeconomic, behavioral, and skills/experience** data to:
1. estimate an **employability score** (percentage),
2. classify **dropout risk** as low / moderate / high (with a lower-confidence flag for students with limited academic history),
3. show a **factor-based program-shift indicator** (program-fit concern vs. broader disengagement),
4. show the **contributing factors** behind each result (interpretability),
5. generate **career matches** grounded in the Philippine Standard Occupational Classification (PSOC) with system-computed compatibility scores and AI-phrased explanations,
6. recommend **institutional actions** for moderate/high-risk students from a predefined intervention list, phrased by an AI API constrained to that list, with a rule-based fallback.

Predictions are advisory. Faculty and department staff review them; nothing is automated. The system never makes admission, dismissal, employment,
academic-standing, or disciplinary decisions, and it is not a clinical or diagnostic tool.

## 2. IMPORTANT: the ML model is plugged in later

The trained models (Python, scikit-learn, trained on separate historical datasets) will be added in a later phase, after consultation with the adviser.
**For this build, implement the entire application end-to-end with a clearly-labelled placeholder predictor** so every screen, dashboard and flow works.

Design it so swapping in the real model touches ONE place:

- `app/Contracts/PredictorInterface.php`
  - `predictEmployability(FeatureSet $f): PredictionResult`
  - `predictDropout(FeatureSet $f): PredictionResult`
- `app/Services/Prediction/HeuristicPredictor.php`: the placeholder. Transparent weighted rules over the features (e.g. GWA, failed subjects, scholarship,
  procrastination, internships, certifications). It MUST return `model_version = "placeholder-heuristic-v0"` and a list of **contributing factors**
  (feature name, human label, direction `+`/`-`, weight/magnitude). Scores are deterministic (same input → same output).
- `config/edupredict.php`: `predictor` driver (`heuristic` now; `http` and `onnx` reserved for later). Bound in a service provider via `.env` `PREDICTOR_DRIVER=heuristic`.
- `app/Services/Prediction/FeatureBuilder.php`: the **only** place that turns a student's DB records into a `FeatureSet` (a typed DTO).
  Document every feature (name, type, allowed values, source table/column, which model uses it) in `docs/ml-feature-contract.md`.
  Do NOT try to mirror any public dataset's columns now; the mapping will be decided when the models are trained.
- `PredictionResult` DTO: `score` (0–100 for employability; probability + class for dropout), `risk_level` (low|moderate|high, dropout only),
  `confidence` (normal|low), `factors[]`, `model_version`.
- Show a small, unobtrusive "Model: placeholder-heuristic-v0" label in the results footer so nobody mistakes the placeholder for the final model.
- Live system data is **never** used to automatically retrain models. Do not build any retraining feature.

## 3. Tech stack and conventions

- Laravel 12, PHP 8.3, MariaDB 11.4 via Docker (MySQL-compatible; keep `DB_CONNECTION=mysql`; do NOT use MySQL-8-only or MariaDB-only SQL features, keep migrations portable), Blade + Tailwind CSS (Vite) + **Livewire** (latest stable release compatible with Laravel 12, `composer require livewire/livewire`), Chart.js for charts.
- **Livewire rules (follow strictly):**
  - Use **class-based Livewire components** (`app/Livewire/...` + Blade view). Do NOT use Volt / functional single-file components.
  - **Livewire bundles Alpine.js. Do NOT install or import Alpine separately.** If Breeze (or anything else) adds `import Alpine from 'alpinejs'` / `Alpine.start()` to `resources/js/app.js`, remove it, otherwise Livewire throws a "multiple instances of Alpine" error. Use Livewire's bundled Alpine (`x-data`, `x-show`, etc.) for small client-side behavior.
  - **Do NOT install Yajra DataTables, jQuery, or any other table/JS library.** All lists use the single shared table pattern below.
  - Use Livewire for: all data tables, the multi-step student wizard (with draft saving), the editable grade-extraction review table, live search/filter, the prediction-request button (with loading state), and modals. Plain Blade controllers are fine for simple pages.
  - Authorization must be enforced **inside every Livewire component** (mount + each action via Policies/`authorize()`), not just by route middleware. Livewire actions are callable by the client, so never trust public properties for IDs; re-verify scope on every query and action. Add tests that call Livewire actions as an out-of-scope user and expect a 403.
- **One shared table pattern:** build a reusable base Livewire table (e.g. `app/Livewire/Tables/BaseTable.php` + a Blade partial) providing: debounced search, sortable columns, filters (risk level, year level, program, etc.), pagination (use `WithPagination`, Tailwind pagination view), per-page selector, empty state, loading state, and scoped base query supplied by each subclass. EVERY list in the app (advisees, department students, users, institution students, audit log, PSOC, interventions, questionnaire items) must extend/reuse it. Do not hand-roll a different table per page.
- Charts: Chart.js initialized from Livewire-rendered Blade (use `wire:ignore` on chart containers and dispatch browser events to update data).
- Authentication: use Laravel Breeze (**Blade stack, not the Livewire/Volt stack**) if it installs cleanly on Laravel 12, then remove its Alpine import (see Livewire rules); otherwise hand-roll with Laravel's built-in auth. Email+password login.
- Roles: a `role` column on `users` (enum: `student`, `faculty`, `department_head`, `dean`, `administrator`). Enforce with **Policies + a role middleware + query scopes**.
  Never rely on hiding links alone; every controller/query must enforce scope server-side.
- Use Form Requests for validation, Eloquent relationships, resource controllers, service classes for business logic (controllers stay thin).
- Use migrations + factories + seeders for everything; the app must be fully reproducible with `migrate:fresh --seed`.
- Tests: use the framework already installed (PHPUnit default). Write feature tests for every role-access rule and for each prediction flow.
- Code quality: strict types where reasonable, meaningful names, short comments on non-obvious logic, no dead code.
- Timezone `Asia/Manila`. Currency `PHP`.

## 4. Roles and access scope (the most important rule set)

| Role | Can see / do |
|---|---|
| **Student** | Own profile, grades, questionnaire, skills; request new prediction; see own results, factors, history, career matches. Cannot see other students. |
| **Faculty** | Only their **advisees** (students whose `adviser_id` = this faculty): predictions, factors, recommended actions, history. |
| **Department Head** | Only students of **their program**: individual results + aggregated analytics for that program. |
| **Dean** | All programs within **their college**: aggregated analytics per program and college-wide risk/performance trends (aggregated, not a browse of every individual unless trivially cheap and still scoped to the college). |
| **Administrator** | Institution-wide analytics; create/manage Faculty, Department Head, Dean, and additional Administrator accounts; manage colleges/programs, the institution student list, adviser assignments, PSOC data, intervention list, questionnaire items. |

Write one feature test per row proving a user **cannot** access data outside their scope (URL tampering, ID guessing, API endpoints).

## 5. Data model (create migrations, models, factories)

Use sensible indexes and foreign keys. Suggested tables (adjust names if needed, record changes in `docs/DECISIONS.md`):

- `colleges` (name, code)
- `programs` (college_id, name, code)
- `users` (name, email, password, role, college_id nullable, program_id nullable, is_active, consented_at nullable, last_login_at)
  - Dean → `college_id`; Department Head → `program_id`; Faculty → optional program; Student → via `students`.
- `institution_students` (the **institution-provided student list**: student_number, last_name, first_name, program_id, year_level, birthdate or email-match key, is_registered)
- `students` (user_id, student_number, program_id, year_level, adviser_id → users, enrollment_year, semesters_completed, consent_version)
- `grade_reports` (student_id, school_year, semester, source `manual|ai_extracted`, status `draft|confirmed`, original_file_path nullable, confirmed_at)
- `subject_grades` (grade_report_id, subject_code, subject_name, units, midterm_grade nullable, final_exam_grade nullable, final_grade, remarks (PASSED|FAILED|INC|DRP|...), is_failed, is_major_subject bool, needs_review bool). Do NOT store faculty names or section.
- `socioeconomic_profiles` (student_id, household_income_bracket, household_size, scholarship_status, employment_status, living_arrangement, resource_access fields e.g. internet/device/study space). Use Laravel `encrypted` casts on sensitive columns.
- `skills_experiences` (student_id, technical_skills json, certifications json, internships/OJT json, projects json, work_experience json)
- `questionnaire_items` (construct: study_habits|time_management|motivation|procrastination|engagement, text, reverse_scored bool, is_active, is_draft bool)
- `questionnaire_responses` (student_id, submitted_at) + `questionnaire_answers` (response_id, item_id, value 1–5)
- `predictions` (student_id, requested_by, model_version, employability_score, dropout_probability, dropout_risk, confidence `normal|low`,
  program_shift_flag `none|program_fit|disengagement|mixed`, factors json, feature_snapshot json (de-identified), created_at). **Never update; always insert (history).**
- `psoc_occupations` (psoc_code, title, major_group, description, skill_tags json, related_program_codes json)
- `career_matches` (prediction_id, psoc_occupation_id, compatibility_score, explanation, explanation_source `ai|template`)
- `interventions` (code, title, description, targets_factor, min_risk_level) the **predefined intervention list**
- `recommended_actions` (prediction_id, intervention_id, phrased_text, phrasing_source `ai|rule_based`, reviewed_by nullable, reviewed_at nullable, reviewer_note nullable)
- `consents` (user_id, version, accepted_at, ip_address)
- `audit_logs` (user_id, action, subject_type, subject_id, meta json, ip, created_at)
- `notifications` (use Laravel's built-in database notifications)

## 6. AI API integration (OpenRouter free tier by default, optional at runtime)

The team has **no budget for paid AI**, so the default provider is **OpenRouter** using models with the `:free` suffix.
Create `app/Contracts/AiClientInterface.php` and ONE concrete driver, `app/Services/Ai/OpenRouterClient.php`, using Laravel's `Http` facade against OpenRouter's
OpenAI-compatible endpoint (`POST {AI_BASE_URL}/chat/completions`, `Authorization: Bearer {AI_API_KEY}`).
Configure via `.env` (do not hardcode any model name):
`AI_PROVIDER=openrouter`, `AI_BASE_URL=https://openrouter.ai/api/v1`, `AI_API_KEY=`, `AI_MODEL=` (text, a `:free` model),
`AI_VISION_MODEL=` (image/PDF extraction; may be empty), `AI_FALLBACK_MODELS=` (comma-separated list tried in order when the main model is rate-limited, removed, or returns invalid output), `AI_TIMEOUT=30`.
Adding another provider later must be a single new class.

**Free-tier constraints that MUST shape the implementation** (free models are limited to roughly 20 requests/minute and about 50 requests/day per account, and models can be rotated out without notice):
- **Minimize calls.** One request per prediction for ALL career explanations (return a JSON array for the top 5 matches), and one request per prediction for action phrasing. Never one call per item.
- **Cache** AI outputs (by a hash of the de-identified input) so repeating the same prediction does not spend quota. Add a daily call counter and stop calling the AI (use fallbacks) when a configurable `AI_DAILY_LIMIT` (default 40) is reached.
- On HTTP 429 / 5xx / timeout / invalid JSON: try the next model in `AI_FALLBACK_MODELS` once, then fall back to the rule-based/manual path. Never block a page or crash on an AI failure; show a small "AI unavailable, using standard text" note.
- **Automated tests must NEVER call the real API.** Use `Http::fake()` everywhere.
- **Privacy on free models:** free models may log prompts. Only de-identified data is ever sent: aggregated features for explanations/actions, and for grade extraction only **text lines of subject rows** (never the image/PDF, never name/student-number/header lines; see feature 1). A short on-screen notice explains this.
- Grade extraction is ON by default and works without any AI (see feature 1). The AI step is a last-resort fallback, switchable by an admin setting `ai_extraction_fallback_enabled` (default on).

**The app must work fully with `AI_API_KEY` empty**: every AI feature has a rule-based/manual fallback and the UI shows a small note when the fallback was used.

Three AI features:
1. **Grade-report extraction (hybrid pipeline, accuracy-first).** Goal: turn whatever the student provides into clean rows (subject code, description, units, midterm, final exam, final grade, remarks) shown in an editable review table. The AI is NOT the primary extractor. Input paths, most reliable first:
   1. **Paste from portal (primary, no AI, no OCR).** A textarea where the student pastes the grades table copied from the school portal (copying an HTML table yields tab-separated text with columns intact). Parse deterministically.
   2. **PDF with a real text layer:** `pdftotext -layout`, then parse the same way. **IMPORTANT: a PDF made by "printing a screenshot" has NO text layer** (verified on a sample: `pdftotext` returns nothing and `pdfimages -list` shows one embedded image). If extracted text is empty or has no recognizable subject rows, extract the page image (`pdfimages -png`, or `pdftoppm -r 200 -png`) and use path 3.
   3. **Image / screenshot / image-only PDF: GRID-AWARE CELL OCR (this is the key finding).** Plain whole-image Tesseract is NOT reliable on these tables (verified: `1.25`→`HE25)`, `1.75`→`75`, `3`→`3}`, `PR 002`→`PROO2`, `CCS 107`→`CCSHO7:`). What works (verified 23/23 rows correct on 3 samples, and GPA/semester/program read correctly): detect the table grid, crop **each cell**, upscale 3x, binarize (threshold ~150), pad, and OCR it with a **per-column character whitelist** (digits only for #/units; `0-9 . INCDRPW` for grade columns; letters+digits+space for subject code; letters+space for remarks). Ignore and never store the Faculty and Section columns. Read the header band (program, "School Year and Semester: 2024-2025 | Second") and the footer GPA; **the footer text is dark green on mid green, so it must be binarized or Tesseract returns nothing.**
      - A tested reference implementation is in `cursor/reference/grade_table_ocr.py`. **Productionize it** at `tools/grade_table_ocr.py` (keep the contract: JSON on stdout, non-zero exit plus `{"error": ...}` on failure), call it from Laravel with `Symfony\Component\Process\Process` (python3, Pillow, numpy and the tesseract CLI are installed in the Docker image), add a timeout, and cover it with tests using the fixtures below. Make grid detection more tolerant (it currently expects 10 columns, an orange header band, light grid lines).
      - **If grid detection fails** (different layout, phone photo, skewed): fall back to generic Tesseract TSV (word boxes clustered into rows by y and columns by x, with OCR-noise normalization such as `1,25`→`1.25`, `l.25`/`I.25`→`1.25`), then path 4. Never claim success silently; show the source and the warnings.
   4. **AI fallback (only if parsing confidence is low):** if fewer rows validate than expected or >20% of rows fail validation, send the AI ONLY the cleaned **text lines that look like subject rows** (strip any line that is not a subject row, so names, student numbers and headers are never sent) and ask for JSON rows. Validate strictly. Never send the image or PDF. Count the call against `AI_DAILY_LIMIT`. If `AI_API_KEY` is empty or the call fails, go to manual entry with whatever rows parsed.
   - **Parser design:** `app/Services/Grades/GradeReportParser.php` with small adapters per input path and a shared `GradeRowNormalizer`. For pasted/PDF text, detect columns **from header keywords** (Subject Code, Description/Subject, Units, Midterm, Final, Final Grade/Grade, Remarks), tolerate different orders and extra columns, and fall back to anchoring on the subject-code pattern (`[A-Z]{2,8}\s?\d{1,3}[A-Z]?`) at the row start and the remarks/grade tokens at the end.
   - **Validation (flag, never silently drop):** subject-code pattern; units numeric 0-9; grades in the configured scale (Philippine default 1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00, 5.00, plus INC/DRP/W etc.); remarks consistent with the grade (<=3.00 PASSED, 5.00 FAILED, INC -> INCOMPLETE); duplicate subject codes in a term flagged; `needs_review=true` and a highlight on any problem row.
   - **GPA computation and cross-check (verified against 3 real portal sheets; implement exactly):**
     - GPA = sum(units x final_grade) / sum(units) over rows that count toward GPA.
     - **NSTP subjects (code starts with `NSTP`) are EXCLUDED from the GPA.** Evidence: the 2023-2024 sheet only matches the portal's 1.31 when NSTP 122 (3 units) is excluded (31.5/24 = 1.3125); including it gives 1.28.
     - **INC counts as 4.00 in the portal's GPA** (inferred from the numbers: 2025-2026 sheet matches 2.33 only with INC=4.00: 60.5/26 = 2.327; skipping INC gives 1.93, INC=5.00 gives 2.52). Make this a config value `grades.inc_gpa_weight` (default 4.00) with a note in DECISIONS.md that it must be confirmed with the registrar. Failed (5.00) rows DO count. PATHFIT counts.
     - Make "excluded code prefixes" configurable (`grades.gpa_excluded_prefixes`, default `["NSTP"]`).
     - If the computed GPA differs from the GPA shown on the source by more than 0.01, show a visible warning on the review screen naming the likely cause (an OCR error in a row, or a rule difference), and highlight the rows.
   - **Review screen (Livewire):** editable table (edit cells, add/delete row, row-level warnings, source label: pasted / PDF text / grid OCR / generic OCR / AI), computed GPA next to the detected GPA. Nothing is saved until the student clicks **Confirm** (`status=confirmed`). Manual entry always works. Uploaded files are deleted after confirmation (record the choice in DECISIONS.md).
   - **Fixtures and tests (required):** `tests/Fixtures/grade-reports/`. The three reference sheets below as expected-JSON fixtures (parser and GPA tests run against the expected data even without the images). If the sample images exist in `cursor/samples/` (names below), also run the OCR script against them in an integration test (skip with a clear message if python/tesseract are unavailable). Add at least three layout variants of your own (different column order, no Midterm/Final columns, space-aligned PDF text). Tests must NOT call the real AI (`Http::fake()`).
   - **Expected results for the reference sheets** (faculty names and sections are ignored). Program for all: BS Information Systems.

     **A. `Screenshot_2026-10-05_143510.png` : 2024-2025, Second. Portal GPA 1.44.**

     | code | description | units | midterm | final | final grade | remarks |
     |---|---|---|---|---|---|---|
     | CCS 106 | Applications Development and Emerging Technologies | 5 | 2.50 | 2.25 | 2.25 | PASSED |
     | CCS 110 | Computer Graphics 1 | 3 | 2.25 | 1.00 | 1.50 | PASSED |
     | CCS 116 | Web Development 2 | 5 | 1.25 | 1.25 | 1.25 | PASSED |
     | GEE 002 | Living in the IT Era | 3 | 1.50 | 1.00 | 1.25 | PASSED |
     | GEE 005 | Reading Visual Art | 3 | 1.00 | 1.00 | 1.00 | PASSED |
     | PATHFIT 4 | Sports and Fitness | 2 | 1.00 | 1.00 | 1.00 | PASSED |
     | PR 002 | Quantitative Methods | 3 | 1.75 | 1.00 | 1.25 | PASSED |

     Computed 34.5/24 = 1.4375 -> 1.44.

     **B. `Screenshot_2026-10-05_184653.png` : 2025-2026, First. Portal GPA 2.33. Contains INC and a FAILED row.**

     | code | description | units | midterm | final | final grade | remarks |
     |---|---|---|---|---|---|---|
     | CCS 118 | Multimedia Systems | 3 | 1.00 | 1.75 | 1.50 | PASSED |
     | GEE 003 | Gender and Society | 3 | 1.50 | 1.00 | 1.25 | PASSED |
     | IS 102 | Enterprise Resource Planning | 3 | 1.50 | 1.25 | 1.25 | PASSED |
     | IS 103 | Database System Enterprise | 5 | 1.75 | INC | INC | INCOMPLETE |
     | IS 104 | IS Innovations & New Technologies | 3 | 1.75 | 1.75 | 1.75 | PASSED |
     | IS 105 | Enterprise Architecture | 3 | 1.25 | 1.25 | 1.25 | PASSED |
     | IS 106 | IS Major Elective 1 | 3 | 2.00 | 5.00 | 5.00 | FAILED |
     | RES 001 | Methods of Research | 3 | 1.50 | 1.50 | 1.50 | PASSED |

     Computed with INC=4.00: 60.5/26 = 2.327 -> 2.33. `IS 106` must be flagged `is_failed`; `IS 103` must be flagged incomplete (not failed).

     **C. `Screenshot_2026-10-05_184732.pdf` (image-only PDF, NO text layer) : 2023-2024, Second. Portal GPA 1.31.**

     | code | description | units | midterm | final | final grade | remarks |
     |---|---|---|---|---|---|---|
     | CC 103 | Computer Programming 2 | 5 | 1.25 | 1.00 | 1.00 | PASSED |
     | CC 108 | Technical Computer Concepts | 3 | 1.25 | 1.25 | 1.25 | PASSED |
     | CCS 107 | Web Development 1 | 5 | 1.50 | 1.50 | 1.50 | PASSED |
     | GEC 008 | Ethics | 3 | 1.25 | 1.25 | 1.25 | PASSED |
     | ISP 101 | Fundamentals of Information System | 3 | 1.50 | 1.25 | 1.25 | PASSED |
     | NSTP 122 | Civic Welfare Training Services 2 | 3 | 1.25 | 1.00 | 1.00 | PASSED (excluded from GPA) |
     | PATHFIT 2 | Exercise-Based Fitness Activities | 2 | 1.25 | 1.25 | 1.25 | PASSED |
     | PR 001 | College Algebra | 3 | 2.00 | 1.75 | 1.75 | PASSED |

     Computed excluding NSTP: 31.5/24 = 1.3125 -> 1.31.
2. **Career-match explanations**: the system computes compatibility scores itself (see M8). The AI only phrases a short explanation (all five in one request). If the AI is unavailable, use a template string.
3. **Recommended-action phrasing**: the system selects interventions from the predefined list based on the student's contributing risk factors. The AI may only **rephrase** the chosen
   interventions; it receives intervention codes and must return JSON referencing only those codes. Validate the response; discard anything not in the list. Rule-based fallback = the intervention's own stored description.

**De-identification (RA 10173):** never send names, student numbers, emails, birthdates, or free-text identifiers to the AI API. Send only coded/aggregated features
(e.g. "GWA band: 2.25–2.5, failed subjects: 2, scholarship: yes"). Implement this in one class (`app/Services/Ai/Deidentifier.php`) with a test that asserts no PII fields can pass through.
Exception: grade-report extraction necessarily receives the uploaded document; instruct the student in the UI not to upload pages showing more than their grades, and state
this in the consent text.

## 7. UI / UX direction

- Clean, professional, academic look; generous whitespace; rounded cards; consistent spacing. Font: Inter (or system sans fallback).
- **Brand palette (green). Define these as Tailwind theme tokens** (Laravel 12 ships Tailwind v4: use `@theme` in `resources/css/app.css`), e.g. `brand-50`, `brand-200`, `brand-400`, `brand-900`:
  - `#E8F5E9` brand-50: page/section backgrounds, table stripes, hover tints
  - `#A5D6A7` brand-200: borders, soft badges, chart fills, selected rows
  - `#66BB6A` brand-400: accents, icons, chart lines, progress bars
  - `#1B5E20` brand-900: primary buttons, sidebar, headings, links
  - Neutrals: white cards on a `brand-50` or light-gray page background; gray text scale for body copy.
  - **Contrast rules:** white text is allowed ONLY on `brand-900`. Never put white text on `brand-400` or `brand-200` (fails accessibility contrast); use `brand-900` text on those. Hover state for primary buttons: slightly lighter than brand-900, not brand-400 with white text.
  - **Risk badges use their own semantic colors**, not the brand green, so "Low risk" is not confused with the app's chrome: Low = green with a check icon, Moderate = amber with a warning icon, High = red with an alert icon, each with a text label. Keep them visually distinct from the brand palette (e.g. use outlined/soft badges).
- Layout: left sidebar navigation (collapsible on mobile) + top bar with user menu and notification bell. Fully responsive, usable on a phone.
- **Risk badges:** semantic colors as defined above, each with an **icon and text label**, never color alone. Low-confidence predictions get a visible "Lower confidence" tag with a tooltip explaining why.
- Interpretable results: horizontal bar chart of contributing factors (positive/negative), plain-language sentences, and a "what this means / what this doesn't mean" panel.
- **Wording must be supportive, not alarming.** Students see "areas where support could help" rather than "you will drop out". Every results screen shows the disclaimer: *"These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline."*
- Forms: multi-step **Livewire** wizard for the student profile (Academic → Socioeconomic → Skills & Experience → Questionnaire) with a progress indicator, save-as-draft, inline validation, and a completeness meter.
- Tables: all built from the shared Livewire table pattern (section 3): searchable, sortable, filterable (risk level, year level, program), paginated. Charts: Chart.js (donut for risk distribution, line for trend over time, bar for factors).
- Include empty states, loading states, success/error toasts, and confirmation dialogs for destructive actions.
- Accessibility: labels on all inputs, sufficient contrast, keyboard navigable, `aria` attributes on modals/tabs.

### UI reference images (optional)
If images exist in `cursor/ui-reference/` (screenshots of the intended design), treat them as the visual target: match layout, spacing, component shapes and hierarchy using Blade + Tailwind + Livewire.
If a screenshot conflicts with this spec's roles, data, stack or palette rules, **the spec wins**. If the folder is empty or missing, design the screens yourself following this section. Do not generate React or plain-HTML pages from images.

### Screens by role
- **Student:** Dashboard (completeness meter, latest employability score, risk badge, program-shift note, top 3 factors, "Request new prediction" button with cooldown) · My Profile (wizard) · Grades (manual + upload) · Questionnaire · Results (details, factors, history timeline) · Career Matches (PSOC cards with compatibility %).
- **Faculty:** Advisee list (table with latest scores/risk, filters) · Advisee detail (scores, factors, history chart, recommended actions with "mark reviewed" + note) · Notifications when an advisee's new prediction arrives.
- **Department Head:** Program dashboard (risk distribution, average employability, trend by term, year-level breakdown, program-shift flag counts) · Student list for the program with detail pages.
- **Dean:** College dashboard (per-program comparison cards/charts, aggregated risk and performance trends).
- **Administrator:** Institution dashboard · User management (create Faculty/Dept Head/Dean/Admin, activate/deactivate, reset password) · Institution student list (CSV import + manual edit) · Adviser assignment (single and bulk) · Colleges & programs · PSOC occupations · Interventions · Questionnaire items · Audit log viewer.

## 8. Milestones (build in this order)

### M0 – Foundations
- Confirm the app runs, DB connects, `migrate` works. Install Tailwind and Chart.js via Vite (`docker compose exec app npm install ...`) and Livewire via Composer (no separate Alpine), build the shared `BaseTable` Livewire component with a small demo usage, set up the base layout (sidebar, topbar, flash toasts), `config/edupredict.php`, `docs/DECISIONS.md`, `docs/ml-feature-contract.md` (initial skeleton).
- **Done when:** the welcome/login page renders styled; `npm run build` succeeds; a demo Livewire table renders with working search/sort/pagination and the browser console shows no Alpine errors.

### M1 – Authentication, roles, seed data
- Auth (login, logout, password reset UI even if mail is log-driver), `role` middleware, route groups per role, role-based redirect after login to each dashboard.
- Seeders: colleges + programs (include UCC's College of Computer Studies with BSIT / BSCS and a few other colleges/programs as realistic placeholders), and **one demo account per role**
  (documented in README, dev-only passwords). Seed ~60 synthetic students across programs/year levels with varied profiles, grades and predictions (clearly marked synthetic; never production).
- **Done when:** each of the five demo accounts logs in and lands on its own (even if empty) dashboard; role-access tests pass.

### M2 – Student self-registration, consent, account management
- Student registration validated against `institution_students` (student number + name + program match; block unknown or already-registered numbers).
- **Informed consent** step at registration (RA 10173 wording: what data is collected, why, who sees it, AI API de-identification, rights to access/correct/withdraw). Store in `consents`. Registration is impossible without it.
- Admin-created accounts for Faculty / Department Head / Dean / Administrator (temporary password + force change on first login). Admin user list with activate/deactivate.
- Admin: institution student list CSV import (validate, report row errors) and adviser assignment (single + bulk).
- **Done when:** a student on the list can register; one not on the list cannot; admin can create all four staff roles; tests cover both.

### M3 – Grades (manual + AI-assisted extraction)
- Grade report CRUD (term, subjects, units, grades, failed flag computed from the grading scale; make the scale configurable, default Philippine 1.00–5.00 where 3.00 is passing and 5.00 failing, with INC/DRP handling).
- Computed GWA (unit-weighted), failed-subject count, semesters completed, `limited_history` flag (e.g. fewer than 2 completed semesters).
- Implement the hybrid extraction pipeline from section 6 (paste → PDF text → OCR → AI fallback) with the editable review table and explicit **Confirm**. Use Livewire `WithFileUploads` for uploads. The paste path and manual entry must work with no API key and no internet.
- **Done when:** manual entry works; the three reference sheets (A, B, C in section 6) parse exactly to the expected tables with the correct GPAs (INC and NSTP rules applied), including the image-only PDF; the paste path and tests are green; with the AI key empty, upload still works through OCR/parsing or degrades to manual; GWA is correct in tests.

### M4 – Socioeconomic profile + Skills & Experience
- Multi-step forms with the fields in the data model; encrypted storage for sensitive socioeconomic fields; draft saving; completeness meter.
- **Done when:** a student can complete and edit both sections; data persists; unauthorized users cannot read them.

### M5 – Behavioral questionnaire
- Admin-manageable items. Seed a **draft** set of 4 items per construct (study habits, time management, motivation, procrastination, engagement), 5-point Likert scale, with some reverse-scored items.
  Mark all seeded items `is_draft=true` and show a footer on the questionnaire: *"Structured self-report scale developed from supporting research; not a clinical or diagnostic assessment."*
- Compute normalized construct scores (0–100) after submission; store answers; allow retaking (keep history).
- **Done when:** a student can submit; reverse scoring is correct (test); construct scores feed `FeatureBuilder`.

### M5.5 – FeatureBuilder + placeholder predictor
- Implement `FeatureBuilder`, `PredictorInterface`, `HeuristicPredictor`, DTOs, config binding, and feature-contract doc (see section 2). Unit-test determinism and factor output.
- **Done when:** given a seeded student, `FeatureBuilder` → `HeuristicPredictor` yields scores, a risk level, and factors; low-confidence flag appears for limited history.

### M6 – Prediction request flow + results + history
- "Request new prediction" (student): requires minimum profile completeness; cooldown (default 24h, configurable); inserts a new `predictions` row (never overwrites); notifies the assigned faculty.
- Results page: employability gauge/percentage, dropout risk badge (+ lower-confidence tag), factor bar chart, plain-language summary, disclaimer, and **history timeline** (line chart + table).
- Authorized viewers (faculty, dept head, dean aggregates, admin) see history per their scope.
- **Done when:** repeated requests create history; faculty receives a notification; scope tests pass.

### M7 – Program-shift indicator
- Rule-based on the dropout factors (not a trained model; no percentage). Define in `app/Services/Prediction/ProgramShiftEvaluator.php`:
  - `program_fit`: poor performance concentrated in the program's major/core subjects while engagement, motivation and study-habit constructs are healthy.
  - `disengagement`: low engagement/motivation/consistency and/or broad poor performance across subject types.
  - `mixed` / `none` otherwise.
- Display as a qualitative label with the contributing factors and wording like "may be worth a conversation with an adviser about program fit". Documented thresholds in `docs/DECISIONS.md`.
- **Done when:** the indicator appears beside dropout risk for faculty/dept-head/student views and is unit-tested on contrasting cases.

### M8 – PSOC career matching
- Seed `psoc_occupations` with a representative set (~40–60 occupations across relevant PSOC major groups, with realistic `skill_tags` and `related_program_codes`, especially for IT/CS, business, education, engineering, health).
  **Mark the dataset as "starter set, verify against the official PSA PSOC 2012 before final submission"** in `docs/DECISIONS.md` and in the admin PSOC page.
- Compatibility score computed by the system (weighted: program relevance, skill-tag overlap with the student's skills/certifications/projects, relevant academic strengths) → 0–100. Show the top 5 with a bar and the matched/missing skills.
- AI-phrased 2–3 sentence explanation per match (de-identified input) with a template fallback. Include the limitation note: "broad occupational categories, not job offers".
- **Done when:** a student sees ≥5 sensible matches; scores are deterministic and unit-tested; fallback text works with AI off.

### M9 – Recommended institutional actions
- Seed `interventions` (≈15–20 realistic items such as academic tutoring, peer mentoring, financial-aid/scholarship referral, guidance counselling referral, study-skills workshop,
  time-management coaching, OJT/internship placement support, career-guidance session, adviser check-in, program-fit conversation, attendance monitoring), each linked to the factor(s) it addresses and a minimum risk level.
- For moderate/high-risk predictions: rule engine maps contributing factors → candidate interventions (ranked, max 5) → AI rephrases (constrained to those codes) or rule-based fallback.
- Faculty/Dept Head can mark an action reviewed and add a note. Students see only a gentle "support available" message, with the specific actions shown to faculty/staff (record this decision).
- **Done when:** low-risk students get none; moderate/high get actions; a test proves AI output outside the list is rejected.

### M10 – Role dashboards and analytics
- Implement all dashboards in section 7 with Chart.js: risk distribution donut, average employability, trend over terms, year-level breakdown, per-program comparison (dean), institution totals (admin).
- Aggregations computed with efficient queries on the **latest prediction per student**; always scoped by role.
- Faculty advisee list (shared Livewire table) with filters; export current table view to CSV (scoped; log the export in `audit_logs`).
- **Done when:** every dashboard renders with the seeded data; counts match the DB in tests; no cross-scope leakage.

### M11 – Administration, privacy and security
- Admin screens for colleges/programs, PSOC, interventions, questionnaire items, audit log viewer.
- `audit_logs` entries for: logins, account creation, role/assignment changes, prediction requests, exports, viewing a student record by staff.
- Privacy page + consent text versioning; student "download my data" (JSON/PDF) and "request account deletion" (admin-processed) flows; session timeout; rate limiting on login and AI endpoints; secure headers; CSRF everywhere; encrypted sensitive columns; file upload validation (type, size).
- **Done when:** audit entries are written for the listed events; sensitive columns are encrypted at rest (verify in phpMyAdmin); login is rate-limited.

### M12 – Polish, demo readiness, documentation
- Seed a **demo dataset** that tells a story across all roles (a few high-risk, a few moderate, mostly low; mixed program-fit/disengagement cases).
- UI pass: consistent spacing, empty/loading states, mobile layout check, favicon, page titles, 403/404/500 pages.
- `README.md` at the repo root: how to start Docker, create the DB, seed, demo accounts per role, how to run tests, how to set `AI_API_KEY`, project structure, and a "How to plug in the trained models" section describing exactly where `PredictorInterface` is bound.
- Final: run the full test suite; list known gaps in `docs/KNOWN_GAPS.md`.
- **Done when:** a fresh clone can be brought up by following the README only.

## 9. Constraints, ethics and limitations to reflect in the product

- Estimates only; no guarantees of employment, graduation, program shift or dropout. Career matches are broad PSOC categories, not job titles.
- The program-shift indicator is a qualitative factor-based interpretation (no trained model, no percentage).
- Recommended actions are limited to the predefined list and are advisory; faculty/department judgment is required.
- No clinical or diagnostic claims anywhere, especially around the questionnaire.
- Predictions for limited-history students (e.g. first-years) are flagged "lower confidence".
- Compliance with RA 10173: consent, access control, encrypted sensitive data, encrypted transmission in production (HTTPS), de-identified data to the AI API, audit logging, data-subject rights.
- Put these limitations in an in-app "About & Limitations" page.

## 10. Definition of done for the whole build

1. `docker compose up -d && docker compose exec app php artisan migrate:fresh --seed` yields a working app with demo data.
2. All five roles can log in and see **only** their own scope (tested).
3. A student can: register → consent → enter grades → complete profile + questionnaire → request a prediction → see employability, risk, factors, program-shift note, career matches, history.
4. Faculty see advisees + recommended actions; Dept Heads/Deans/Admins see correctly-scoped analytics.
5. The app runs with `AI_API_KEY` empty (fallbacks) and with a key (AI-assisted features).
6. The placeholder predictor can be replaced by changing one binding + implementing `PredictorInterface`.
7. Every list uses the shared Livewire table pattern; no Alpine/jQuery/Yajra duplicates; Livewire actions are covered by scope tests.
8. Tests pass; README is accurate; every milestone is a separate git commit.
