# ML feature contract

This document is the single source of truth for features passed to `PredictorInterface`.
`App\Services\Prediction\FeatureBuilder` is the only class that maps student records into a `FeatureSet`.

Student predictions use the placeholder `HeuristicPredictor` (`model_version = placeholder-heuristic-v1`). Rows already stored as `placeholder-heuristic-v0` are not rewritten.
The FastAPI service scores saved Pianda and UCI research artifacts. It does not accept this `FeatureSet`.
UCC section means and Philippine grades are not mapped onto those artifacts.

## Binding

- Config: `config('edupredict.predictor.driver')` from `.env` `PREDICTOR_DRIVER` (default `heuristic`).
- `heuristic` is the student workflow. `http` resolves `HttpPredictor`, which refuses a `FeatureSet`.
- `onnx` still throws. There is no automatic fallback from a research error to the placeholder.
- Research calls use `HttpResearchInferenceClient` with explicit `pianda-v1` or `uci-697` payloads.
- Binding: `App\Providers\AppServiceProvider` binds `App\Contracts\PredictorInterface`.
- Do not rewrite `FeatureBuilder` to feed questionnaire means or grades into the research models.

## Placeholder rules

`HeuristicPredictor` is deterministic. Employability starts at 50 and dropout probability starts at 0.20.
A `+` factor helps the student. A `-` factor hurts. Magnitudes are absolute contributions before the final clamp.
Employability is clamped to 0–100. Dropout probability is clamped to 0–1, then classed:

- low: below 0.30
- moderate: 0.30 up to but not including 0.60
- high: 0.60 and above

`limited_history` does not change the score. It sets `confidence` to `low` when confirmed terms are fewer than `prediction.limited_history_semesters` (default 2). Otherwise confidence is `normal`.

Philippine GWA: lower is better. Employability GWA contribution is `((3.00 - gwa) / 2) * 20`, clamped to ±20.
Dropout GWA contribution is `-0.05` at 1.75 or better, otherwise `((gwa - 1.75) / 3.25) * 0.35`.
Each failed subject costs 8 employability points and adds 0.12 dropout probability, both capped at 3 subjects.
A confirmed record with a GWA, zero failures, zero incomplete subjects, and zero dropped subjects adds 4 employability points. Incomplete or dropped subjects withhold that bonus.
Each incomplete subject costs 2 employability points and adds 0.02 dropout probability, capped at 3. Each dropped subject, including unofficially dropped, uses the same magnitudes. Neither is treated as 5.00 or as a failed subject.

Other non-zero weights:
internships up to 2 × +6 employability and −0.03 dropout;
certifications up to 2 × +4 / −0.02;
technical skills up to 4 × +1.5 employability;
work experience up to 2 × +3 employability (projects are no longer collected and have no weight);
each positive construct `((score - 50) / 50) * 8` employability and `((100 - score) / 100) * 0.08` dropout;
procrastination is reversed, `((50 - score) / 50) * 8` employability and `(score / 100) * 0.12` dropout;
M37.1 does not score scholarship, income, employment, internet, device, study space, household size, or living arrangement. A published `socioeconomic_profiles` row stays encrypted and is not copied into a new snapshot. Those snapshot keys are null and listed as unavailable. They are not read as poverty, as no scholarship, or as zero. `placeholder-heuristic-v0` attempts keep the household factors they already stored.

## Feature catalog

Draft socioeconomic rows are ignored. Skills, certification, and work-experience entries count only while the Skills & Experience section is saved (`skills_experiences.is_draft` false) and the entry is not archived. Only current grade report versions count (confirmed and not superseded). Questionnaire features come from the latest submitted response.

