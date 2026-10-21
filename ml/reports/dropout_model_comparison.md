# Dropout model comparison

Target labels: Dropout, Enrolled, Graduate. Accuracy is secondary. No risk band is derived from P(Dropout).

Protocol, fixed before scores were read:

- Seed 42.
- Stratified holdout at 20%. Training rows 3539; holdout rows 885.
- RepeatedStratifiedKFold, 5 × 5, on the training partition.
- Nominal source codes are one-hot encoded inside the pipeline. Numeric and binary fields are standardized only for logistic regression.
- Class imbalance: logistic regression and random forest use `class_weight="balanced"`. Gradient boosting uses balanced sample weights because that estimator has no `class_weight`.
- Other hyperparameters remain library defaults, with `random_state` fixed and logistic `max_iter=2000`.
- Selection metric for each feature set: higher mean CV macro F1. Within 0.01 macro F1, the earlier algorithm wins: Logistic Regression, Random Forest Classifier, Gradient Boosting Classifier.
- The majority-class baseline is not selectable.
- Holdout metrics did not choose the algorithm.
- The saved candidate is the reduced-feature winner. The full-feature winner is an exploratory reference and is not saved for deployment.

## A. Exploratory full-feature reference

| Algorithm | CV macro F1 | CV weighted F1 | CV accuracy | CV ROC-AUC OvR | Train macro F1 | F1 gap | Holdout macro F1 | Holdout dropout FN | Holdout dropout ROC-AUC | Holdout Brier P(Dropout) |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.7104 ± 0.0148 | 0.7603 ± 0.0117 | 0.7526 ± 0.0127 | 0.8862 ± 0.0081 | 0.7681 | 0.0576 | 0.6876 | 91 | 0.9112 | 0.1022 |
| Random Forest Classifier | 0.6803 ± 0.0183 | 0.7508 ± 0.0136 | 0.7721 ± 0.0121 | 0.8843 ± 0.0091 | 1.0000 | 0.3197 | 0.6725 | 72 | 0.9111 | 0.1050 |
| Gradient Boosting Classifier | 0.7211 ± 0.0153 | 0.7676 ± 0.0121 | 0.7589 ± 0.0133 | 0.8901 ± 0.0083 | 0.8086 | 0.0875 | 0.6859 | 92 | 0.9062 | 0.1057 |
| Majority baseline | 0.2220 ± 0.0002 | 0.3325 ± 0.0007 | 0.4993 ± 0.0006 | 0.5000 ± 0.0000 | 0.2220 | 0.0000 | 0.2221 | 284 | 0.5000 | 0.3209 |

Reference algorithm by the CV rule: **Gradient Boosting Classifier**. Contenders: Gradient Boosting Classifier.

### Confirmatory holdout, full reference

| Class | Precision | Recall | F1 | Support |
| --- | --- | --- | --- | --- |
| Dropout | 0.8136 | 0.6761 | 0.7385 | 284 |
| Enrolled | 0.4056 | 0.6352 | 0.4951 | 159 |
| Graduate | 0.8675 | 0.7851 | 0.8242 | 442 |

| Observed \ Predicted | Dropout | Enrolled | Graduate |
| --- | --- | --- | --- |
| Dropout | 192 | 68 | 24 |
| Enrolled | 29 | 101 | 29 |
| Graduate | 15 | 80 | 347 |

Dropout false negatives on the holdout: 92 of the observed Dropout rows (rate 0.3239).

P(Dropout) versus the rest, holdout ROC-AUC 0.9062. Multiclass OvR macro ROC-AUC 0.8813. Brier score for P(Dropout) 0.1057.

### Out-of-fold training partition, same reference algorithm

Single stratified 5-fold, separate from the repeated CV used for selection.

| Class | Precision | Recall | F1 | Support |
| --- | --- | --- | --- | --- |
| Dropout | 0.8476 | 0.7291 | 0.7839 | 1137 |
| Enrolled | 0.4731 | 0.6504 | 0.5477 | 635 |
| Graduate | 0.8614 | 0.8229 | 0.8417 | 1767 |

