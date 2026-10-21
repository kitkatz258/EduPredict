# M32 defense verification

Verification used the existing Docker app at `http://localhost:8000` and the preserved MariaDB database. PHPUnit uses sqlite `:memory:` and did not write to MariaDB. No migrations, reseeds, model retraining, or deploys were run.

## 1. Environment and preservation

| Item | Value |
| --- | --- |
| Branch | `main` |
| Baseline commit | `9f20c0c` — Add M32 defense verification checklist |
| App | Laravel 12, Livewire 4, Alpine bundled with Livewire, Chart.js, SweetAlert2 |
| Database | MariaDB 11.4 in the `db` service (`DB_CONNECTION=mysql`) |
| Predictor | `placeholder-heuristic-v0` (`PREDICTOR_DRIVER=heuristic`) |
| Backup | `state/backups/edupredict-m32-20261022-041432.sql.gz` (gzip-tested; not committed) |
| `APP_KEY` line sha256 | `6a6cf7db3d1ed66f85d26f48e76b2e5a92c1e28511874fd600ff6c3932cadb00` (unchanged) |
| `.env` sha256 | `9eb425f025f81369d79762f9876613c4ade1733c3619b5c8268c59aec4b9901d` (unchanged) |

The backup was taken before browser writes. One synthetic department-head account was created to prove user persistence, then deactivated, then removed with the audit rows that did not exist in the backup (`audit_logs.id` 130–137). No student, prediction, questionnaire response, answer, grade, skill, consent, career match, or recommended action was inserted or deleted.

Counts after cleanup match the backup:

| Table | Count |
| --- | --- |
| users | 69 |
| students | 62 |
| institution_students | 63 |
| predictions | 63 |
| questionnaire_responses | 8 |
| questionnaire_answers | 120 |
| questionnaire_items | 57 |
| grade_reports | 63 |
| subject_grades | 323 |
| consents | 6 |
| career_matches | 20 |
| recommended_actions | 87 |
| audit_logs | 124 |
| student_skills | 143 |
| student_certifications | 0 |
| student_work_experiences | 31 |
| socioeconomic_profiles | 62 |
| interventions | 18 |
| psoc_occupations | 48 |
| colleges / departments / programs | 5 / 5 / 13 |
| account_deletion_requests | 1 |
| notifications | 2 |

`academic_tutoring` is active. Its `updated_at` of `2026-10-22 03:09:31` is already in the backup (an earlier deactivate/reactivate that was put back to active before the dump). This milestone did not change intervention text or activation.

## 2. Role matrix

Browser checks used the Cursor browser against `http://localhost:8000`. Automated rows are PHPUnit on sqlite unless noted.

