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

## Feature catalog (skeleton — filled in M5.5)

| name | type | allowed values | source | used by |
|---|---|---|---|---|
| *(rows added when FeatureBuilder is implemented)* | | | | |

## Notes

- Feature snapshots stored on `predictions` must be de-identified (no name, student number, email, birthdate).
- Live system data is never used to retrain models automatically.
