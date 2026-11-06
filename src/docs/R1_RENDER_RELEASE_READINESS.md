# R1 — Render release readiness

Checked on `integration/q31-d1` after `c550b82`. No production database was opened. Render was not deployed. Nothing was pushed.

## Results

| Check | Result |
| --- | --- |
| Branch contains Q1–Q3.1 (`dceff30`), M42, M42.1, M43, and D1 (`463b4c0`) | PASS |
| PostgreSQL questionnaire-position fix (`c550b82`) is on the branch | PASS |
| `render.yaml` keeps `PREDICTOR_DRIVER=heuristic` and does not host the research service | PASS |
| Render boot migrates and seeds only an empty `users` table. It does not install the 60-item catalog | PASS |
| Populated-database command checks state, dry-runs by default, and rolls back a failed post-check | PASS |
| `edupredict:activate-revised-questionnaire` still refuses production | PASS |
| Student scoring stays on `placeholder-heuristic-v1`. Research artifacts stay offline | PASS |
| README and `CLAUDE.md` name `placeholder-heuristic-v1` as the current default. Stored `placeholder-heuristic-v0` rows stay historical | PASS |
| Likert keyboard behavior is native radio selection. No app defect was reproduced | PASS |
| Supabase still has not received `questionnaire_versions` or the 60 items | BLOCKED |
| Students must stay off the questionnaire during the boot window after migrate | BLOCKED |
| Render auto-deploy is still enabled in `render.yaml` | BLOCKED |

## What the populated database receives

`2026_11_02_100000_create_questionnaire_versions_table` creates `questionnaire_versions` and inserts three catalog rows:

- `draft-v1` stays a draft and is not current.
- `ucc-panel-approved-v1` stays the published historical 36-item catalog and is not current.
- `ucc-panel-revised-60-v2` is inserted published, panel-approved, and current.

The migration does not insert questions and does not update students, answers, submissions, or predictions. `edupredict:seed-demo-if-empty` still returns when any user exists, so an already populated Supabase database does not receive the 60 items from boot.

After that migration, `ucc-panel-revised-60-v2` can be current while it still has zero questions. `QuestionnaireVersionCatalog::currentCode()` also falls back to that code from config. A current version with no active items lets `PredictionRequester::hasCurrentQuestionnaire()` treat any submitted response, including a historical 36-item response, as the current questionnaire. This release does not change that eligibility rule. Students stay off the site until the command below reports a passed post-check.

## Authorized update

`edupredict:prepare-approved-questionnaire` is the production path. It is not in `docker/render/entrypoint.sh`. Render free has no shell, so an operator runs it from a trusted machine against the Supabase session pooler after the backup.

Dry-run is the default and writes nothing:

```bash
php artisan edupredict:prepare-approved-questionnaire
```

A write requires both flags:

```bash
php artisan edupredict:prepare-approved-questionnaire --execute --confirm-preserves-history
```

The command stops before any write when any of these is true:

- `questionnaire_versions` does not exist, or any of the three catalog rows is missing or duplicated.
- More than one version is current.
- A version other than `ucc-panel-revised-60-v2` is current.
- The revised row is not published, not panel-approved, or not `six-by-ten`.
- `draft-v1` is not a draft, or `ucc-panel-approved-v1` is not published.
- The 36-item catalog is a partial set, or responses point at it while it is not 36 items.
- The revised catalog has more than 60 items, a duplicate section position, or a position outside the approved catalog.
- An answered revised item has text or construct different from the approved catalog. That wording is left as stored.
- A submission has no version, or its version code has no row.

On execute it locks the three catalog rows, checks again, calls the existing idempotent activator, and sets the revised row current only when no version is current. It does not insert version rows, does not change `draft-v1` or `ucc-panel-approved-v1`, and does not rewrite answered text. If the post-check fails, the transaction rolls back.

The post-check requires all of the following:

- Version codes, submission version pins, and answer count are unchanged.
- Fingerprints of `draft-v1` and `ucc-panel-approved-v1` are unchanged.
- The revised version has 60 active non-draft items, ten in each of academic, socioeconomic, behavioral, competence, psychological_capital, and self_perceived_employability.
- Exactly one current version, and its code is `ucc-panel-revised-60-v2`.

`edupredict:activate-revised-questionnaire` remains the local command and still refuses `APP_ENV=production`.

## Predictor

`render.yaml` sets `PREDICTOR_DRIVER=heuristic`. New student attempts use `HeuristicPredictor` at `placeholder-heuristic-v1`. Rows already stored as `placeholder-heuristic-v0` are not rewritten. `student_scoring_enabled` stays false. The Render service has no ML API token and does not start the FastAPI research service. `HttpPredictor` still refuses a student `FeatureSet`.

## Likert keyboard

Each choice is a native radio inside its label, in a `radiogroup`. The dialog focus trap blocks Tab only at the ends of the dialog. The questionnaire key listener records that the last input was a key so keyboard selection does not auto-scroll. It does not call `preventDefault` for Space or arrows. Synthetic keyboard events in the earlier browser check focused a radio and did not activate it. That is a limit of the automation, not a defect in the form. No keyboard code changed in R1.

## Release checklist

Complete every step before students use the deployed site. Do not start until the branch update itself is intentional.

1. Keep `APP_KEY` exactly as already set on Render. Encrypted socioeconomic fields depend on it.
2. Leave `PREDICTOR_DRIVER=heuristic`. Do not set `student_scoring_enabled`.
3. Turn off Render auto-deploy, or be ready to run the command in the same window as the first boot. `render.yaml` still has `autoDeploy: true` until that dashboard change.
4. From a trusted machine, dump the Supabase session-pooler database with `pg_dump` before deploying. Supabase free has no point-in-time recovery. Record `users`, `students`, `questionnaire_responses`, `questionnaire_answers`, and `predictions` counts, plus the `model_version` values.
5. Deploy this branch. Boot runs `php artisan migrate --force` and then `edupredict:seed-demo-if-empty` only if `users` is empty.
6. Confirm `questionnaire_versions` has exactly one row each for `draft-v1`, `ucc-panel-approved-v1`, and `ucc-panel-revised-60-v2`.
7. Keep students off the questionnaire while the revised version may be current with zero items.
8. Dry-run `edupredict:prepare-approved-questionnaire` against that database. Read every blocker. Do not execute while any blocker is present.
9. Run it again with `--execute --confirm-preserves-history` only after the dry run is clean. The command prints `Post-check passed` when the checks above hold.
10. Compare the recorded counts. Responses, answers, and prediction versions must be unchanged. Historical 36-item submissions must still be pinned to `ucc-panel-approved-v1`.

## Rollback

- Application: use the previous Render deploy. Do not change `APP_KEY`.
- Database: restore the `pg_dump` taken in step 4. Do not run `migrate:fresh`, and do not delete `questionnaire_responses` or `questionnaire_answers` by hand.
- A code rollback without the dump leaves the new version table and any questions the command created. Restore the dump when the previous application does not know `ucc-panel-revised-60-v2`.

## Verification run for this preparation

- SQLite: `ApprovedQuestionnaireReleaseTest` and `Milestone43ATest`, 14 passed, 121 assertions.
- PostgreSQL `edupredict_testing`: `ApprovedQuestionnaireReleaseTest`, 7 passed, 37 assertions.
- Frontend: `npm run build` completed, 69 modules.
- No Supabase connection was opened.
