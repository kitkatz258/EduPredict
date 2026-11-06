# Dropout experiments (M42)

Three-class UCI target: Dropout, Enrolled, Graduate. No Low/Moderate/High labels. Grade columns excluded. A/S/B questionnaire means excluded. P(Dropout) is source-trained, not UCC-calibrated.

Protocol, fixed before scores were read:

- Seed 42. Stratified 20% holdout. RepeatedStratifiedKFold 5 × 5 on the training partition.
- DummyClassifier (most frequent), multinomial LogisticRegression (lbfgs, max_iter=2000, class_weight=balanced), RandomForestClassifier (balanced), GradientBoostingClassifier with balanced sample weights.
- Selection: higher mean CV macro F1. Within 0.01, earlier algorithm wins: Logistic Regression, Random Forest Classifier, Gradient Boosting Classifier.
- Holdout did not choose the algorithm.
- Full 36-feature exploratory model: **not re-run** in M42. See `dropout_model_comparison.md` from M25.

M25 previously measured full-feature CV macro F1 for Gradient Boosting Classifier: 0.7211 ± 0.0153 (36 predictors, including grades and second-semester fields). That figure is **not** an EduPredict result and was not used to select an M42 candidate.

## Scholarship holder only

Contract: `uci-scholarship-only-m42`. Timing: `after_published_profile`. Deployment status: **pending_mapping**.

Source-native 0/1 scholarship flag. EduPredict category collapse and missing-profile coercion are not verified. Weak three-class signal.

Columns: Scholarship holder.

Split: train 3539, holdout 885. Class counts in the full file: {'Graduate': 2209, 'Dropout': 1421, 'Enrolled': 794}.

| Algorithm | CV macro F1 | CV weighted F1 | CV accuracy | CV ROC-AUC OvR | Train macro F1 | F1 gap | Holdout macro F1 | Holdout accuracy | Holdout dropout FN | Holdout dropout ROC-AUC | Holdout Brier P(Dropout) |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.3492 ± 0.0088 | 0.4269 ± 0.0119 | 0.4795 ± 0.0111 | 0.5983 ± 0.0104 | 0.3493 | 0.0000 | 0.3481 | 0.4802 | 19 | 0.6213 | 0.2032 |
| Random Forest Classifier | 0.3492 ± 0.0088 | 0.4269 ± 0.0119 | 0.4795 ± 0.0111 | 0.5983 ± 0.0104 | 0.3493 | 0.0000 | 0.3481 | 0.4802 | 19 | 0.6213 | 0.2032 |
| Gradient Boosting Classifier | 0.3492 ± 0.0088 | 0.4269 ± 0.0119 | 0.4795 ± 0.0111 | 0.5983 ± 0.0104 | 0.3493 | 0.0000 | 0.3481 | 0.4802 | 19 | 0.6213 | 0.2032 |
| Majority baseline | 0.2220 ± 0.0002 | 0.3325 ± 0.0007 | 0.4993 ± 0.0006 | 0.5000 ± 0.0000 | 0.2220 | 0.0000 | 0.2221 | 0.4994 | 284 | 0.5000 | 0.3209 |

Selected: **Logistic Regression**. Contenders: Logistic Regression, Random Forest Classifier, Gradient Boosting Classifier.

Holdout per-class metrics for the selected algorithm:

| Class | Precision | Recall | F1 | Support |
| --- | --- | --- | --- | --- |
| Dropout | 0.3897 | 0.9331 | 0.5498 | 284 |
| Enrolled | 0.0000 | 0.0000 | 0.0000 | 159 |
| Graduate | 0.7805 | 0.3620 | 0.4946 | 442 |

| Observed \ Predicted | Dropout | Enrolled | Graduate |
| --- | --- | --- | --- |
| Dropout | 265 | 0 | 19 |
| Enrolled | 133 | 0 | 26 |
| Graduate | 282 | 0 | 160 |

Dropout false negatives: 19 (rate 0.0669).
Holdout accuracy 0.4802, macro F1 0.3481, weighted F1 0.4234.
P(Dropout) ROC-AUC 0.6213; OvR macro ROC-AUC 0.5977; Brier 0.2032.

## First-semester enrolled and approved units (grades excluded)

Contract: `uci-first-sem-units-m42`. Timing: `after_first_semester`. Deployment status: **pending_unit_definition**.

Source-native unit counts only. UCC cumulative subject units have no verified first-semester index or approval definition.

Columns: Curricular units 1st sem (enrolled), Curricular units 1st sem (approved).

Split: train 3539, holdout 885. Class counts in the full file: {'Graduate': 2209, 'Dropout': 1421, 'Enrolled': 794}.

