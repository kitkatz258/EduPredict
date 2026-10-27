# M41 — Final Live-Testing Defects and 60-Item Questionnaire v2

## Scope and priority
Address the live-testing defects captured in the five user screenshots (27 October 2026). Work in the **current branch only**; do not assume any particular branch name. This is a focused repair milestone, not a redesign. Preserve M35 deployment settings, existing M36–M40 features, student assessment/grade/prediction histories, role authorization, and local/prod database contents. No `migrate:fresh`, deployment, push, or branch operations. Ensure all relevant actions work on both MariaDB development and PostgreSQL.

**Priority order:** P0 stuck questionnaire overlay and broken admin save feedback; P0 account provisioning/password security decision; P1 dark contrast, PSOC enable/disable, versioned 60-item questionnaire; P2 table alignment and action layout.

## A. Admin modal success behavior — all administrator modules
- Audit Add/Edit actions on Users, eligible students, colleges, departments, programs, questionnaire items, PSOC, interventions, prediction grants and applicable management dialogs.
- After a **confirmed successful server-side save**, close the modal, restore body scrolling and focus, refresh the relevant Livewire table, then show a **centered SweetAlert2 success dialog with OK**. Examples: “Department successfully added” and “Intervention successfully updated.” Do not use a transient corner toast for these Add/Edit success messages.
- Failed validation must keep the modal open and show inline errors; no fake success, no duplicate notifications. Destructive actions should retain pre-action SweetAlert confirmations; icon actions must remain accessible.
- All dialog footers must be pinned and their bodies independently scrollable. Eliminate `Public method [undefined]`, focus-lock and overlay-stuck errors.
- Browser-test at least Add AND Edit success per different component pattern, and negative validation; ensure similar modules use the same proven feedback mechanism.

## B. Dark theme: all roles and guest
- Fix invisible/low-contrast text in dark mode across ALL roles and states. Screenshot examples: amber “Lower confidence” badge and placeholder warning text on student dashboard, amber context-flag pills on questionnaire section cards, inactive status badges, control help text, dark input placeholder, focus and hover states.
- Use semantic light/dark design tokens; retain meaning of risk colors and indigo brand while meeting legibility (target WCAG AA normal text contrast 4.5:1). Do not solve by globally making all colors white or discarding risk semantics.
- Test student/admin/department head/dean/guest; both themes; SweetAlert, modals, disabled/active badges, charts, error states; persistence and no first-paint flash.

## C. Questionnaire admin list: Reverse column and actions
- Remove the **Reverse** column from the admin list and any Reverse checkbox/select from Create/Edit **for the new questionnaire instrument**; give Actions enough horizontal space that Edit, Deactivate, and Publish/Mark published icons stay in one row (nowrap/flex and reasonable min-width). Keep each accessible label/title.
- Important scoring distinction: the 60 uploaded statements are **positively worded and should use raw 1–5 agreement scoring without inversion**. Removing the Reverse UI does NOT mean reverse-code every answer. Preserve old `draft-v1` reverse-coded historical definitions and results; do not reinterpret historical attempts. If old versions require display, show reverse status as read-only historical metadata in a details view, not an editable field for the new version.
- Center short-code/status/program fields appropriately; keep names, paragraphs and long statements left aligned. Apply to tables shared across admin and staff without breaking mobile layout.

## D. PSOC occupation activation and validation
- Add an `is_active` switch to PSOC list and Edit/Create as suitable, with server-side validation and SweetAlert confirmation for activate/deactivate. Deactivated items must be excluded from **future** career suggestions; existing saved prediction career matches remain untouched. Follow current academic structure's toggle behavior but with explicit scope permissions.
- Use an additive migration only if the schema lacks an active-state field (default existing rows to active); ensure existing official code validation, major group, program multi-select, and skill-tag validation still apply. On add/edit, show inline errors, centered successful save dialog after closing modal, no duplicate creates. Test role authorization and both PostgreSQL/SQLite. Do not change model scoring.

