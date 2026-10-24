# M33 research inference

Student predictions stay on `placeholder-heuristic-v0`. This service scores saved research artifacts with their original columns. It is not a validated UCC predictor, and it is not deployed.

## Architecture

| Piece | Role |
| --- | --- |
| Laravel on Render, later | Student application. MariaDB remains the local database. |
| FastAPI service `ml` | Loads joblib artifacts and answers research calls. |
| PostgreSQL on Supabase, later | Not connected in M33. |
| Docker Compose | `app`, `db`, `phpmyadmin`, and `ml`. |

`ml` joins the Compose network and does not publish a host port. Laravel reaches it at `http://ml:8000`. The image binds `ML_HOST` and `PORT` so a later Render service can supply the platform port. Do not deploy it in this milestone.

The service reads `employability_candidate_v1.joblib` and the four M27 binary logistic files. It does not load `dropout_candidate_v1.joblib`, and startup does not train.

## Endpoints

| Call | Auth | What it does |
| --- | --- | --- |
| `GET /health/live` | No | Process is up. No model payload. |
| `GET /health/ready` | No | Models loaded and a non-development token is configured. `503` otherwise. |
| `GET /v1/research/models` | Bearer token | Versions, columns, dataset labels, and limitations. No coefficients. |
| `POST /v1/research/employability` | Bearer token | Pianda `CompetenceMean` and `PsyCapMean` only (`schema_id=pianda-v1`). |
| `POST /v1/research/dropout` | Bearer token | One named UCI feature set (`schema_id=uci-697`). |

Dropout responses are binary: Dropout or Not Dropout. The class is the estimator's 0.5 cut. There is no Low, Moderate, or High band. Every response sets `research_only` true, `production_validated` false, `suitable_for_student_advice` false, and `student_workflow` false.

`scholarship_only` is served only as the weak compatibility baseline. It is marked `weak_compatibility_baseline` true.

## What is blocked

- Student `FeatureSet` values, including GWA, scholarship status, and questionnaire snapshots.
- UCC item codes A, S, B, C, P, and E, and Pianda EVS or PsyCap items.
- A schema id that names the UCC instrument.
- UCI codes that were not in the audited source file.
- Admission or previous-qualification grades outside the audited UCI range, which rejects a Philippine 1.00–5.00 grade placed in those columns.
- Missing or extra columns for the requested feature set.
- Requests without the bearer token, oversized bodies, and inference that exceeds `ML_INFERENCE_TIMEOUT_SECONDS`.

A curricular grade on the 0–20 scale is not inspected for Philippine quarter-steps, because those numbers can also be real UCI grades. The Laravel client has no conversion function.

`HttpPredictor` refuses `predictEmployability` and `predictDropout`. `PredictionRequester` blocks a non-heuristic driver before it builds features. Neither path calls the heuristic model and relabels the result.

## Environment variable names

No secret values belong in git. Local Compose uses the development token `local-dev-ml-token`. The service rejects that token when `ML_ENV=production`.

| Name | Used by |
| --- | --- |
| `PREDICTOR_DRIVER` | Laravel. Default `heuristic`. |
| `ML_API_BASE_URL` | Laravel client. Compose sets `http://ml:8000`. |
| `ML_API_TOKEN` | Shared bearer token. |
| `ML_API_TIMEOUT` | Laravel request timeout, seconds. |
| `ML_API_CONNECT_TIMEOUT` | Laravel connect timeout, seconds. |
| `ML_HOST` | FastAPI bind host. Default `0.0.0.0` inside the container. |
| `PORT` or `ML_PORT` | FastAPI bind port. Render will set `PORT`. |
| `ML_ENV` | `local` or `production`. |
| `ML_MODELS_DIR` | Artifact directory. Default is the image `models` folder. |
| `ML_INFERENCE_TIMEOUT_SECONDS` | In-process scoring limit. |
| `ML_MAX_BODY_BYTES` | Request body limit. |
| `ML_LOG_LEVEL` | `INFO` in production. Debug is clamped. |

Logs record method, path, status, and duration. They do not record the token, request body, questionnaire answers, or model coefficients.

## Local checks

From the repo root, build and start the research service, then call it from the Laravel container:

```bash
docker compose up -d --build ml
docker compose exec app python3 -c "import urllib.request; print(urllib.request.urlopen('http://ml:8000/health/live', timeout=5).read())"
```

Set `ML_API_LIVE=1`, `ML_API_BASE_URL`, and `ML_API_TOKEN` only for the optional Laravel comparison test. The default PHPUnit run does not call the live service.