| name | type | allowed values | source | used by |
|---|---|---|---|---|
| gwa | float or null | 1.00–5.00, rounded to 2 decimals | current confirmed `subject_grades` via `GwaCalculator` (NSTP, INC, and dropped rows excluded; provisional when any INC exists) | employability, dropout |
| failed_subjects | int | ≥ 0 | same confirmed rows; INC, dropped, and unofficially dropped are not failures | employability, dropout |
| incomplete_subjects | int | ≥ 0 | current confirmed rows whose grade or remark is incomplete | employability, dropout |
| dropped_subjects | int | ≥ 0 | current confirmed rows whose grade or remark is dropped or unofficially dropped | employability, dropout |
| semesters_completed | int | ≥ 0 | distinct confirmed `grade_reports` school year + semester | confidence |
| limited_history | bool | true, false | `semesters_completed` < `prediction.limited_history_semesters` | confidence |
| scholarship_status | null on a new prediction | stored profile is not read | encrypted `socioeconomic_profiles` row is preserved and not used | none on v1 |
| has_scholarship | null on a new snapshot | not derived for a new prediction | same | none on v1 |
| employment_status | null on a new prediction | stored profile is not read | same | none on v1 |
| income_bracket | null on a new prediction | stored profile is not read | same | none on v1 |
| household_size | null on a new prediction | stored profile is not read | same | none on v1 |
| living_arrangement | null on a new prediction | stored profile is not read | same | none on v1 |
| internet_access | null on a new prediction | stored profile is not read | same | none on v1 |
| device_access | null on a new prediction | stored profile is not read | same | none on v1 |
| study_space | null on a new prediction | stored profile is not read | same | none on v1 |
| technical_skill_count | int | ≥ 0 | count of active `student_skills` | employability |
| certification_count | int | ≥ 0 | count of active `student_certifications` | employability, dropout |
| internship_count | int | ≥ 0 | count of active `student_work_experiences` with type `ojt_internship` | employability, dropout |
| project_count | int | always 0 | projects are no longer collected; kept so snapshot shape stays stable | none |
| work_experience_count | int | ≥ 0 | count of active `student_work_experiences` of every other type | employability |
| construct_scores.study_habits | float or null | 0–100 | latest submitted `questionnaire_responses.construct_scores` | employability, dropout |
| construct_scores.time_management | float or null | 0–100 | same | employability, dropout |
| construct_scores.motivation | float or null | 0–100 | same | employability, dropout |
| construct_scores.procrastination | float or null | 0–100; higher means more procrastination | same | employability, dropout |
| construct_scores.engagement | float or null | 0–100 | same | employability, dropout |
| major_gwa | float or null | 1.00–5.00, rounded to 2 decimals | confirmed rows with `is_major_subject` true, via `GwaCalculator` | program-shift indicator only |
| other_gwa | float or null | 1.00–5.00, rounded to 2 decimals | confirmed rows with `is_major_subject` false | program-shift indicator only |
| major_failed_subjects | int | ≥ 0 | failed rows among major subjects | program-shift indicator only |
| other_failed_subjects | int | ≥ 0 | failed rows among other subjects | program-shift indicator only |
| major_units | float | ≥ 0 | GPA units of major subjects | program-shift indicator only |
| other_units | float | ≥ 0 | GPA units of other subjects | program-shift indicator only |

Legacy `draft-v1` construct scores use a 1–5 Likert mean, with `reverse_scored` items transformed as `6 - value`, then `(mean - 1) / 4 * 100`.

`ucc-panel-approved-v1` section means (Academic, Socioeconomic, Behavioral, Competence, Psychological Capital, and Self-Perceived Employability) are stored on the questionnaire response and copied into future prediction snapshots. They are not mapped onto `construct_scores.study_habits` or the other five feature fields, and they are not inputs to the placeholder predictor, Pianda, or UCI. A missing legacy construct stays null. The predictor treats that null as no contribution from that field.

## Result shape

`predictEmployability` returns `score` 0–100, `risk_level` null, and `probability` null.
`predictDropout` returns `probability` 0–1, `score` as that probability on a 0–100 scale, and `risk_level` `low|moderate|high`.
Both return `confidence` `normal|low`, `factors[]` (`feature`, `label`, `direction`, `magnitude`), and `model_version`.

## Notes

- Feature snapshots stored on `predictions` must be de-identified (no name, student number, email, birthdate).
- Live system data is never used to retrain models automatically.
