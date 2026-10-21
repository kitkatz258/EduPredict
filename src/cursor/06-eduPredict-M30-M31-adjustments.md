# 06 — EduPredict Adjustments: Approved Questionnaire and Final UI/Rules Polish

> Follow `src/cursor/01-initial-project.md` and `src/cursor/rules/edupredict.mdc`; inspect the current implementation, tests, and the two approved UCC PDFs in `src/cursor/reference/approved-questionnaire/` before editing. Implement **M30**, verify and commit; then implement **M31**, verify and commit. Stop after M31. Do not silently change the panel-approved questionnaire or pretend the external ML models are validated on UCC data.

## M30 — Implement panel-approved UCC questionnaire (separate commit)

### What is wrong / what to change
1. The Assessment currently contains legacy `draft-v1` behavioral items and a placeholder Employability Assessment. Replace **new** questionnaire submissions with the exact wording, response choices, numbering, and scoring instructions in the approved `EduPredict_Questionnaire.pdf` and `EduPredict_Scoring_and_Research_Basis.pdf`. Do not invent, paraphrase, or reverse-code questions unless instructed by the approved reference.
2. Cover all six approved sections: Academic (A1–A6), Socioeconomic (S1–S6), Behavioral (B1–B6), Competence (C1–C8), Psychological Capital (P1–P6), and Self-Perceived Employability (E1–E4). Make section navigation, progress, Save draft, Back, and Save & Continue understandable on mobile and desktop. Avoid two competing workflow progress displays.
3. Implement documented construct/section means and interpretation with deterministic validation and rounding rules. Keep E separate as a descriptive self-assessment; A/S/B as context. Preserve the existing encrypted handling and staff visibility restrictions for socioeconomic answers. Do not copy raw sensitive answers into staff views or history that staff can access.
4. Create a new explicit instrument version, e.g. `ucc-panel-approved-v1` (use the real version if recorded in PDFs). Protect historical questionnaire definitions and answers from in-place rewriting. Legacy responses remain readable under their original versions; never retroactively rescore past predictions. Show only saved-at-the-time data in prediction History. Distinguish questionnaire submission history from prediction-attempt history if these are separate events.
5. Expose construct summaries in the student's Assessment/Review and future prediction snapshots only where meaningful. Do **not** feed UCC C/P section means into the Pianda model or A/S/B into UCI; no trained-model integration is authorized here.
6. The panel/professor approved the questionnaire for the capstone; do not label it psychometrically validated without evidence. Resolve outdated *draft* presentation labels only through explicitly recorded, versioned application copy; preserve original approved PDF source files.

### Done when
- Exact 36 approved items and scoring rules match the reference PDFs, including any coded/reverse-scored items.
- Students can save/resume and finish sections; progress and navigation reflect actual saved answers; browser and mobile flows work.
- Old answers, reports, snapshots, and predictions remain intact; role/privacy restrictions hold.
- Automated tests cover questionnaire wording/count, scoring, version locking, draft/resume, access, and history snapshots. Full Laravel tests and frontend build pass.
- Commit separately: `M30: Implement approved UCC questionnaire and versioned scoring`.

## M31 — Admin interaction polish, data statuses, and prediction eligibility (separate commit)

