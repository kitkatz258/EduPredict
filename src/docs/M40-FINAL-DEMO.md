# M40 — Final demo checklist

Local isolated Docker at `http://localhost:8000`. MariaDB data was not reset. No Render/Supabase changes, no branch switch, push, merge, or deploy.

## Environment

| Item | Value |
| --- | --- |
| Branch | `feature/m36-khamba-ui` |
| App | Laravel 12, Livewire 4, Tailwind 4, Chart.js, SweetAlert2 |
| Student predictor | `placeholder-heuristic-v0` / `placeholder-heuristic-v1` (`PREDICTOR_DRIVER=heuristic`) |
| Migrations | None in M40. Additive M37–M39 tables left as-is. |
| Data writes | Disposable SYN-0003 questionnaire draft created then discarded. Count after cleanup: 0 responses. Other students, predictions, and grants untouched. |

Demo password is the seeded value in README section 9. Do not paste it into this file.

## Accounts and URLs (local)

| Role | Sign-in | First page |
| --- | --- | --- |
| Student | `SYN-0003` (Bea Navarro) or `SYN-0002` | `/student/dashboard` |
| Department Head | `depthead@edupredict.test` | `/department/dashboard` |
| Dean | `dean@edupredict.test` | `/dean/dashboard` |
| Administrator | `admin@edupredict.test` | `/admin/dashboard` |

Theme toggle is in the header (and account menu). Preference is `localStorage` key `edupredict.theme`.

## Walkthrough

1. Guest `/login`: theme toggle persists across sign-out. Split layout stays Khamba navy/indigo.
2. Student Assessment → Open questionnaire. Pinned header holds **This section** bar, **Overall** bar (saved sections / 6), and six letter dots. One Likert click moves This section from 0/6 to 1/6 (17%) with Overall still 0. Save & Continue completes section A, Overall 1/6, body `scrollTop` 0. Theme switch inside the wizard does not reset answers. 390px keeps both bars, dots, and the pinned footer. Discard draft via SweetAlert.
3. Department Head dashboard: Students and Reports live in the top nav only. Charts repaint in dark mode. Students list is BSIS-scoped. Status dialog shows estimates and interventions, not raw answers.
4. Department / Dean reports: compact **About these estimates** under the filters. CSV export and print keep generation time, model limits, and the “estimates, not guarantees” line. Dean tables are year/department/program counts. `/department/students` as dean returns 403.
5. Administrator dashboard and Users list follow the same theme tokens. No student table on the institution dashboard.

Print from a dark-mode session is forced onto white paper with the light KPI palettes.

## Tests

| Suite | Result |
| --- | --- |
| `docker compose exec app php artisan test` | 286 passed, 2 skipped, 36.26s |
| `docker compose -f docker-compose.postgres.yml exec app php vendor/bin/phpunit --configuration=phpunit.pgsql.xml` | 288 tests, 1 skipped, OK, 58.46s |
| `docker compose exec app npm run build` | Vite 7.3.6 succeeded (`app-0ehmwCoE.js`, `app-B7-us38A.css`) |
| Python FastAPI pytest | Not run. Host Python has no pytest; `ml` image has no test tree. |

`php artisan test --configuration=phpunit.pgsql.xml` warns that `--configuration` cannot be used twice; use `php vendor/bin/phpunit --configuration=phpunit.pgsql.xml` for a clean PostgreSQL run.

Skipped cases are the live research-service match and grade-OCR samples. They are not passes.

## Production handoff (not executed)

Follow `src/docs/M35-RENDER-DEPLOYMENT.md`. Keep `APP_KEY`. Leave `PREDICTOR_DRIVER=heuristic`. Do not auto-merge this branch. FastAPI stays a separate service with its own token.

## Limits

See `src/docs/KNOWN_GAPS.md`. Placeholder scores are not trained UCC probabilities. Pianda/UCI inference is research-only.
