# 05 — Admin Reliability, UI Cleanup, and Approved Questionnaire

> Read `src/cursor/01-initial-project.md`, `src/cursor/rules/edupredict.mdc`, prior adjustment docs, and the current implementation before editing. **Only implement the named milestone(s)**. Keep Laravel, Livewire, MariaDB and Docker architecture. Commit **each milestone independently**, run tests/build, and stop when requested.

## M28 — Fix Livewire actions, admin forms, and CSV import (highest priority)

### What is wrong / what to change
1. **Recurring HTTP 500 on actions.** Livewire requests contain `calls: [{"method":"undefined", ...}]` (seen in `admin.college-form` and previously `student.skills-experience-section`), resulting in `Livewire\\Exceptions\\MethodNotFoundException`. Investigate the root cause across **all** interactive components, shared button/modal helpers, Alpine event handlers and action attributes. Check dynamic `wire:click`/`wire:submit` bindings, conditional expressions, code rendering literal `undefined`, event forwarding and JS handlers. Do not add a dummy public `undefined()` method, globally suppress the exception, or change security middleware as a workaround. Repair the source and add regression tests for all affected paths. A request with an undefined action must never be emitted by legitimate UI interactions.
2. **Admin CRUD smoke test.** Manually verify Create/Edit/Save/Activate/Deactivate/Archive where allowed, View/Cancel, dialog open/close, validation errors, and SweetAlert confirmations across Users, Eligible Students, Colleges/Departments/Programs, Questionnaire, PSOC Occupations, Interventions, Deletion Requests. Ensure each button works, gives success/failure feedback and **actually persists** the intended mutation. Authorization remains server-enforced.
3. **Eligible Students CSV import.** The import currently seems inert even when network status is 200. Trace upload binding, action execution, validation, file processing, modal lifecycle, display errors and list refresh. Show valid-row/imported/skipped/error counts and useful failure reasons. Test with a small synthetic CSV; do not modify the existing institution roster or wipe the DB. Keep student eligibility / CLAS restrictions.
4. **Add a single eligible student manually.** Add button opens an accessible modal with student number, first/last name, CLAS program, year level and existing optional institution-list fields (if applicable); validate duplicate numbers, linked program scope and year bounds; write an unclaimed institution eligibility row **without** creating or logging in a student account. Support safe edit/status changes according to existing data model. Keep CSV import as a separate option. Tests must prove students outside the imported/manual eligibility list still cannot register.
5. **Account confirmations.** Preserve consistent SweetAlert2 for consequential mutations. Ensure dialog background visibly dims and focus/scroll locks work; no duplicate submissions; correct cancel/confirm behavior. Do not show a success toast until the server succeeds.

### Done when
- Reproduce and fix `method: "undefined"` at its actual source, including tests for previously broken admin form saves, account activation/deactivation, student skills dialogs and any common helper. No legitimate UI click yields HTTP 500.
- Admin modal CRUD, CSV import (success and invalid-file states), manual eligibility, and status changes work in browser; request responses actually persist data.
- Run `docker compose exec app php artisan test`, `docker compose exec app npm run build`, and relevant browser checks. Report commands, findings, changed files, tests, exact commit hash, and residual issues.
- Commit `M28: Repair Livewire actions and eligible student administration`. Stop unless explicitly asked to proceed.

## M29 — Admin interface layout, filter polish, and record status controls

### What is wrong / what to change
1. **Extraneous explanatory copy.** Admin dashboard currently shows an oversized card with large blank space, description of latest-estimate totals and scattered filters; other role pages start with implementation-oriented paragraphs (e.g. 'Add and edit staff from the list'). Replace with concise helpful page titles/subtitles only where needed. Use a compact, aligned **Department / Program / Year / Period** filter bar with no empty left column or explanatory card. Keep existing aggregate semantics, filters and privacy restrictions. Avoid removing useful context for interpreting statistics.
2. **Sidebar scrolling.** Staff sidebar stays fixed-height and its own nav area scrolls only when needed; mouse wheel over sidebar scrolls the sidebar, not background page. On short nav lists, wheel must not unintentionally scroll the page if pointer is over sidebar; test desktop and mobile, including keyboard access, sticky header and logout/profile actions. Avoid global page scroll locking outside open dialogs.
3. **Academic structure statuses.** Inspect existing `is_active` support for college, department and program and existing linked-record constraints. Offer clearly labelled Activate/Deactivate or Archive/Restore actions (modal or row menu) wherever supported. Never hard-delete or orphan colleges, departments, programs, students or prediction history. Confirm consequential changes and explain effects on CLAS eligibility and accounts. **Do not allow the sole active CLAS pilot college or its necessary programs to be disabled without a safe explicit policy.** If a required status field/transition is absent, propose a minimal additive migration first, backup and preserve data, rather than fake a control.
4. **Consistent actions and filters.** Use icon + accessible label or compact buttons for View/Edit/Change Status in admin tables, aligning filters, modals, focus states, SweetAlert dimming and responsive overflow. On Users, retain role/status filtering and usable navigation.
5. **Activity Log column headings.** Replace ambiguous headers `When`, `Actor`, `Subject`, `IP` with clear labels such as `Date & time`, `Performed by`, `Affected record`, `IP address` (keep `Action`); provide useful action descriptions/empty state without changing the audit schema or exposing unnecessary personal data. Keep audit entries immutable.
6. **Deletion Requests filter.** Use a compact, visually distinct Pending / Approved / Rejected / All segmented control and preserve actual filter logic. Clarify that these requests are **account deletion requests** and approval currently deactivates login rather than destroying grades and history.