## E. Student account initial password request: security gate
- User requested **uppercase student surname as the initial password** on Admin Add Eligible Student AND CSV import, with immediate sign-in using student number. Note: surname is predictable/public; this creates a real account-takeover risk, especially on the public Render demo. Current M38 design issues a random 12-character one-time password and forces change. **Do not silently replace the secure default**. Prefer a bulk provisioned random temporary password + protected one-time delivery, or a verified student activation/reset flow.
- If project stakeholders explicitly accept a surname-based demonstration-only mode, isolate behind an OFF-by-default, nonproduction-only feature flag and require first-login password change before accessing student records. It must never be enabled in production or for real student accounts. No plaintext passwords in logs, CSV exports or repository; protect unclaimed/claimed rows and keep import idempotent. Document this deviation and test it. Existing accounts are NOT reset.
- Ensure Admin Add Eligible Student and CSV Import use a consistent account-provisioning policy; import should not accidentally issue insecure real credentials without explicit operator intent.

## F. Questionnaire Cancel overlay deadlock
- Confirmed: cancelling the questionnaire and accepting the confirmation sometimes leaves the page unclickable until reload (stale backdrop, `overflow:hidden`, focus trap, pointer-events or Alpine/Livewire state).
- Remove the separate **Cancel** action from the current-version questionnaire modal. Make the primary exit **Save draft & close**: persist dirty answers, await successful response, close modal, remove overlay/scroll lock, restore focus, show confirmation; leave published response unchanged. Close X/Escape/backdrop must not silently discard answers: either route through the same save-and-close path or show a clearly working unsaved-changes choice.
- Retain explicit **Discard draft** with confirmation, only for unsubmitted drafts, as a separate intentional destructive action. Do not conflate it with Save draft & close.
- Browser-test all exits (button, X, Escape, backdrop, discard confirm/cancel), successful reopen, page clickability afterward, both desktop/mobile and dark/light. Add regression tests for backdrop/focus/scroll restoration.

## G. 60-question instrument — NEW VERSION ONLY
- Supplied `SystemQuestions (1).docx` has **6 sections × 10 items = 60 statements**, listed EXACTLY below. Use the original wording and codes A1–A10, S1–S10, B1–B10, C1–C10, P1–P10, E1–E10. Do not invent or silently edit statements.
- Create a NEW version, e.g. `ucc-panel-revised-60-v2` (name clearly marks it as revised/draft pending adviser/panel reapproval). Do **not** alter the previously panel-approved `ucc-panel-approved-v1` 36-item instrument, existing submissions, construct means, historical predictions or model snapshots. Don't call the revised 60-item instrument panel-approved before confirmed approval.
- New instrument section mean = sum of native 1–5 values / 10 (only when all 10 are submitted); readiness index = ((mean-1)/4)×100 if displayed. Keep A/S/B context only, E descriptive only, C/P stored for research and NOT automatically mapped to Pianda or UCI models. Preserve old section scores under their own version-specific item counts.
- Update wizard header to show **two visible bars**: current section answered / 10, and number of fully saved sections / 6; accurate live answered vs saved counts 0–60; six section dots/chips; pinned header and footer, only question body scrolls; return body scroll to top after Save & Continue; adapt 390px without horizontal overflow.
- Admin UI should show each version accurately, filtering/publishing/activation and preventing modification of an already-answered instrument. Prefer creating the new version as inactive/draft, then explicitly activating it only when authorized so existing drafts retain their intended version. Handle migrations/seeders idempotently; never backfill new items into previous attempts or switch open drafts without a deliberate transition policy.
- Ensure test fixtures, required complete submission, section sums, item count, partial-save handling, and historical snapshots are correctly versioned. Verify both 36-item legacy and new 60-item path.

## H. Acceptance tests and boundaries
1. Demo screenshots: for every reported defect, take before/after browser screenshots where possible, especially dark-mode contrast and questionnaire exit clickability.
2. Admin Add and Edit success close modal **then centered SweetAlert2 + OK**; error stays open with validation. Test with actual saves on disposable rows and cleanup.
3. Admin questionnaire table: no Reverse editable control for the new instrument, all 3 actions on one horizontal row, statuses centered.
4. New 60-item draft: 10/section, exact wording, 60 total, original 36 still readable and unchanged. Preview/live questionnaire counters and wizard bars correct.
5. PSOC inactive item excluded from new suggestions but remains in historical matches.
6. Security: no surname password mode enabled in production; existing accounts unchanged. Report the design decision explicitly.
7. All modal exits restore body scroll, focus and clickability; rerun the full student wizard and at least one admin modal in both themes.
8. Run `php artisan test`, direct `php vendor/bin/phpunit --configuration=phpunit.pgsql.xml` in the isolated PG environment when available, `npm run build`, and targeted frontend tests. Explicitly report unavailable suites.
9. Audit migrations: additive only, preserve secrets/APP_KEY and existing records; avoid touching Render/Supabase. Update DECISIONS and KNOWN_GAPS.
10. Commit locally only on the current branch and report commit hash, verification, unverified work. Do not push, merge, switch, or deploy.