| Role | Workflow | Expected | Actual | Result | Method |
| --- | --- | --- | --- | --- | --- |
| Guest | Invalid sign-in | Credential error, no session | “These credentials do not match our records.” | PASS | Browser |
| Guest | Sign-in / Create Account toggle | URL and panel switch | `/register` shows the registration panel; sign-in panel hides | PASS | Browser |
| Guest | Register a number that is not eligible | Rejected, no user row | The first attempt filled the hidden sign-in password, so the page asked for a password. No user was created. A clean “not on the list” message was not rechecked | NOT TESTED | Browser attempt |
| Guest | Show/hide password | Value toggles | Button switches to “Hide password” and the value stays | PASS | Browser |
| Guest | Forgot password | Recovery form, no mail sent | `/forgot-password` explains the reset email. The form was not submitted | PASS | Browser |
| Student | Sign in `2024-00001` | Dashboard with disclaimer | Employability 56, moderate risk, `placeholder-heuristic-v0`, two charts, no console errors | PASS | Browser |
| Student | Open `/admin/dashboard` | 403 | “You do not have access” | PASS | Browser |
| Student | Assessment | Do not rewrite the legacy draft | Page resumes “Draft research instrument v1” (1/21 saved). No save or discard was clicked | PASS | Browser |
| Student | Start `ucc-panel-approved-v1` on a student with no draft | 36 items, save, resume, submit | Not run. The demo student still has an open `draft-v1` draft. Completing the approved instrument would add a response and could create a prediction | NOT TESTED | — |
| Student | Add skill with a blank name, then Escape | Validation, dialog closes, list unchanged | “The skill name field is required.” Escape removed the dialog and `overflow-hidden`. “3 skills listed” stayed | PASS | Browser |
| Student | History snapshot | Read-only saved attempt | Oct 8 attempt shows 56 / moderate and `placeholder-heuristic-v0`. Escape was sent; close was not rechecked before the next navigation | PASS for view | Browser |
| Student | Request a new prediction | Refused without the current questionnaire | Button disabled. Copy says a completed current questionnaire is still needed. No prediction row was added (still 63) | PASS | Browser |
| Student | Grades upload, OCR, archive/restore | Optional paths and immutable versions | Not exercised in the browser, so demo grades and skills were not rewritten | NOT TESTED | Existing feature tests |
| Department head | Dashboard | Program scope, no names | “Computer Studies · Bachelor of Science in Information Systems”, 9 students, no student names | PASS | Browser |
| Department head | Students list and status dialog | In-scope student, no raw socioeconomic answers | Status dialog shows the estimate, `placeholder-heuristic-v0`, and a private-answers notice. No income or household fields | PASS | Browser |
| Department head | Close that status dialog | Dialog closes | The close control was clicked. Completion was not rechecked before leaving the page | NOT TESTED | Browser attempt |
| Department head | Open a student outside the program (`/students/2`) | 403 | 403 page | PASS | Browser |
| Dean | CLAS dashboard and year filter | Aggregates only, charts update | Year filter changed the student count. No student names. No `fullSize` console error after the chart fix | PASS | Browser |
| Dean | `/students/1` and `/department/students` | 403 | Both return “You do not have access” | PASS | Browser |
| Dean | Sidebar wheel | Wheel on the sidebar does not scroll the page | `#staff-sidebar` has the wheel handler and the event was `defaultPrevented` | PASS | Browser |
| Faculty | Active legacy faculty sign-in | Rejected after the attempt | “This account type is no longer used. Contact an administrator.” Stayed on `/login` | PASS | Browser |
| Admin | Dashboard program filter | Counts and charts update together | Computer Studies filter narrowed programs and redrew charts with 0 console errors | PASS | Browser |
| Admin | Add user with no department | Readable validation, no row | Browser originally showed the framework default. The form now says “Choose a department.” The new sentence is covered by `AdminAccountsTest` and was not rechecked in the browser | PASS | Browser, then test |
| Admin | Create and deactivate a staff user | SweetAlert cancel keeps the account; confirm persists | Cancel left the account active. Confirm set `is_active` to 0. The synthetic account was deleted after the check | PASS | Browser and database |
| Admin | Eligible student, empty save, Escape | Validation, no new row | Required-field errors. Escape closed the dialog. List stayed “63 results” | PASS | Browser and database |
| Admin | Eligible-student CSV | Valid and malformed files persist or reject | Not run in the browser | NOT TESTED | Existing import tests |
| Admin | Questionnaire version filter | Approved set stays 36 and is not edited | Filter shows 36 results. No item was saved or deactivated | PASS | Browser |
| Admin | Activity log | Readable columns and pagination | “Activity Log”, 10 per page, action filter | PASS | Browser |
| Admin | Deletion requests | Status tabs; approval not taken | Pending, Approved, Rejected, and All are present. No request was approved or rejected | PASS for display | Browser |
| Admin | Academic structure, PSOC, interventions | Open, edit, cancel | Authenticated fetch returned 200 and the expected titles. Add/edit/activate were not clicked | NOT TESTED | Fetch only |
| All | Mobile drawer and focus restore | Drawer and focus return to the opener | Not checked | NOT TESTED | — |
| All | Dialog backdrop click | Same close method as Cancel | A coordinate click landed on the dialog heading, not the backdrop | NOT TESTED | Browser attempt |

