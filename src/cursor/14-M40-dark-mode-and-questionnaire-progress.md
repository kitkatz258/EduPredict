# M40 Addendum — All-role Dark Mode and Questionnaire Header Progress

**Priority: mandatory M40 acceptance criteria.** This addendum supplements `src/cursor/13-M40-final-demo-qa.md` and overrides any prior acceptance claim that the questionnaire progress is complete merely because numeric counters or section chips exist. Implement, inspect in a real browser, and document proof. Do not call M40 complete until all items pass or are clearly reported as blocked.

## A. Persistent light/dark mode for all roles

- Add a discoverable light/dark theme toggle to the existing shared account menu or header, accessible to Student, Administrator, Department Head, and Dean; apply consistently to login/guest, profile/settings, dashboards, assessment, history, all tables, modals, reports, and empty/error states. Follow Khamba's navy/indigo identity in both modes. Do not import Bootstrap JS, jQuery, or Khamba demo theme script.
- Use one shared theme mechanism (Tailwind 4 + CSS tokens/class or data attribute) rather than per-page overrides. Persist each user's preference (localStorage acceptable for local browser preference) and apply it before first paint to avoid flashing. Support system preference when no choice is saved. Do not store sensitive user data in localStorage.
- Verify interactive states, focus indicators, validation messages, semantic risk/warning colors, disabled buttons, transparent overlays, charts (legends, labels, axes, tooltips), scrollbars, navigation, modal headers/footers, SweetAlert2, printed reports (always readable on white), and no invisible text or controls. Switching the theme must not reset Livewire forms or destroy Chart.js instances.
- Responsive mobile/desktop and keyboard accessibility; do not interfere with existing auth, role-scoping, or stored records.

## B. Actual visible wizard progress in the *fixed questionnaire modal header*

**This is a reported regression across several attempts. Do not substitute counters or chips for the requested bars.** The attached user reference shows a fixed header and an independently scrolling body. Within the modal HEADER, render and visibly style BOTH:

1. **This section:** a horizontal progress bar reflecting answered questions in the current section, calculated immediately from current browser selections, before Save Draft or Save & Continue. Label `This section · X / Y answered` and separately indicate saved count if useful.
2. **Overall:** a visible horizontal progress bar based on *completed/saved sections out of six* (not overall answered questions), plus six distinct section dots/icons with `not started`, `in progress/current`, and `completed/saved` states. Use section names/accessible labels and do not confuse 'answered locally' with 'saved'.

- Place both progress bars and section-dot navigation in the fixed/sticky modal header **above the scrollable questions**. If limited space on mobile, wrap/compact the header, but both indicators must stay visible; do not hide them at responsive breakpoints or tuck them inside the scrollable body.
- Answering/changing a Likert choice updates the current-section bar immediately and correctly (without a server request or save); saving updates the saved counters, completion state, and overall sections bar only after successful persistence. Reopening a saved draft must reconstruct the correct progress. Discard draft must reset appropriately. A submitted response must remain immutable.
- After Save & Continue: navigate to the next section, reflect the correct dots/bars, and reset **the modal body** scrollTop to 0. Footer remains pinned with Cancel/Back/Save Draft/Save & Continue, including on 390px phones. Ensure the `This section` and `Overall` bars have visible contrast and accessible progressbar labels/values.
- Avoid conflicts with Livewire DOM morphing, Alpine, and modal focus handling; no `undefined` method errors, stale counters, or loss of draft answers. Existing Likert order/value mapping remains Strongly Agree (5) -> Strongly Disagree (1).

## C. Mandatory browser verification: do not claim completion on tests alone

Run a full real browser flow in a disposable synthetic student's *unsubmitted* questionnaire, and capture screenshots / describe exact on-screen placement. Verify and report each case individually:

1. Open wizard on desktop; **both horizontal bars and all six dots are visibly inside the pinned header** while scrolling to question 8.
2. Start a section with 0 of 8 answered; select one choice; `This section` visibly increases from 0% to 12.5% **before save**. `Overall` saved-section progress does not increase yet.
3. Save draft; verify persisted counts. Save & Continue after finishing a section; `Overall` advances by one of six, current dot changes, body scrolls to top. Return/reopen and confirm correct state.
4. Switch theme while wizard is open; both progress bars, selected answers, header/footer, scrollbar and modal remain readable and functional with no reset.
5. Inspect 390px mobile viewport: modal header progress visible, no clipped labels or footer, no page horizontal overflow.
6. Verify dark/light theme across Student, Administrator, Department Head, Dean, guest login, dialogs, Chart.js, SweetAlert2, and reports.
7. Inspect browser console for errors and run Laravel suites, relevant JS checks, PostgreSQL where possible, and Vite build. If browser tooling cannot take screenshots, state that and give exact observed DOM and test steps instead; never claim visually verified when only source code/tests were inspected.

Use synthetic test data; clean up only your own disposable drafts; leave other student records and prediction history untouched. No production database edits, no deployment, no branch switching/pushing/merging. Commit locally after verified. If an item cannot be verified or works only in source but not the browser, report it as **unfinished** and prioritize a targeted fix before proceeding.

## D. Carry forward core M40 scope

This addendum does not replace M40's role/security tests, real intervention review, provisioned login + consent, grade persistence, quota grants, export/printing, and production-readiness checks. Prioritize broken grade/prediction flows and this questionnaire regression; no new ML/heuristic calculations or broad refactoring.

======================================================================

### M40 Addendum — Staff Dashboard and Report Layout Cleanup

Apply these corrections to Department Head and Dean dashboards and reports, checking Administrator reporting screens for the same presentation issues.

1. Remove the redundant Students and Reports shortcut buttons from the upper-right of the Department Head dashboard. Remove redundant Reports shortcuts from the Dean dashboard where applicable. Keep functional links in the horizontal navigation.

2. Remove the large technical metadata/disclaimer paragraph currently displayed beneath report filters. This includes generated timestamps, model-version explanations, program-shift descriptions, and repeated estimation warnings.

3. Do not remove scientifically necessary limitations from the system entirely. Keep a concise disclosure accessible through a compact "About these estimates" section. Preserve appropriate disclaimers and generation metadata in CSV exports and print output without occupying unnecessary space in the main on-screen report layout.

4. Standardize spacing between page title, description, filters, actions, summary counts, and report table across Department Head and Dean pages.

5. Inspect Administrator dashboards, reports, and export interfaces for the same issues. Apply the same cleanup only where the corresponding components exist.

6. Preserve all existing statistics, Chart.js behavior, filtering, authorized records, report contents, CSV safety, print functionality, and role restrictions. Dean reports must remain aggregate-only.

7. Browser-verify both staff roles, dashboard navigation, report filters, CSV exports, print layout, and desktop/mobile responsiveness.

8. Add relevant regression checks. Do not modify prediction calculations or historical data.

Include this addendum in M40 verification. Commit only after testing. Do not push, merge, or deploy.