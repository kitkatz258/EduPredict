# Q3.1 questionnaire release readiness

Branch: `feature/questionnaire-60-admin`. Ancestry includes Q1 `51ce99c`, Q2 `706b604`, and Q3 `a99a016`. This check did not switch branches, push, merge, deploy, or start the next ML milestone. Supabase and the shared MariaDB were not migrated. The only shared-database writes were a temporary SYN-0003 draft created while checking the live dialog, then deleted.

## Result

| Check | Result |
| --- | --- |
| Incomplete submit names the gap in a top-right warning toast | PASS |
| Dialog header no longer shows that banner | PASS |
| First unanswered question stays highlighted, focused, and scrolled into view | PASS |
| Saved answers and on-screen selections stay | PASS |
| Server still rejects an incomplete submit | PASS |
| Pointer auto-scroll in the same section | PASS |
| Auto-scroll off, resume, and section change do not keep scrolling | PASS |
| Reduced motion uses an instant scroll | PASS |
| Keyboard selection does not scroll the student away | NOT VERIFIED in the browser |
| Isolated SQLite suite | PASS |
| Frontend production build | PASS |
| Isolated PostgreSQL questionnaire migration | PASS |
| Full PostgreSQL application suite | NOT VERIFIED |
| Shared v2, v1, and the three open drafts | PASS |
| Browser publish of a new questionnaire version | NOT VERIFIED |

## Notice

An incomplete submit stays on the server. The student sees the existing SweetAlert2 warning toast (`toast` browser event, `position: top-end`) after the Livewire update that opens the first gap. The sticky dialog subhead no longer renders `data-submission-notice`.

Checked in the live dialog for Bea Navarro (SYN-0003), light desktop: the toast text was “Please answer all 60 questions before submitting. You still have 59 unanswered questions. We've taken you to the first unanswered question in Section A. Academic Factors.” The container class was `swal2-top-end`. No `data-submission-notice` node remained. Question 61 (A2) had `data-unanswered-target` and `aria-invalid`, and it received focus. The saved Agree on A1 stayed selected (“1 saved”). PHPUnit asserts the same toast payload for 59, 51, 1, and 39 unanswered questions, including the unsaved-selection sentence when the screen and the saved draft differ.

## Navigation and scrolling

Earlier in this check, with auto-scroll on, a pointer Agree moved `.dialog-scroll` to the next unanswered question in Section A (16px offset) and did not change section. With the toggle off (`edupredict.questionnaire.autoscroll=0`), the next Agree stayed put. With reduced motion, the recorded `scrollTo` behavior was `auto`. Opening Section E reset the scroller to the top. Reloading a saved draft did not scroll. Keyboard Space and ArrowRight did not check the focused radio in that browser, so “keyboard does not scroll” is only evidenced by the pointer flag in the script and by PHPUnit. It is not a completed keyboard-selection pass.

## Data left in the shared MariaDB

After the temporary draft was discarded:

- Open v2 drafts: response 25 (student 1, 3 answers), 26 (student 62, 12), 27 (student 63, 31).
- Student 4 (SYN-0003) has no questionnaire response.
- Answer rows: 510.
- Versions: `draft-v1` draft, `ucc-panel-approved-v1` published historical, `ucc-panel-revised-60-v2` published with `current_slot=1`.
- Items: draft-v1 23, v1 36, v2 60.

No extra version row was created. The first discard click in the earlier session did not remove the row; that leftover draft was deleted, and the second browser discard was confirmed against these counts.

## Tests and migration

`php artisan test` in the app container (SQLite `:memory:`): 317 passed, 2 skipped, 3543 assertions. Skips are the PostgreSQL sequence test inside `PostgresCompatibilityTest` and the live research-service test inside `ResearchInferenceWorkflowTest`.

`npm run build` completed (Vite, 69 modules).

Isolated PostgreSQL (`docker-compose.postgres.yml`, `phpunit.pgsql.xml`, database `edupredict_testing`): `StudentAssessmentValidationTest`, `QuestionnaireVersionManagementTest`, and `PostgresPortabilityTest` — 20 tests, 234 assertions, OK. That run applies the questionnaire-version migration on disposable PostgreSQL. The rest of the Laravel suite was not re-run on PostgreSQL.

`PredictionRequester` still only changes the current-version lookup to `QuestionnaireVersionCatalog::currentCode()`. Scoring, quota, and history were not changed. Section bands stay on the 3-decimal rounded mean; that limit is in `KNOWN_GAPS.md`. Six-by-ten remains the only publishable contract.

## Still open

- The prediction-history chart `aria-label` still says “Employability and dropout probability over time” for placeholder heuristic history. It was not changed here.
- A hosted database does not have `questionnaire_versions` until its own migrate. Render and Supabase were not touched.
- `draft-v1` has 23 items, not 20.
- Publishing a questionnaire version was covered by isolated feature tests, not by a browser happy path.
- Keyboard Likert selection was not completed in the browser.
