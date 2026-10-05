# Known gaps

These items are unfinished on purpose or are limits of this build. They are not open defects in the milestones M0–M12.

- The employability and dropout models are still the deterministic placeholder `HeuristicPredictor` (`placeholder-heuristic-v0`). `http` and `onnx` drivers throw until a trained model is approved. Do not describe placeholder output as a trained result.
- The PSOC table is a starter set. Verify it against the official PSA PSOC 2012 before a final submission.
- `grades.inc_gpa_weight` defaults to 4.00 and must be confirmed with the registrar. NSTP codes are excluded from GPA.
- `is_major_subject` defaults to false on newly entered grades, so a live program-fit result appears only when major subjects are marked. The seeded cohort stores shift labels for the demo story; those stored labels are not recomputed from the grade rows on each page load.
- Account deletion approval deactivates the login and keeps prediction history. It does not hard-delete academic records.
- Dashboard charts have no date-range filter and no career-match trend. Risk and program-shift figures are counts.
- Content-Security-Policy allows inline and `unsafe-eval` scripts because Livewire and Alpine need them.
- On Windows, Docker bind-mounted `src/` makes the first PHP response slow. That is the filesystem, not a query problem. Do not turn on config, route, or view caches for day-to-day development.
- OCR integration tests skip when `src/cursor/samples/` is missing the portal files or when Python or Tesseract is not available. Parser tests against the expected JSON still run.
- Real grade-report images are gitignored. Do not commit personal grade documents.
- phpMyAdmin can be used to confirm socioeconomic ciphertext. The automated suite already asserts those columns are not stored as plaintext.
