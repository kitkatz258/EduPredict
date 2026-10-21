# EduPredict ML research candidates (M25)

This folder trains and evaluates research candidates. It does not replace the Laravel placeholder predictor, and it does not serve a model.

The saved files are not production-validated. Approval of the UCC questionnaire is not psychometric validation or evidence that the candidates work for UCC students.

## Local data

Place the unchanged source files here before training:

- `data/raw/employability/Raw Dataset.xlsx`
- `data/raw/employability/Survey questionnaire.docx`
- `data/raw/employability/Dataset.rar`
- `data/raw/dropout/data.csv`

Those files are gitignored. The dropout CSV is semicolon-separated and is read with the leading byte-order mark removed in memory. Training uses the loose workbook only. The RAR archive is checked for duplicates and is not merged into the table.

The approved UCC PDFs expected at `src/cursor/reference/approved-questionnaire/` are the inference reference. If they are absent, item-level mapping and UCC section scoring stop. The source-data experiments still use the Pianda and UCI variables that can be verified from their own files.

## Setup

From `ml/`:

```bash
python3 -m venv --without-pip .venv
# bootstrap pip into .venv, then:
.venv/bin/python -m pip install -r requirements.txt
```

Run commands with one numeric thread so repeated cross-validation stays stable:

```bash
export OMP_NUM_THREADS=1 OPENBLAS_NUM_THREADS=1 MKL_NUM_THREADS=1 NUMEXPR_NUM_THREADS=1
export PYTHONUNBUFFERED=1
export PYTHONPATH=.
```

## Commands

```bash
.venv/bin/python -m src.run_m25
.venv/bin/python -m pytest
```

`src.run_m25` writes:

- `reports/employability_data_audit.md`
- `reports/dropout_data_audit.md`
- `reports/employability_feature_manifest.md`
- `reports/dropout_feature_manifest.md`
- `reports/employability_model_comparison.md`
- `reports/dropout_model_comparison.md`
- `reports/training_summary.md`
- `reports/metrics.json`
- `reports/figures/`
- `models/employability_candidate_v1.joblib`
- `models/dropout_candidate_v1.joblib`

Load an artifact with `PYTHONPATH` set to `ml/`. Each joblib object is a dictionary with `pipeline` and `metadata`. The pipeline was fit on the training split only.

## Candidate inputs

Employability v1 accepts `CompetenceMean` and `PsyCapMean`, the verified unweighted Pianda item means on the 1–5 scale. The target is the EVS mean. EVS items are not inputs. A later display index is `((score - 1) / 4) * 100` inside that range. It is not a probability of employment.

Dropout v1 accepts `Scholarship holder` as 0 or 1. It returns the original labels Dropout, Enrolled, and Graduate, plus `P(Dropout)`. It does not apply Low, Moderate, or High cut points. The full UCI feature model is an exploratory reference and is not the saved candidate, because most of its fields have no verified UCC equivalent and the grade scales are not converted.
