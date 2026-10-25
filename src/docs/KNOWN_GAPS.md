# Known gaps

These items are unfinished on purpose or are limits of this build. They are not open defects in the milestones through M21.

- Student predictions remain the deterministic placeholder `HeuristicPredictor` (`placeholder-heuristic-v1`). Attempts saved as `placeholder-heuristic-v0` stay on that version. The FastAPI service scores saved Pianda and UCI research artifacts only. `PREDICTOR_DRIVER=http` does not score a student assessment, and `onnx` still throws. Do not describe placeholder output or research-demo output as a validated UCC result.
- The PSOC table is a starter set. Verify it against the official PSA PSOC 2012 before a final submission.
- INC is left out of the GWA, which is then marked provisional. The UCC portal appears to count INC as 4.00, so its GPA can differ; confirm the official rule with the registrar. NSTP codes are excluded from GPA.
- Predictions made before grade snapshots existed have no stored grade version; history should show "Not available for this attempt" for them.
- `is_major_subject` defaults to false on newly entered grades, so a live program-fit result appears only when major subjects are marked. The seeded cohort stores shift labels for the demo story; those stored labels are not recomputed from the grade rows on each page load.
- Account deletion approval deactivates the login and keeps prediction history. It does not hard-delete academic records. The profile page does not delete an account; students submit the request from the privacy notice.
- Faculty remains a legacy role so old rows still load. Those accounts cannot sign in and are not offered in active screens.
- The public About page stays removed. Model limits are on the privacy notice.
- Dashboard charts have no date-range filter and no career-match trend. Risk and program-shift figures are counts.
- Content-Security-Policy allows inline and `unsafe-eval` scripts because Livewire and Alpine need them.
- On Windows, Docker bind-mounted `src/` makes the first PHP response slow. That is the filesystem, not a query problem. Do not turn on config, route, or view caches for day-to-day development.
- OCR integration tests skip when `src/cursor/samples/` is missing the portal files or when Python or Tesseract is not available. Parser tests against the expected JSON still run.
- Real grade-report images are gitignored. Do not commit personal grade documents.
- phpMyAdmin can be used to confirm socioeconomic ciphertext. The automated suite already asserts those columns are not stored as plaintext.
- M36 restyles the shared shell, the administrator dashboard, and the administrator Users list. Other lists still use their earlier table markup. There is no dark mode and no global search. Khamba's store pages, demo login, and Bootstrap widgets are not part of the app.
- M37 applies the pinned dialog header, scrolling body, and pinned footer to student dialogs: questionnaire, skills, certifications, experience, career detail, grade-report history, and prediction history. Administrator, department-head, and dean dialogs inherit the sticky header and scrolling body. Their action buttons still sit in the body until M38 and M39 move them into the footer slot. The inline grade editor is not a dialog. Its action bar is sticky. There is no administrator screen for extra prediction attempts. A published household profile is kept and is not used by a new prediction. True multi-process concurrency of the daily quota was not load-tested. The student row lock is the enforcement. SQLite `lockForUpdate` does not block a second connection.
