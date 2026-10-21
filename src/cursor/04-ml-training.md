# EduPredict — M25: ML Algorithm Experimentation and Candidate Training

Read the existing repository before implementing:

- src/cursor/01-initial-project.md
- src/cursor/rules/edupredict.mdc
- Existing project architecture and prediction interfaces
- The approved EduPredict questionnaire, scoring, and supporting-studies document
- Any existing ML-related documentation

### Expected source files

Employability:
- Raw Dataset.xlsx — main Pianda dataset
- Survey questionnaire.docx — original questionnaire
- Dataset.rar — source archive; inspect for duplicates

Dropout:
- data.csv — UCI dataset; use semicolon delimiter

Approved UCC questionnaire:
- src/cursor/reference/approved-questionnaire/

The approved UCC questionnaire is a reference for future
inference and construct mapping, not additional training data.

### Approved questionnaire and scoring references

Read both PDFs under `src/cursor/reference/approved-questionnaire/`:
- EduPredict_Questionnaire.pdf
- EduPredict_Scoring_and_Research_Basis.pdf

The project team reports that the questionnaire is approved, although the PDFs still carry consultation/draft labels. Do not treat approval as proof that pilot reliability, construct equivalence, or model validation has been completed.

Use the exact six questionnaire sections and codes A1–A6, S1–S6, B1–B6, C1–C8, P1–P6, E1–E4. Compute section means only according to the documented scoring basis.

C and P are the proposed employability predictors; E is an independent descriptive comparison and never a predictor. A, S, and B are contextual measures, not UCI dropout model predictors.

Cross-check Pianda's original codebook and survey against the adapted UCC items. Produce a source-to-UCC mapping with documented discrepancies; do not assume measurement equivalence or invent missing mappings.

Document the feasible dropout feature subset, inference timing, and unresolved UCC-to-UCI grading transformations. Do not create unverified grade conversions or Low/Moderate/High risk thresholds.

The scoring bands and context flags in the consultation draft are descriptive/institution-defined, not empirically validated classification thresholds. Specify deterministic rounding and threshold behavior if implemented later.

During M25, train and evaluate research candidates using verified source-dataset variables only. Record outdated status wording, sampling-description inconsistencies, validation gaps, and other issues in the research report without silently rewriting the approved questionnaire or PDFs.

**Scope:** Dataset auditing, feature mapping, reproducible algorithm experimentation, scientific evaluation, and candidate model artifacts only.

Do not modify Laravel, the database, the UI, FeatureBuilder, HeuristicPredictor, or existing prediction history. Do not integrate FastAPI yet.

## 1. Dataset locations

Use the actual source datasets placed in:

- ml/data/raw/employability/ — Pianda et al. (2026)
- ml/data/raw/dropout/ — UCI Predict Students' Dropout and Academic Success

Inspect all files, original column definitions, codebooks, and scoring instructions before preprocessing.

Do not download substitute datasets or fabricate unavailable columns. Preserve original files unchanged.

Record provenance, version, checksum, citation, license, and source documentation. Protect raw data from accidental Git commits.

Before training, inspect the source files in ml/data/raw/employability/ and ml/data/raw/dropout/, and the approved UCC questionnaire and scoring documents in src/cursor/reference/approved-questionnaire/.

Pianda's Raw Dataset.xlsx contains Dataset and Codebook sheets. Cross-check the codebook with Survey questionnaire.docx before deriving construct means and target variables. Inspect Dataset.rar for duplicate copies and do not combine duplicated records.

The UCI file data.csv uses semicolon delimiters; read it accordingly and verify the expected row/column counts.

Treat the approved UCC questionnaire as the application's future inference instrument, not as additional training observations. Create a documented source-to-UCC construct mapping, explicitly identifying any unmatched or adapted items. Do not assume equivalence without evidence.

Do not alter original files, fabricate mappings, or train a model with unresolved target definitions. Exclude raw/restricted data from Git as appropriate.

## 2. Employability readiness — regression

Primary dataset: Pianda et al. (2026), Mendeley Data Version 4.

Target: The original dataset's verified continuous Employability construct.

Initial v1 predictors:

- CompetenceMean
- PsyCapMean

Derive these only after confirming their original source items, coding, missing-value handling, and scoring.

Do not assume the adapted UCC questionnaire is automatically measurement-equivalent to the source instrument.

Exclude target items and target-derived values from predictor features.

Compare:

1. Linear Regression
2. Ridge Regression
3. Random Forest Regressor
4. Gradient Boosting Regressor

Use appropriate repeated cross-validation and report:

- MAE
- RMSE
- R²
- mean and variation across validation folds
- overfitting indicators
- model interpretability
- uncertainty arising from the small dataset

A separate item-level experiment may be conducted if scientifically defensible, but the primary v1 experiment must use construct-level predictors.

Keep optional internship-based models separate from the two-feature v1 baseline.

Predict the original native score first. Only after confirming the scoring range may a display conversion be applied:

Readiness Index = ((PredictedScore - 1) / 4) * 100

This is **Employability Readiness**, not probability of employment.

## 3. Student dropout risk — classification

