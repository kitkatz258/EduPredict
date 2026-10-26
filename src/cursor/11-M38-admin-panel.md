# M38 — Administrator Portal: Panel Revisions

## Objective and working rules
Implement the remaining October 22 panel revisions for the Administrator portal on a dedicated development branch based on M37.2 (`0c06cdbc`). Preserve the completed M33–M37.2 flows, Khamba/Tailwind/Livewire implementation, data and immutable snapshots, Render/Supabase deployment configuration, and role authorization. The user handles Git branch creation, merge, push, and deployment. Agent may commit verified changes locally but must not push, switch branches, deploy, or run `migrate:fresh`. Never connect to or write production Supabase. Work with synthetic local data. Read `src/cursor/01-initial-project.md`, `src/cursor/rules/edupredict.mdc`, M36–M37.2 changes and `src/cursor/ui-reference/{khamba,screenshots}`. Check actual file paths first.

## P0: Admin shell and shared modal consistency
- Apply Khamba navy/indigo/light design to ALL administrator screens: Dashboard, Users, Eligible Students, Colleges, Departments, Programs, Questionnaire, PSOC, Interventions, Activity Log, Account Deletion Requests, Profile and Settings. Responsive width and consistent gutters, no large dead zones or horizontal overflow.
- Sidebar: use a cohesive scrolling nav and account region, preserving collapse-to-icons and mobile drawer. `Academic structure` is a **parent group**, expanding to **Colleges**, **Departments**, **Programs**, each with its own routable page. Clicking parent toggles group, not navigation. Correct active-state, keyboard and role checks; preserve legacy deep links or redirect safely.
- All administrator dialogs and drawer forms must have a persistent visible header with Close, an independently scrolling body with subtle visible scrollbar, and persistent footer actions (Cancel, Save, Confirm). Confirm before destructive/important operations with SweetAlert2; only close on successful server response; no fake success or undefined Livewire method. Restore focus, Escape/backdrop semantics, avoid overlapping scroll locks, verify mobile.
- Apply usable interactive styling across all administrator buttons and icon buttons (pointer cursor, hover border, focus ring, labels/tooltips, keyboard).

## P1: Tables, filters, ordering, actions
- User asked for Bootstrap-style data tables, but **do not add jQuery/Yajra/Bootstrap JS** unless absolutely necessary and compatible with Livewire; use the current server-side Livewire tables with Khamba/Bootstrap-like presentation: compact headers, striped/hover rows, responsive horizontal scroll when needed, search, sorting, per-page, pagination, empty and loading states.
- Normalize ALL administrator table pages (not just Users). Keep existing server-side scopes, permission checks and queries; no client-only filtering of confidential data.
- Make **View, Edit, Activate, Deactivate, Archive, Restore** table actions icon-only with accessible `aria-label`, title/tooltip, semantic styling, focus and hover; avoid ambiguous destructive icons. Keep important Add/Export buttons with text.
- Users: role and status as **dropdown filters**, not segmented/sliding toggles. Include only active roles; faculty legacy cannot be newly created or signed in. Newest created rows first by default (stable id tie-breaker), preserving explicit user sorts. Consistent new-row visibility after insert.
- Academic structure: status as accessible Activate/Deactivate **toggles** with confirmation and backend checks. Never delete linked records; preserve CLAS pilot rules, inactive program registration restrictions, and last-required active structure constraints.
- Activity Log: useful start/end **date-range** filter, actor/action/category filters when supported, search, sort, paginated export if supported. Validate range and boundaries server-side. Restrict access and avoid exposing private questionnaire/household contents in logs or export.
- Account deletion requests: clear Pending/Approved/Rejected/All dropdown, correct label that approval disables sign-in rather than deleting immutable records.

## P2: PSOC and interventions
- PSOC code entry: audit the official PSA PSOC 2012 hierarchy and current schema first. Provide **validated code selection or safe generation only where the source catalog permits**—do not invent authoritative codes or auto-increment arbitrary identifiers. Major groups as predefined dropdown from verified catalog; related program codes as multi-select from existing CLAS program catalog; skill tags from existing controlled list with an auditable add-option. Validate and avoid destructive schema changes.
- PSOC Add/Edit use consistent pinned header/footer, success SweetAlert, and action icons. Show newest added record first by default. Preserve deterministic matching and prior career snapshots. No made-up official occupations.
- Interventions: icon-only actions, clearer eligible-from-risk-level explanation, active/inactive controls, confirmations and post-save toasts. Retain institutional intervention catalog as the only selectable intervention source; do not rewrite actions stored on earlier predictions. Never let AI invent new intervention decisions.

