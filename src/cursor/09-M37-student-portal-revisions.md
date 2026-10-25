# M37 — Student Portal, Assessment and UX Revisions

## Context and boundaries
- Base: M36 Khamba-inspired Tailwind/Blade/Livewire visual foundation. Use `src/cursor/ui-reference/khamba/` and `src/cursor/ui-reference/screenshots/` as visual references.
- Implement on `feature/m37-student-ux` (or the current approved M37 branch). Do not create/switch branches automatically.
- This milestone covers ALL student-facing revisions below, plus a shared modal standard for later Admin and staff milestones. Keep the current Laravel, Livewire, Alpine, Chart.js, Remix Icon, SweetAlert2, and Tailwind stack; do not add Bootstrap JS, jQuery, or Yajra.
- No deployments, cloud configuration, ML retraining, FastAPI integration, production migrations, destructive database resets, or rewriting old assessment/prediction snapshots.

## 1. Responsive page width and density
- Audit Dashboard, Assessment, History, Profile/Settings, grades, skills, certifications, experience, and student detail dialogs.
- Remove excessively narrow desktop `max-width` wrappers causing large side gutters. Use responsive full-available-width containers with consistent gutters and sensible paragraph/form line-length limits. Make cards and grids use space efficiently without horizontal overflow.
- Maintain the Khamba palette, spacing and component system; avoid excessive repeated text or empty blocks.

## 2. Approved questionnaire presented as a modal wizard
- On Assessment, open the questionnaire as a real accessible modal/drawer-style wizard; do not remove the existing versioned submission backend.
- Fixed/sticky modal header: title, instrument version/section, section number, saved and answered counts, section and overall progress. Show the counts truthfully: unsaved inputs must not be described as saved.
- Independently scrolling question-body area; fixed footer always visible with Cancel/Close, Back, Save Draft, Next/Save & Continue, and final Review/Submit as context requires. No need to scroll to the bottom to reach actions.
- Preserve autosaved/explicitly saved drafts, resume, Back, validation, final review/submit, and versioned immutable records. Confirm discarding unsaved data via SweetAlert2; Cancel must not silently discard saved drafts.
- Likert display order **Strongly Agree, Agree, Neutral, Disagree, Strongly Disagree**. Map to the existing numeric values correctly; do not reverse semantics or modify historical scoring.
- Support future reviewed versions with up to 10 items per section. **Do not invent, silently insert, activate or rescore questions**, and do not alter the current panel-approved instrument without renewed approval and a distinct version. Investigate the apparent 37-item count (A6/S6/B7/C8/P6/E4) versus earlier 36-item approved specification; report exact cause and do not silently correct stored data.
- Responsive on narrow screens and mobile keyboard, accessible labels/radio groups, focus trapping, Escape and focus restoration.

## 3. Remove Household Profile from the ACTIVE student assessment, safely
Decision: Household Profile is not an input to either currently trained M25–M27 research model. It supplies context and can influence the old Laravel **placeholder heuristic**, so do not claim it is categorically useless and do not simply delete data or remove dependencies blind.
- Audit actual read/write paths: household form, `SocioeconomicProfile`, feature builder, heuristic predictor, contributing factor explanations (including Scholarship, Study space, Household income band), predictions, history and any consent/privacy flows.
- Remove Household Profile as a required or visible **step for new/current active student assessment flows**, if the application can still complete assessment and request predictions with missing household information without fabricating inputs. Recalculate assessment step counts, navigation, eligibility/completeness messages and progress accordingly.
- Preserve existing encrypted household records and all historical prediction snapshots; no table/column deletion or bulk nulling. Existing stored snapshots must remain unchanged and readable.
- For new assessments lacking household data, explicitly omit missing household features; do not interpret missing as poverty, lack of scholarship, or a numeric zero. Remove Scholarship and other household factor attributions from **new** results when those features are not collected. Retain historical attributions on prior attempts with version label.
- Do not silently change the logic of `placeholder-heuristic-v0`. If safe removal requires a distinct heuristic version, altered eligibility rules, or model revalidation, **stop that functional change and report a precise blocker and safe proposed design** instead of inventing equivalence or fake confidence. Implement independent UI simplifications that are safe.
- Socioeconomic questionnaire Section S **is separate** from this household profile: preserve Section S, its six approved items, encryption, scoring, and privacy controls. This request DOES NOT delete Section S.
- Verify no role gains access to raw household responses.

## 4. Student dashboard and History
- Bold category titles; increase prominence and readability of scores and labels; convert contextual threshold flags into clear accessible badges/callouts (not plain red text).
- Reduce repeated explanatory paragraphs, consolidate formula/research cautions in accessible expandable disclosures while preserving visible key nonvalidated/heuristic caveats.
- Make Employability, Academic Wellness, Program Shift, Dropout Risk, Career Matches and Next Steps responsive and space-efficient. Preserve all saved values, version references, computed factors and historical snapshots. No invented ML probability/hiring likelihood claims.
- Investigate UCC-wide scholarship assumptions when household factor attribution is still displayed, and document the outcome instead of fabricating predictive relevance.

