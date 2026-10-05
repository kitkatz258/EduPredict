# EduPredict implementation decisions

One line per decision. Record choices made where the spec is silent.

- M0: Installed Livewire 4.4 (latest stable compatible with Laravel 12) as class-based components; did not pin to v3.
- M0: Demo table lists `users` until domain tables exist in later milestones; it is a public development page at `/demo/table`.
- M0: Application timezone is `Asia/Manila`; currency is PHP (no money library yet).
- M0: Guest welcome page is a branded landing with a non-functional sign-in card until Breeze auth lands in M1.
- M0: Chart.js is imported in `resources/js/app.js` as `window.Chart`; Alpine is not installed separately.
- M0: Uploaded grade files will be deleted after confirmation (recorded here as required by the spec; implemented in M3).
- M0: `grades.inc_gpa_weight` defaults to 4.00 to match portal GPA on the 2025-2026 sample; confirm with the registrar before treating this as official policy.
