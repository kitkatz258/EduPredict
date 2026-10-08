# ML feature contract

This document is the single source of truth for features passed to `PredictorInterface`.
`App\Services\Prediction\FeatureBuilder` is the only class that maps student records into a `FeatureSet`.

The trained models are **not** implemented in this build. The bound predictor is the placeholder
`HeuristicPredictor` (`model_version = placeholder-heuristic-v0`). Do not mirror public dataset
column names until the adviser-approved training datasets are chosen.

## Binding

- Config: `config('edupredict.predictor.driver')` from `.env` `PREDICTOR_DRIVER` (default `heuristic`).
- Drivers reserved: `heuristic` (now), `http`, `onnx` (later). `http` and `onnx` throw until a later phase.
- Binding: `App\Providers\AppServiceProvider` binds `App\Contracts\PredictorInterface`.
- Swap the trained model by implementing `PredictorInterface` and changing the driver binding. Do not rewrite `FeatureBuilder` or the request flow.

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
A confirmed record with zero failures adds 4 employability points.

Other non-zero weights: scholarship +8 employability / −0.05 dropout (none is −2 / +0.04);
internships up to 2 × +6 employability and −0.03 dropout;
certifications up to 2 × +4 / −0.02;
technical skills up to 4 × +1.5 employability;
work experience up to 2 × +3 employability (projects are no longer collected and have no weight);
each positive construct `((score - 50) / 50) * 8` employability and `((100 - score) / 100) * 0.08` dropout;
procrastination is reversed, `((50 - score) / 50) * 8` employability and `(score / 100) * 0.12` dropout;
reliable internet, device, or study space is +2 employability (missing is −3);
income bands and employment shift the score by a few points only.

Household size and living arrangement are stored in the snapshot and are not scored by the placeholder.

## Feature catalog

Draft socioeconomic rows are ignored. Skills, certification, and work-experience entries count only while the Skills & Experience section is saved (`skills_experiences.is_draft` false) and the entry is not archived. Only current grade report versions count (confirmed and not superseded). Questionnaire features come from the latest submitted response.

| name | type | allowed values | source | used by |
|---|---|---|---|---|
| gwa | float or null | 1.00–5.00, rounded to 2 decimals | current confirmed `subject_grades` via `GwaCalculator` (NSTP, INC, and dropped rows excluded; provisional when any INC exists) | employability, dropout |
| failed_subjects | int | ≥ 0 | same confirmed rows; INC and dropped are not failures | employability, dropout |
| semesters_completed | int | ≥ 0 | distinct confirmed `grade_reports` school year + semester | confidence |
| limited_history | bool | true, false | `semesters_completed` < `prediction.limited_history_semesters` | confidence |
| scholarship_status | string or null | `none`, `partial`, `full`, `government`, `private` | `socioeconomic_profiles.scholarship_status` when not a draft | employability, dropout |
| has_scholarship | bool | true when status is present and not `none` | derived | employability, dropout |
| employment_status | string or null | `unemployed`, `part_time`, `working_student`, `full_time`, `self_employed` | `socioeconomic_profiles.employment_status` when not a draft | employability, dropout |
| income_bracket | string or null | income bracket codes in `config/edupredict.php` | `socioeconomic_profiles.household_income_bracket` (encrypted at rest; snapshot stores the code) | employability, dropout |
| household_size | int or null | ≥ 0 | `socioeconomic_profiles.household_size` when not a draft | snapshot only |
| living_arrangement | string or null | living-arrangement codes | `socioeconomic_profiles.living_arrangement` when not a draft | snapshot only |
| internet_access | string or null | `yes`, `unreliable`, `no` | `socioeconomic_profiles.has_internet` | employability, dropout |
| device_access | string or null | `yes`, `shared`, `no` | `socioeconomic_profiles.has_device` | employability, dropout |
| study_space | string or null | `yes`, `no` | `socioeconomic_profiles.has_study_space` | employability, dropout |
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

Construct scores use a 1–5 Likert mean, with `reverse_scored` items transformed as `6 - value`, then `(mean - 1) / 4 * 100`.

## Result shape

`predictEmployability` returns `score` 0–100, `risk_level` null, and `probability` null.
`predictDropout` returns `probability` 0–1, `score` as that probability on a 0–100 scale, and `risk_level` `low|moderate|high`.
Both return `confidence` `normal|low`, `factors[]` (`feature`, `label`, `direction`, `magnitude`), and `model_version`.

## Notes

- Feature snapshots stored on `predictions` must be de-identified (no name, student number, email, birthdate).
- Live system data is never used to retrain models automatically.
