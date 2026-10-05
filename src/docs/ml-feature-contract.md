# ML feature contract

This document is the single source of truth for features passed to `PredictorInterface`.
`App\Services\Prediction\FeatureBuilder` is the only class that maps student records into a `FeatureSet`.

The trained models are **not** implemented in this build. The bound predictor is the placeholder
`HeuristicPredictor` (`model_version = placeholder-heuristic-v0`). Do not mirror public dataset
column names until the adviser-approved training datasets are chosen.

## Binding

- Config: `config('edupredict.predictor.driver')` from `.env` `PREDICTOR_DRIVER` (default `heuristic`).
- Drivers reserved: `heuristic` (now), `http`, `onnx` (later).
- Swap the trained model by implementing `PredictorInterface` and changing the driver binding.

## Feature catalog

`FeatureBuilder::build()` currently returns questionnaire construct scores only.
The rest of the `FeatureSet`, plus `HeuristicPredictor`, is added in M5.5. Do not train a model here.

| name | type | allowed values | source | used by |
|---|---|---|---|---|
| construct_scores.study_habits | float | 0–100 | `questionnaire_responses.construct_scores` (latest submission) | employability, dropout (M5.5) |
| construct_scores.time_management | float | 0–100 | same | employability, dropout (M5.5) |
| construct_scores.motivation | float | 0–100 | same | employability, dropout (M5.5) |
| construct_scores.procrastination | float | 0–100; higher means more procrastination | same | employability, dropout (M5.5) |
| construct_scores.engagement | float | 0–100 | same | employability, dropout (M5.5) |

Scores use a 1–5 Likert mean, with `reverse_scored` items transformed as `6 - value`, then `(mean - 1) / 4 * 100`.

## Notes

- Feature snapshots stored on `predictions` must be de-identified (no name, student number, email, birthdate).
- Live system data is never used to retrain models automatically.