## 5. Skills / certifications / work experience actions
- Use compact icon-only Edit, Archive and Restore controls consistently. Provide tooltip, `aria-label`, keyboard focus and appropriately sized hit area.
- SweetAlert2 confirmation on both Archive and Restore, and on other consequential actions. Only close dialog on a successful backend operation. Show useful errors when save fails.
- Prevent double submissions and preserve archived rows/history and past prediction snapshots.

## 6. Grades (manual, paste, OCR when applicable)
- Recognize `Dropped`, `INC`, `5.00` (case/whitespace tolerant) and automatically set `Dropped`, `Incomplete`, `Failed` remarks respectively. Reconcile with the existing grade domain; protect confirmed historical versions and supersession.
- `Dropped`/`INC` are not passing numeric grades. Keep agreed GWA exclusion rules unless reliable official university rules say otherwise; do not silently equate them to 5.00 or change GWA semantics.
- Check every grade input path, correction/removal of auto-remarks, validation, and conflict with manually entered justified remarks. Add regression tests.

## 7. Two successful predictions per Philippine calendar day
- Limit to **two successful persisted predictions** per student per date in `Asia/Manila` (00:00–23:59:59 local). No consumption on failed/rejected/incomplete requests; no mutation of prior history.
- Apply server-side atomic/concurrency-safe enforcement compatible with PostgreSQL and current local database. Continue existing consent, questionnaire, authorization and audit controls. Display remaining attempts and clear state when exhausted; test exact midnight behavior and concurrent requests.
- Prepare an explicit extension point for an auditable administrator-granted *extra attempt* without changing or erasing the normal count; **do not build the admin controls here** (M38).
- Avoid changing research model, FastAPI or heuristic calculations.

## 8. Shared modal/footer and scrollbars
- Establish reusable modal layout contract for **every role**: stable header with close control, only body scrolling, permanently visible footer buttons, responsive max-height (`dvh` and mobile keyboard considerations), accessible focus trapping, Escape, focus restoration, correct SweetAlert2 z-index and no nested scrollbars where possible.
- M37 must **apply and browser-test across all student dialogs** (questionnaire, grades, skills/experience, history/detail, account controls as relevant). Record remaining Admin, Department Head and Dean dialogs as explicit acceptance requirements for M38/M39; do not claim all-role completion in M37.
- Restyle conspicuous native-looking modal scrollbar to a slim, legible, non-obtrusive Khamba-consistent thumb/track with Firefox and WebKit support, fallback behavior, and accessible scroll affordance. Do not hide it.

## 9. Confirmation and validation coverage
- Audit student draft discard vs save, questionnaire final submit, archive/restore, grade confirmation/replacement, prediction request, account changes and other consequential actions. Confirm truly consequential actions; don't interrupt routine save-draft with unnecessary confirmation.
- SweetAlert2 confirmation first where needed, persistent server validation and readable inline errors, loading/disabled states; prevent Livewire undefined method and duplicate action regressions.

## 10. Testing and stop conditions
- Run full Laravel tests, focused PostgreSQL tests as available, JS regression checks, `npm run build`, and real browser walkthroughs on synthetic accounts.
- Browser verify wide desktop, standard laptop, phone; no horizontal page overflow; every student modal footer pinned and scrollbar styled; wizard draft/save/back/reopen/complete; Likert values; skills Archive/Restore; grades special terms; quota day boundary/concurrency; Dashboard/History; account dropdown; no JS errors or chart regressions.
- Log actual tests and skipped flows; don't claim checks that weren't run. Ensure databases and source records remain unchanged except deliberate synthetic test records cleaned up.
- If removal of Household Profile would invalidate the current predictor, document the blocker explicitly, preserve accurate output labeling, and continue only safe independent student-side revisions.
- Commit M37 after verification; **do not push, merge, reset, checkout/switch branches, deploy, or touch Supabase production**. User handles Git operations. Report changed files, migrations, test results, browser verification, open blockers and commit hash.

## Later-role obligations (must carry forward)
- **M38 Administrator:** apply shared sticky-header/scrolling-body/sticky-footer modal contract and scrollbars to all admin dialogs; responsive full-width pages; consistent icon-only actions; Bootstrap-inspired/Khamba Livewire tables; dropdown role/status filters; hierarchy Academic Structure pages with toggles; PSOC managed dropdowns; activity date filters; account provisioning; auditable administrator prediction exceptions.
- **M39 Department Head + Dean:** apply same modal/footer/scrollbar standards and viewport use; student-style horizontal navigation where suitable; intervention selectable/expandable cards with confirm/review/close/toast and fixed footer; authorized CSV exports and printing; no dean-level student data leaks.
- **M40 final verification:** walk every modal/action in all four roles; authentication/authorization, PostgreSQL and production-adjacent (not production) smoke checks; stable existing M35 deployment.
