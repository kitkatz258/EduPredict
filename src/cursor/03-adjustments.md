# 03 – Post-M21 Adjustments

> Read `cursor/01-initial-project.md`, `cursor/rules/edupredict.mdc`, and `cursor/02-adjustments.md` for context.
> Inspect the current implementation first. Do ONLY what is listed here.
> Use `cursor/ui-reference/` as visual direction.
> Run commands through Docker, e.g. `docker compose exec app ...`.
> Preserve existing data, prediction history, encrypted fields, and backups.
> Do not run `migrate:fresh`. Do not change `APP_KEY`.
> After each milestone: run targeted tests, full test suite, frontend build, review `git diff`, commit, then stop or continue only if explicitly instructed.

---

## M22 – Student auth, Assessment flow, Dashboard, and History polish

### Authentication
- Keep Sign In / Create Account inside one fixed-width auth card.
- Make the top Sign In / Create Account control a real animated segmented toggle.
- Switching must happen without a full page reload where practical.
- Add a smooth sliding/fading transition between the two auth states.
- Keep both states the same outer-card width so the transition does not stretch the layout.
- Remove the redundant “New student? Create an account…” prompt below Sign In because Create Account already exists in the toggle.
- Preserve student-number sign-in, controlled registration, password reset, privacy notice, and all existing security behavior.

### Assessment flow
- Remove the large `Assessment workflow progress` card.
- Remove the large clickable `1. Questionnaire / 2. Skills & experience / 3. Grades / 4. Review` step cards.
- Use one consistent wizard/progress pattern for the entire Assessment.
- The Behavioral Self-Report progress style should become the visual pattern for the whole Assessment.
- Primary sequence:
  1. Academic Behavior
  2. Socioeconomic Factors
  3. Employability Assessment
  4. Skills / Certifications / Work Experience
  5. Grades
  6. Review / Run Prediction
- `Continue` should move to the next logical section. `Back` should move to the previous one.
- Saved sections may still be revisited/edited through a compact summary/navigation pattern; do not require full re-entry.
- After Academic Behavior, continue directly to Socioeconomic Factors, then Employability Assessment instead of relying on the old large step cards.
- Keep final Employability Assessment questions as placeholders/configurable structure until approved research items are supplied.
- Do not invent final questionnaire wording or weights.

### Assessment history cleanup
- Remove `Previous questionnaire submissions` from the Assessment page.
- Remove saved grade-report history blocks from the active Assessment flow.
- Historical submissions belong in the dedicated History page.

### Dashboard
- Reduce the excessive empty space in the Employability score card.
- Make the card denser and more informative using only existing valid data, such as:
  - score
  - score band/label
  - concise top positive/negative contributors
- Do not invent new metrics.
- Redesign `Next Steps` so it does not use four oversized mostly-empty cards. Use a more compact action row/list/grid with clearer hierarchy.
- Keep Career Matches on the Dashboard.

### Results + construct summaries
- When questionnaire scoring exists for a category/construct, show a concise construct summary in current results and in the saved History snapshot.
- Example categories may include Study Habits, Time Management, Motivation, Procrastination, Engagement, etc.
- Do not hardcode unapproved weights.
- Use stored scores/calculations only; do not recalculate old attempts using new/current rules.

### History
- History is the single place for past prediction attempts.
- Each `View` must show the exact immutable snapshot saved for that prediction:
  - questionnaire version + summarized answers/scores
  - category/construct calculations where available
  - skills/certifications/work experience used
  - grade report/version and rows used
  - employability result
  - dropout result
  - confidence
  - contributing factors
  - program-shift context
  - career matches saved with that attempt
  - model version
- Older attempts that lack saved detail must continue to say `Not available for this attempt`.
- Never fill historical gaps with current mutable profile data.

### M22 checks
- Auth toggle transitions smoothly both ways.
- Sign In/Create Account card width stays stable.
- No redundant new-student prompt.
- Assessment uses one wizard pattern only.
- Continue/Back follows the intended sequence.
- Questionnaire/grade-history blocks are removed from Assessment.
- Dashboard score card and Next Steps are visually compact.
- History renders only saved snapshots.
- Full Laravel tests pass.
- Frontend build passes.