Primary dataset: UCI Predict Students' Dropout and Academic Success.

Preserve the original target labels:

- Dropout
- Enrolled
- Graduate

Develop two clearly distinguished experiments:

**A. Exploratory full-feature baseline**

Use defensible source features to establish a reference performance level. Clearly label features unavailable in EduPredict.

**B. Deployment-compatible reduced-feature baseline**

Use only predictors with a documented, technically feasible, and scientifically defensible EduPredict equivalent.

Do not invent UCC-to-Portuguese grade conversions.

Do not insert questionnaire A, S, B, C, P, or E values into UCI fields without a separately established scientific mapping.

Check when each source feature becomes available to avoid using future academic outcomes for earlier predictions.

Compare:

1. Logistic Regression
2. Random Forest Classifier
3. Gradient Boosting Classifier

Report:

- per-class precision, recall, and F1
- macro and weighted F1
- confusion matrix
- accuracy as secondary information
- ROC-AUC where appropriate
- dropout-class false negatives
- probability calibration where appropriate

Evaluate P(Dropout) separately from the three-class prediction.

Do not invent Low, Moderate, or High risk thresholds.

Do not select a full-feature model for deployment if it depends on unavailable UCC fields.

## 4. Approved questionnaire boundaries

The approved questionnaire has six sections:

- A1–A6: Academic Factors
- S1–S6: Socioeconomic Factors
- B1–B6: Behavioral/Engagement
- C1–C8: Competence
- P1–P6: Psychological Capital
- E1–E4: Self-Perceived Employability

Use the approved scoring definitions from project documentation.

Only C and P are intended as inputs to the employability model.

E is a descriptive comparison measure, not an input.

A, S, and B provide contextual interpretation and support-recommendation signals. They are not UCI dropout predictors.

Do not train a behavioral model or alter the existing questionnaire.

## 5. Reproducible ML workspace

Create or reuse:

- ml/data/raw/
- ml/data/processed/
- ml/src/data/
- ml/src/features/
- ml/src/training/
- ml/src/evaluation/
- ml/models/
- ml/reports/
- ml/tests/
- ml/requirements.txt
- ml/README.md

Use a Python virtual environment and pinned, compatible dependencies.

Prefer pandas, numpy, scipy, scikit-learn, matplotlib, joblib, and openpyxl where necessary.

Keep preprocessing inside scikit-learn Pipelines/ColumnTransformers.

Use fixed seeds, repeatable splits, training-only preprocessing fitting, and consistent candidate evaluation.

Keep final test data untouched during model selection and tuning. If sample size limits evaluation certainty, disclose it.

## 6. Required reports

Generate:

- ml/reports/employability_data_audit.md
- ml/reports/dropout_data_audit.md
- ml/reports/employability_feature_manifest.md
- ml/reports/dropout_feature_manifest.md
- ml/reports/employability_model_comparison.md
- ml/reports/dropout_model_comparison.md
- ml/reports/training_summary.md

For every dataset feature, indicate:

- original meaning and type
- predictor, target, identifier, or derived field
- KEEP / EXCLUDE / REVIEW
- leakage concerns
- missing-data treatment
- potential fairness implications
- availability at EduPredict inference time
- proven or proposed mapping to the UCC system

Every model comparison must use actual measured results, not illustrative numbers.

Generate appropriate charts, confusion matrices, and comparison tables.

## 7. Candidate artifacts and testing

Save recommended research candidates only after evaluation:

- ml/models/employability_candidate_v1.joblib
- ml/models/dropout_candidate_v1.joblib

Save the complete preprocessing pipeline, not just the estimator.

Include model metadata, input schema, dataset checksum/version, target definition, metrics, random seed, library versions, training date, and limitations.

Test:

- preprocessing and training execution
- missing/invalid inputs
- target leakage prevention
- predictor schema enforcement
- correct output shapes
- artifact serialization and reload
- reproducible results
- separation of questionnaire context and ML predictors

Do not label these candidates as production-validated.

## 8. Restrictions

Do not:

- Change Laravel code or database schemas
- Run Laravel migrations
- Remove the existing heuristic predictor
- Build FastAPI yet
- Create another Docker container
- Deploy to Render
- Invent source values or feature mappings
- Create synthetic training rows to inflate performance
- Invent dropout thresholds
- Modify approved questionnaire items or scoring
- Commit sensitive data or unauthorized dataset copies
- Select a model before comparing results

If the original codebook, feature mapping, or target definition is insufficient, document the blocker and stop the affected experiment rather than guessing.

## 9. Completion requirements

Run relevant Python tests, training scripts, artifact reload tests, and reproducibility checks.

Verify Git identity and review the diff before committing.

At completion report:

1. Dataset audit results and provenance
2. Target definitions and feature decisions
3. Actual metrics for every candidate
4. Recommended employability research candidate
5. Recommended dropout research candidate
6. Differences between exploratory and deployable results
7. Data compatibility or methodological blockers
8. Created files and artifact paths
9. Commands and tests executed
10. Git status and commit hash

Commit as:

M25: Establish ML training pipelines and compare candidate models

**Stop after this milestone. Do not proceed to FastAPI or Laravel integration.**