### What is wrong / what to change
1. **Eligible-student year level:** restrict all new eligible-student entry points (manual form, CSV import, and relevant updates) to integer year levels **1–4**. Show clear validation errors for 0, 5, 6, decimals, or missing required values. Do not destructively rewrite existing records outside 1–4; report them for administrative correction if they exist. Add server-side validation and tests (not only dropdown limits).
2. **Notification bell:** remove the bell from every role's navigation if it is only a decorative or redundant UI entry. Keep actual notification/event records, audit history, and important department-head prediction alerts intact. SweetAlert2 is for confirmations and flash feedback, not a substitute for asynchronous system events; maintain a discoverable workflow for important pending reviews (e.g. department dashboard/high-risk list).
3. **Interventions save feedback:** after Add/Edit/Activate/Deactivate, use consistent SweetAlert2 success/error feedback with a dimmed, properly stacked backdrop and accessible focus. Do not leave the vague green success banner inside the modal. Close/reset the modal at the correct moment, refresh the listing, and prevent duplicate submissions. Confirm destructive/status changes; explain the consequence. Check CSP/Livewire wiring so no `method: undefined` returns.
4. **Intervention "Minimum risk":** keep the backend rule-engine eligibility field; **do not remove it merely for visual simplicity**. Rename the admin label to `Eligible from risk level` (or `Minimum dropout-risk level`) and add concise help text: `Moderate = applicable to Moderate and High; High = applicable only to High. The rule engine still selects actions based on relevant factors; it does not mean the action is automatically applied.` If the current rule semantics differ, document and implement the *true* behavior instead of claiming this. Keep existing risk thresholds and stored intervention snapshots unchanged. Replace leftover "faculty adviser" phrasing with "department head" only where accurate; preserve institutionally approved intervention meanings.
5. **Academic structure activation controls:** ensure College, Department, and Program management clearly shows Active/Inactive state and offers authorized Activate/Deactivate actions where allowed, with SweetAlert confirmation, validation, and informative disabled states. Inspect M29's existing status flips first: reuse them rather than building duplicate controls. Respect the existing safety invariants: CLAS cannot be disabled; CLAS must retain an active department and active program; changing a parent must not silently widen pilot eligibility; preserve all foreign keys, user records, grades, predictions, and history. For entities intentionally ineligible for activation/deactivation, explain why in the modal or table; don't add a general destructive Delete.
6. **Student Dashboard:** remove the remaining `Assessment workflow progress — saved sections, not a prediction-accuracy score` card and its progress bar. Keep the compact assessment-step indicator *inside Assessment* only. Replace dashboard progress with a simple actionable CTA (`Complete/Update Assessment`) or missing-input note when needed; avoid duplicate navigation and large blank areas.
7. **Prediction eligibility — explicit policy:** A new prediction requires a **completed, valid submission of the currently accepted required questionnaire items** and consent. Grades are **optional**; when absent, use no invented academic values and clearly mark unavailable academic factors / reduced confidence. Skills, certifications, OJT/work experience are **optional**; absence must not be treated as proof of zero ability. A student updating only questionnaire or skills can request a new prediction using the **latest confirmed grades**, if any, without reuploading. Preserve the existing 24-hour cooldown, authorization, audit log, versioned input snapshot, and existing predictor behavior. If the current heuristic or FeatureBuilder cannot safely score a missing field without silently replacing it with zero/false, *do not silently relax the gate*: show `Insufficient information for a reliable estimate` for the affected output, and document the specific blocker. Never show a misleading percentage for a score the available inputs cannot support. Do not pass new C/P answers into Pianda or declare UCI integration complete.
8. Clearly distinguish **workflow completeness** from **model confidence** and from **risk classification**. A fully answered questionnaire can still produce limited-confidence estimates when academic history is missing. If a component is only heuristic, continue labeling it as a placeholder, not trained ML or a validated probability.
9. Optionally align the browser tab title `Deletion requests` with `Account deletion requests`, provided this is only a safe copy fix.

### Do not touch
- No PostgreSQL migration, FastAPI integration, ML retraining, new risk-band calibration, changes to external ML artifacts, or silent UCC-to-UCI/Pianda feature mappings.
- No `migrate:fresh`, mass deletion, `APP_KEY` changes, removal of stored notifications, or rewriting historical prediction/grade/questionnaire snapshots.
- No changes to currently enforced dean aggregate-only access, department-head student-level scoping, or encrypted socioeconomic access.

### Done when
- Year levels 1–4 are enforced in UI, server validation, and CSV import.
- Admin dialogs and SweetAlert confirmations give working, accessible feedback and persist expected changes; no generic `undefined` Livewire calls.
- Activation controls operate subject to M29 safeguards, with readable disabled-state reasons.
- Bell removed from all role shells without disabling important backend workflows.
- Dashboard contains no duplicated workflow progress card; Assessment still has one clear step indicator.
- Tested cases: questionnaire complete/no grades/no skills; questionnaire complete/old confirmed grades; incomplete questionnaire; changed only skills/questionnaire; cooldown; missing unsupported heuristic inputs. All produce correct actionability and truthful disclosures.
- Full Laravel test suite, relevant browser click-through, and `npm run build` pass; review migrations and Git diff. If a migration is needed, additive and backed up; never reset data.
- Commit separately: `M31: Polish admin controls and clarify prediction eligibility`.

## Execution / reporting
1. Review working tree before editing and ensure approved PDFs are present; respect their permitted sharing/commit status.
2. Implement and test **M30 only**, commit, then implement and test **M31 only**, commit. If a document or requirement conflicts with the approved sources, record the blocker rather than inventing details.
3. Report changed files, the policy implemented, migrations, test/build results, browser checks, any unmet acceptance criteria, commit hashes, and Git status. Stop after M31.