## Exact revised questionnaire statements (verbatim from uploaded DOCX)

### A. Academic Factors

- **A1** I am satisfied with my current academic performance.
- **A2** I can understand the lessons and topics discussed in my classes.
- **A3** I can manage the academic workload required by my degree program.
- **A4** I can learn difficult topics when I put in enough effort.
- **A5** I am confident that I can pass my enrolled subjects.
- **A6** I am confident that I can complete my degree program.
- **A7** I can explain the topics I have learned to my classmates when they ask.
- **A8** I do well in exams and written assessments in my subjects.
- **A9** I can find ways to improve my grades when they go down.
- **A10** I know which subjects I need to improve in and what I need to do about it.

### S. Socioeconomic Factors

- **S1** My available budget is sufficient to cover my school-related expenses.
- **S2** I can afford the transportation expenses needed to attend classes.
- **S3** I have reliable access to a computer or device for my academic tasks.
- **S4** I have reliable access to the internet for my academic requirements.
- **S5** My financial situation allows me to focus on my studies.
- **S6** I have sufficient financial support to continue my education.
- **S7** I can handle unexpected expenses without having to stop or delay my studies.
- **S8** I have enough money for my daily meals while attending classes.
- **S9** I have a quiet place at home or nearby where I can study.
- **S10** My work or household responsibilities do not get in the way of my studies.

### B. Behavioral Factors / Student Engagement

- **B1** I attend my scheduled classes regularly.
- **B2** I set aside regular time to study or review my lessons outside class.
- **B3** I actively participate in class activities and discussions when given the opportunity.
- **B4** I make use of the learning materials and resources provided by my instructors when studying.
- **B5** I complete my academic requirements within the given deadlines.
- **B6** I continue working toward my academic goals even when I experience difficulties in my studies.
- **B7** I take notes or make summaries during or after my lessons.
- **B8** I join study groups or academic activities outside regular class hours.
- **B9** I ask my instructors or classmates for help when I do not understand a lesson.
- **B10** I stay focused during class and avoid distractions such as my phone.

### C. Competence / Employability Skills

- **C1** I can communicate my ideas clearly to other people.
- **C2** I can work effectively with other people in a team.
- **C3** I can identify problems and develop appropriate solutions.
- **C4** I can adapt to new tools, technologies, and changing situations.
- **C5** I can use digital tools and technology effectively for academic or practical tasks.
- **C6** I can apply what I learned in school to practical tasks or real-world situations.
- **C7** I can organize my work and responsibilities to complete assigned tasks.
- **C8** I can learn new skills when a task or situation requires them.
- **C9** I show responsibility and professional behavior when working on tasks or with others.
- **C10** I can accept feedback and use it to improve my work.

### P. Psychological Capital / Career Readiness

- **P1** I am confident that I can successfully handle difficult tasks related to my studies or future work.
- **P2** When I encounter a problem, I can think of different ways to overcome it.
- **P3** I continue working toward my goals even after experiencing setbacks.
- **P4** I generally expect that my efforts toward my studies and career will lead to positive results.
- **P5** I can recover and continue after academic or personal difficulties affect my progress.
- **P6** I believe I can improve my abilities through effort, learning, and experience.
- **P7** I can think of many ways to reach my career goals.
- **P8** I feel energized when pursuing my goals for my studies and future work.
- **P9** I stay calm and keep going when things do not go as planned.
- **P10** I look at the bright side of difficult situations.

### E. Self-Perceived Employability / Readiness

- **E1** I feel prepared to apply for a job related to my degree program.
- **E2** I believe I can demonstrate useful skills to a potential employer.
- **E3** I can explain how my knowledge and experiences are relevant to a future job.
- **E4** I am confident that I can continue developing the skills needed for my chosen career.
- **E5** I believe I can find a job related to my degree after graduation.
- **E6** I know what kinds of jobs I can apply for with my degree program.
- **E7** I believe employers would value the skills I have gained in school.
- **E8** I am confident that I can do well in a job interview.
- **E9** I believe I can find other work opportunities if my first-choice job is not available.
- **E10** I have a plan for what I will do after graduation.