Shared dialog Escape was confirmed on the skill dialog and the eligible-student dialog: the Livewire close attribute was `closeModal` or `closeForm`, the dialog left the page, and body scroll lock was removed. No Livewire payload with method `undefined` was observed on the checked saves and closes.

## 3. Defects

### Chart update threw after a dashboard filter

Filters changed the totals, then Chart.js threw `TypeError: Cannot set properties of undefined (setting 'fullSize')`. Chart instances were stored on Alpine state, so the next `update()` ran against a proxy whose legend and title blocks were missing.

`dashboard-analytics.blade.php` now keeps charts on `window.__edupredictCharts`, destroys the previous instance, and creates a new chart. Admin and dean filters were repeated in the browser with zero console errors. `DashboardAnalyticsTest` asserts the registry is rendered and the old `this.charts[id]` / `.update()` path is not.

### Dialog close could call Livewire method `undefined`

A click listener’s Alpine `$el` is the button. Reading `data-dialog-close` from the button produced method `"undefined"` and `MethodNotFoundException`.

`dialog-close.js` walks to the dialog host and returns an empty string unless the method name is a plain identifier. The dialog component uses that helper and the same fallback when the bundle has not loaded. `tests/js/dialog-close.test.mjs` covers a real close button, a missing attribute, and the literal `undefined`. `UxRegressionTest` asserts the skill dialog renders `data-dialog-close="closeModal"` and does not render `$wire.$call(undefined)`.

### Empty staff form used a framework field name

Saving a department head with no department showed “The department id field is required.” `CreateStaffForm` now uses “Choose a department.” and “Choose a college.” `AdminAccountsTest` asserts the department message.

No other confirmed defect was changed. Backdrop-click and focus-restore behavior are implemented in the shared dialog and were not fully exercised, so they were left as-is.

## 4. Demo limitations

- Estimates in the app are `placeholder-heuristic-v0`. They are rule-based and are not validated probabilities.
- M25 and M27 machine-learning artifacts stay research outputs. They are not wired into the UCC prediction the demo runs.
- `ucc-panel-approved-v1` is the panel-approved wording and scoring. It is not a psychometrically validated instrument.
- A student who still has a `draft-v1` draft resumes that draft. The approved 36-item instrument is the current version for a student without that draft.
- Faculty accounts cannot sign in.
- The public About page is not part of the app. There is no custom date-range filter. Content Security Policy still allows inline scripts. OCR checks skip when sample files are absent.

## 5. Commands, evidence, and demo sequence

```text
docker compose exec -T app node --test tests/js/dialog-close.test.mjs
# tests 3, pass 3, fail 0

docker compose exec -T app php artisan test --compact
# Tests: 236 passed (2592 assertions), Duration: 45.77s

docker compose exec -T app npm run build
# vite v7.3.6, 67 modules, built in 2.42s
# public/build/assets/app-BbLd1QDx.js includes edupredictDialogClose and __edupredictCharts

docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan view:clear
```

Browser evidence is the Cursor browser session on `http://localhost:8000` described in the matrix. Console checks on the student dashboard, the dean year filter, and the admin program filter reported no JavaScript exceptions. Database checks used counts and the synthetic user’s `is_active` flag, not only HTTP status.

Safe demo sequence, without creating a new prediction:

1. Sign in as the seeded student `2024-00001`. Show the disclaimer, career matches, and History. Leave the disabled “Request new prediction” control as the eligibility example.
2. Sign out. Sign in as `depthead@edupredict.test`. Show the BSIS dashboard, then one in-scope status dialog. Do not open another program’s student.
3. Sign out. Sign in as `dean@edupredict.test`. Change a filter and show that names are absent.
4. Sign out. Sign in as `admin@edupredict.test`. Change a dashboard filter so the charts redraw. Open Users or Eligible students only to show a dialog, then cancel.

Use the seeded demo password already in the project. Do not complete a questionnaire or upload a grade file on these accounts during the demo.