## P3: Student account provisioning (security-sensitive)
- Remove the **public Create Account** link/form from login. Keep student-number sign-in for institution-provisioned enrolled students only. Admin import and eligible-student records must provision accounts via idempotent, validated, audited operations; prevent duplicates/collisions and unintended account activation.
- User requested surname as an initial password without a forced password reset; **do not silently implement guessable surname-only credentials for live accounts**. Create a safe, demonstrable temporary-credential issuance method or explicitly stop and report the choice requiring approval. At minimum, enforce rate limits, no plaintext credentials in logs/exports, secure password hashing, safe reset/recovery and optional in-account password changes. Preserve current existing user logins and account histories. New student login behavior must be verified end-to-end.
- Keep admin-only eligible student Add/CSV workflows; year range 1–4. Handle claimed versus unclaimed records cleanly.

## P4: Audited extra prediction grants
- Implement an administrator-only UI to grant one or more extra *successful* prediction slots on a specified Asia/Manila calendar date to a specific student, using the existing `prediction_attempt_grants` model/table from M37. Avoid altering normal two-per-day usage count or historical rows. Check exact existing schema; do not guess.
- Require justification and optional expiry/target date, validation, authorization, CSRF, audit, duplicate handling, revocation semantics if schema supports it, and clear success/denial feedback. Enforce concurrency-safe quota checks in database-backed service; verify PostgreSQL. Do not create new predictions through the admin UI; only authorize exceptions. No raw questionnaire access.

## Tests / verification / limitations
- Test role restrictions including Dean/Dept Head forbidden admin endpoints; filter searches/sorts/pagination; all modal footer and close cases; buttons and confirmations; CLAS toggle guards; users/eligible import and initial login flow; PSOC controlled inputs; intervention history; date-range logs; grant and quota across midnight and concurrent/duplicate attempts (where practicable).
- Use tests `php artisan test`, PostgreSQL-targeted tests if isolated environment available (`php vendor/bin/phpunit --configuration=phpunit.pgsql.xml`), and `npm run build`; browser-check desktop, mobile, all admin modules. Avoid production URLs, credentials, DB writes, and unrequested data cleanup.
- Keep migrations additive and safe. Never silently reinterpret old data. Report verified vs unverified items, changed files, test results, preserved data, risks and commit hash. **Do not start M39/M40.**


Additional M38 requirements to include with the existing spec:

1) Guest login/auth page redesign
Use the uploaded “Spacer” auth page only as a visual/layout reference, not as a literal copy.

Design goals:
- Rebuild the guest auth page in the EduPredict stack using Blade, Tailwind, Alpine/Livewire only.
- Keep the real authentication logic, validation, CSRF, rate limiting, password flows, and existing routes.
- Do not introduce fake sign-up behavior or demo logic.
- Use a split auth layout:
  - Left side: branded visual panel
  - Right side: actual auth form area
- Replace the blue theme with EduPredict’s accent color blended into a gradient that leans toward dark blue/navy.
- Keep it visually consistent with the Khamba-inspired UI already used in the app.
- Use EduPredict / UCC branding instead of “Spacer”.
- If there is a register page or create-account page in the current app, apply the same visual language there too.
- Preserve mobile responsiveness: stack cleanly on smaller screens.

2) Questionnaire wizard progress must be restored/fixed
The questionnaire wizard still does not show the intended progress properly. Fix it as part of this milestone.

Required behavior:
- Restore the two progress indicators inside the questionnaire modal.
- Progress must clearly show:
  a) answered count
  b) saved count
  c) current section progress
  d) overall questionnaire progress
- Answered count must update immediately when a choice is selected.
- Saved count must update only after the answer is successfully saved.
- Reintroduce/keep the section-level visual progress treatment the user asked for:
  - clear section indicators for the 6 sections
  - current section visibly distinct
  - completed vs incomplete vs not-started visually distinct
- If the previous implementation removed the top progress UI, bring it back in a stable form.
- After Save & Continue, the modal body must return to scroll position 0.
- The modal footer must remain pinned and usable.
- No buggy count text, no disappearing progress UI, and no horizontal overflow on narrow screens.

3) Scope note
Even though these items touch the student/guest side, include them in M38 now because M38 has not been run yet. Treat them as part of the same implementation pass, along with the existing Administrator milestone requirements.

4) Verification required
Verify in browser:
- login page renders with the new split layout
- mobile auth layout works
- questionnaire modal shows the expected progress UI
- answered count updates on selection
- saved count updates after save
- section progression remains correct
- scroll resets to top after Save & Continue

Do not push or deploy. Commit locally only after verification.