### Done when
- Dashboard has a compact, balanced filter bar; no redundant meta-text cards; sidebars behave properly during scrolling.
- All allowed status controls behave with guards, persistence, confirmation and tests; archive/deactivation cannot silently destroy linked data.
- Activity Log headers are understandable and deletion filters work; desktop/mobile and keyboard/modal checks pass.
- Run full Laravel tests, frontend build, browser checks. Commit `M29: Polish admin layout, statuses, and activity views`. Stop unless explicitly asked to proceed.

## M30 — Apply the approved UCC questionnaire (separate, gated milestone)

### Source of truth
- Read the **actual** approved UCC questionnaire and scoring/research PDF files from `src/cursor/reference/approved-questionnaire/` plus relevant current questionnaire tables and the existing M25/M27 mapping reports.
- Verify exact question wording, question order, answer scale, scoring and versions **from these files**, not guesses. Confirm panel approval status against the files' still-draft labels before publishing.

### What is wrong / what to change
1. Existing `draft-v1` behavioral questions are not the approved UCC questionnaire. Implement a **new versioned instrument**, preserving historic answered drafts and prediction snapshots without rewriting them.
2. Show six approved sections: Academic Factors A1–A6; Socioeconomic Factors S1–S6; Behavioral/Engagement B1–B6; Competence C1–C8; Psychological Capital P1–P6; Self-Perceived Employability E1–E4. Use precise PDF wording and scales. Preserve resumable assessment, one-question/section progress, save/continue, review, clear validation/loading and privacy boundaries. The approved S section is not a replacement for the private structured socioeconomic profile without explicit mapping/policy.
3. Version the questionnaire response definitions, scoring metadata and historical view. Saved attempts must show exactly the question/version/answer actually used; old results remain unchanged.
4. Keep approved calculations/context separate: B scores/context do **not** alter trained dropout probability; A and S are context only; C and P cannot be passed into the Pianda-trained candidate until scientific mapping/equivalence is established; E is descriptive comparison, not employability model input. Don't rebrand placeholder scores as validated predictions, don't silently integrate M25/M27 models, don't add dropout risk thresholds.
5. Update admin category/section dynamic dropdown options according to actual new definition; lock already-answered versions from mutation and use fresh versions for edits. Apply published/draft policy visibly.

### Done when
- Exact question texts and scoring match both PDF sources and have a documented traceability checklist; questionnaire versions and old records remain immutable.
- All six sections save/resume/review and are visible correctly in History; role privacy and tests pass.
- Any mismatch between PDFs, current schema, research mapping, and required inference inputs is reported explicitly and handled without invented transformations.
- Run full Laravel suite, frontend build, and actual browser assessment checks. Commit `M30: Add versioned approved UCC assessment instrument`. Stop; do not retrain/integrate models.

## Do not touch (all milestones)
- No `migrate:fresh`, no `docker compose down -v`, no reset or replacement of existing data, no `APP_KEY` changes.
- Do not change ML candidate artifacts, raw datasets, FastAPI plans, deployed inference, or HeuristicPredictor while doing UI fixes.
- Preserve 4 active roles: Student, Department Head, Dean (college aggregates only), Administrator. Legacy Faculty cannot regain access.
- Keep CLAS pilot constraints, authorization scopes, student privacy, consent, grade versions/INC rules, audit history and saved prediction snapshots.
- Do not infer a successful DB mutation from HTTP 200 alone; verify state, feedback and authorization.
- Review `git status` and Git author before each commit; don't push unless asked. No credentials or student private data in commits or logs.