| Observed \ Predicted | Dropout | Enrolled | Graduate |
| --- | --- | --- | --- |
| Dropout | 829 | 208 | 100 |
| Enrolled | 88 | 413 | 134 |
| Graduate | 61 | 252 | 1454 |

## B. Reduced candidate

The only verified EduPredict-compatible predictor in this file is `Scholarship holder`.

| Algorithm | CV macro F1 | CV weighted F1 | CV accuracy | CV ROC-AUC OvR | Train macro F1 | F1 gap | Holdout macro F1 | Holdout dropout FN | Holdout dropout ROC-AUC | Holdout Brier P(Dropout) |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.3492 ± 0.0088 | 0.4269 ± 0.0119 | 0.4795 ± 0.0111 | 0.5983 ± 0.0104 | 0.3493 | 0.0000 | 0.3481 | 19 | 0.6213 | 0.2032 |
| Random Forest Classifier | 0.3492 ± 0.0088 | 0.4269 ± 0.0119 | 0.4795 ± 0.0111 | 0.5983 ± 0.0104 | 0.3493 | 0.0000 | 0.3481 | 19 | 0.6213 | 0.2032 |
| Gradient Boosting Classifier | 0.3492 ± 0.0088 | 0.4269 ± 0.0119 | 0.4795 ± 0.0111 | 0.5983 ± 0.0104 | 0.3493 | 0.0000 | 0.3481 | 19 | 0.6213 | 0.2032 |
| Majority baseline | 0.2220 ± 0.0002 | 0.3325 ± 0.0007 | 0.4993 ± 0.0006 | 0.5000 ± 0.0000 | 0.2220 | 0.0000 | 0.2221 | 284 | 0.5000 | 0.3209 |

Saved research candidate: **Logistic Regression**. Contenders: Logistic Regression, Random Forest Classifier, Gradient Boosting Classifier.

### Confirmatory holdout, reduced candidate

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

Dropout false negatives on the holdout: 19 (rate 0.0669).

P(Dropout) ROC-AUC 0.6213. OvR macro ROC-AUC 0.5977. Brier score 0.2032.

### Out-of-fold training partition, reduced candidate

| Class | Precision | Recall | F1 | Support |
| --- | --- | --- | --- | --- |
| Dropout | 0.3864 | 0.8989 | 0.5405 | 1137 |
| Enrolled | 0.0000 | 0.0000 | 0.0000 | 635 |
| Graduate | 0.7550 | 0.3820 | 0.5073 | 1767 |

| Observed \ Predicted | Dropout | Enrolled | Graduate |
| --- | --- | --- | --- |
| Dropout | 1022 | 0 | 115 |
| Enrolled | 531 | 0 | 104 |
| Graduate | 1092 | 0 | 675 |

## Timing sensitivity

Same repeated CV protocol. The full row is the exploratory winner. The other rows score that same algorithm after future curricular columns are removed. They were not used to choose the saved candidate.

| Timing | Predictors | Algorithm | CV macro F1 | CV weighted F1 | CV accuracy |
| --- | --- | --- | --- | --- | --- |
| full | 36 | Gradient Boosting Classifier | 0.7211 ± 0.0153 | 0.7676 | 0.7589 |
| after_first_semester | 30 | Gradient Boosting Classifier | 0.6888 ± 0.0164 | 0.7377 | 0.7270 |
| at_enrollment | 24 | Gradient Boosting Classifier | 0.5766 ± 0.0202 | 0.6246 | 0.6108 |

Figures: `figures/dropout_macro_f1.png`, `figures/dropout_full_confusion.png`, `figures/dropout_reduced_confusion.png`, `figures/dropout_full_calibration.png`, `figures/dropout_reduced_calibration.png`.

Reproducibility rerun of the reduced comparison matched: True.