Commit:
`M22: Refine student auth, assessment flow, dashboard, and history`

---

## M23 – Admin UX consistency, filters, uploads, and interaction polish

### Admin action buttons
Restyle action controls on:
- Users
- Academic Structure
- Questionnaire
- PSOC Occupations
- Interventions
- Deletion Requests

Replace plain underlined text actions with consistent buttons/icon-buttons matching the rest of EduPredict:
- View
- Edit
- Deactivate/Activate
- Review
- Archive/Restore where applicable

Use Remix Icons plus visible text or accessible labels. Destructive actions require confirmation.

### Questionnaire admin
- Rename visible `Construct` wording to `Category`.
- The available Category options must depend on the selected Questionnaire Section.
- When Section changes:
  - refresh the Category choices immediately
  - clear/reset an invalid previous Category
  - prevent invalid Section/Category combinations from being saved
- Preserve version locking and historical definitions.

### Eligible Students CSV import
- Remove the always-visible CSV import block.
- Add an `Import CSV` button that opens a modal.
- The modal should use a noticeable upload area similar to the student grade upload:
  - drag/drop where practical
  - click to browse
  - accepted file type
  - selected filename
  - validation/error feedback
  - Import action
  - loading/progress state
- Preserve the current CSV columns and registration behavior.

### Activity Log
Keep the backend audit system unchanged, but improve visible column wording:
- `When` → `Date & time`
- `Action` → `Action`
- `Actor` → `Performed by`
- `Subject` → `Affected record`
- `IP` → `IP address`

If a value is an internal model label such as `User 1`, present a more useful readable label where the existing data safely allows it; do not fabricate missing data.

### Deletion Requests
- Keep this as account deletion requests.
- Replace the current plain form-like Status filter with an animated segmented switcher inspired by the Grades mode toggle:
  - Pending
  - Approved
  - Rejected
  - All
- The active segment should slide/transition visually.
- Review remains in a modal.
- Approval still deactivates sign-in and preserves grades, predictions, and history.

### Animation / interaction polish
Apply subtle consistent animation to:
- modal open/close: fade + slight scale
- segmented controls/toggles: animated active indicator
- auth toggle: slide/fade
- dropdown/filter state changes where appropriate
- toast appearance/disappearance

Requirements:
- keep transitions quick and professional
- do not animate normal content so heavily that the app feels slow
- respect `prefers-reduced-motion`
- preserve focus trapping, Escape-to-close, scroll locking, loading states, and duplicate-submit protection from M21

### M23 checks
- Admin actions use styled controls consistently.
- Questionnaire Category changes with Section and invalid combinations cannot save.
- Eligible Student CSV import works from modal with clear upload state.
- Activity Log headings are clearer.
- Deletion Requests uses the intended segmented toggle behavior.
- Modals and toggles animate smoothly and remain accessible.
- Full Laravel tests pass.
- Frontend build passes.
- Manual role/page smoke test passes.

Commit:
`M23: Polish admin workflows, controls, and animations`

---

## Do not touch in M22–M23
- Do not migrate MariaDB to PostgreSQL yet.
- Do not create the FastAPI/model container yet.
- Do not train final ML models yet.
- Do not deploy to Render yet.
- Do not change `APP_KEY`.
- Do not run `migrate:fresh`.
- Do not rewrite historical prediction snapshots.
- Do not invent final research questionnaire items or arbitrary weights.
- Do not reintroduce Faculty as an active role.
- Do not expose Dean to individual student records.
- Do not remove privacy, consent, audit logging, OCR, or existing authorization protections.

---

## After M23
The next phases should be planned separately:

- **M24 – MariaDB → PostgreSQL migration**
- **M25 – Model training/comparison + FastAPI prediction service**
- **M26 – Render deployment and production configuration**