| Algorithm | CV macro F1 | CV weighted F1 | CV accuracy | CV ROC-AUC OvR | Train macro F1 | F1 gap | Holdout macro F1 | Holdout accuracy | Holdout dropout FN | Holdout dropout ROC-AUC | Holdout Brier P(Dropout) |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.6061 ± 0.0126 | 0.6741 ± 0.0131 | 0.6580 ± 0.0164 | 0.7952 ± 0.0095 | 0.6062 | 0.0000 | 0.6347 | 0.6802 | 126 | 0.8558 | 0.1333 |
| Random Forest Classifier | 0.6027 ± 0.0133 | 0.6709 ± 0.0113 | 0.6627 ± 0.0129 | 0.7958 ± 0.0119 | 0.6295 | 0.0268 | 0.6390 | 0.6825 | 117 | 0.8472 | 0.1350 |
| Gradient Boosting Classifier | 0.6110 ± 0.0121 | 0.6779 ± 0.0106 | 0.6695 ± 0.0119 | 0.7993 ± 0.0106 | 0.6273 | 0.0163 | 0.6382 | 0.6847 | 110 | 0.8573 | 0.1342 |
| Majority baseline | 0.2220 ± 0.0002 | 0.3325 ± 0.0007 | 0.4993 ± 0.0006 | 0.5000 ± 0.0000 | 0.2220 | 0.0000 | 0.2221 | 0.4994 | 284 | 0.5000 | 0.3209 |

Selected: **Logistic Regression**. Contenders: Logistic Regression, Random Forest Classifier, Gradient Boosting Classifier.

Holdout per-class metrics for the selected algorithm:

| Class | Precision | Recall | F1 | Support |
| --- | --- | --- | --- | --- |
| Dropout | 0.8404 | 0.5563 | 0.6695 | 284 |
| Enrolled | 0.3409 | 0.5660 | 0.4255 | 159 |
| Graduate | 0.8176 | 0.8009 | 0.8091 | 442 |

| Observed \ Predicted | Dropout | Enrolled | Graduate |
| --- | --- | --- | --- |
| Dropout | 158 | 89 | 37 |
| Enrolled | 27 | 90 | 42 |
| Graduate | 3 | 85 | 354 |

Dropout false negatives: 126 (rate 0.4437).
Holdout accuracy 0.6802, macro F1 0.6347, weighted F1 0.6954.
P(Dropout) ROC-AUC 0.8558; OvR macro ROC-AUC 0.8108; Brier 0.1333.

## First- and second-semester enrolled and approved units (grades excluded)

Contract: `uci-two-sem-units-m42`. Timing: `after_second_semester`. Deployment status: **pending_unit_definition**.

Second-semester columns are unavailable at enrollment or after only the first semester. Using them earlier is temporal leakage.

Columns: Curricular units 1st sem (enrolled), Curricular units 1st sem (approved), Curricular units 2nd sem (enrolled), Curricular units 2nd sem (approved).

Split: train 3539, holdout 885. Class counts in the full file: {'Graduate': 2209, 'Dropout': 1421, 'Enrolled': 794}.

| Algorithm | CV macro F1 | CV weighted F1 | CV accuracy | CV ROC-AUC OvR | Train macro F1 | F1 gap | Holdout macro F1 | Holdout accuracy | Holdout dropout FN | Holdout dropout ROC-AUC | Holdout Brier P(Dropout) |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.6565 ± 0.0145 | 0.7217 ± 0.0118 | 0.7097 ± 0.0125 | 0.8465 ± 0.0079 | 0.6574 | 0.0009 | 0.6701 | 0.7209 | 100 | 0.8860 | 0.1163 |
| Random Forest Classifier | 0.6584 ± 0.0175 | 0.7176 ± 0.0142 | 0.7077 ± 0.0157 | 0.8431 ± 0.0109 | 0.7277 | 0.0693 | 0.6678 | 0.7141 | 99 | 0.8794 | 0.1184 |
| Gradient Boosting Classifier | 0.6677 ± 0.0176 | 0.7238 ± 0.0149 | 0.7131 ± 0.0161 | 0.8564 ± 0.0083 | 0.7011 | 0.0334 | 0.6710 | 0.7153 | 98 | 0.8825 | 0.1168 |
| Majority baseline | 0.2220 ± 0.0002 | 0.3325 ± 0.0007 | 0.4993 ± 0.0006 | 0.5000 ± 0.0000 | 0.2220 | 0.0000 | 0.2221 | 0.4994 | 284 | 0.5000 | 0.3209 |

Selected: **Random Forest Classifier**. Contenders: Random Forest Classifier, Gradient Boosting Classifier.

Holdout per-class metrics for the selected algorithm:

| Class | Precision | Recall | F1 | Support |
| --- | --- | --- | --- | --- |
| Dropout | 0.7676 | 0.6514 | 0.7048 | 284 |
| Enrolled | 0.4036 | 0.5660 | 0.4712 | 159 |
| Graduate | 0.8480 | 0.8077 | 0.8273 | 442 |

| Observed \ Predicted | Dropout | Enrolled | Graduate |
| --- | --- | --- | --- |
| Dropout | 185 | 69 | 30 |
| Enrolled | 35 | 90 | 34 |
| Graduate | 21 | 64 | 357 |

Dropout false negatives: 99 (rate 0.3486).
Holdout accuracy 0.7141, macro F1 0.6678, weighted F1 0.7240.
P(Dropout) ROC-AUC 0.8794; OvR macro ROC-AUC 0.8539; Brier 0.1184.

Reproducibility rerun of the first-semester units comparison matched: True

UCC unit-count inference: **blocked**. Evaluations, credited units, and without-evaluations fields remain source-only and were not treated as deployment-feasible because EduPredict does not store matching counts.
