# M27 binary dropout model comparison

Generated: 2026-10-22T00:15:50+08:00

These models are research artifacts. They are not production-validated. No model in this file is a UCC student predictor, and no Low, Moderate, or High band is defined.

## Target and partition

Dropout is coded 1. Enrolled and Graduate are coded 0 and called Not Dropout.

Not Dropout is an observed grouping of UCI records labeled Enrolled or Graduate at the end of the normal course duration. It is not proof of future retention. Calling the negative class Not Dropout does not show that those students remain enrolled in a later term, and it is not a UCC retention label. Enrolled and Graduate stay different source labels; they are grouped only for this binary experiment.

The outer row partition is the M25 split: seed 42, 20% holdout, stratified on the original three-class Target. Those holdout rows were already reviewed in the three-class experiment. Scores on that partition are an exploratory reuse. They are not an independent final validation, and they do not choose the algorithm. Selection uses repeated stratified cross-validation on the training partition only.

Preprocessing sits inside each pipeline. One-hot encoding and, for logistic regression, scaling are fit on the training rows of each fold. The holdout partition is not used to fit preprocessing or to select an algorithm.

The selection metric is mean cross-validated F1 for the Dropout class. The margin is 0.01. Within that margin the earlier algorithm wins: Logistic Regression, then Random Forest Classifier, then Gradient Boosting Classifier. The majority baseline is not selectable. Accuracy is reported and is not the selection metric. Precision, recall, balanced accuracy, ROC-AUC, PR-AUC, Brier score, confusion counts, and calibration are reported with it. A constant score has undefined ROC-AUC in this report; scikit-learn's 0.5 placeholder is not stored.

The predicted class comes from the estimator's predict method. For these models that is a 0.5 cut on the positive probability. It is not a Low, Moderate, or High threshold. Logistic regression and random forest use class_weight="balanced". Gradient boosting uses balanced sample weights. The majority baseline does not. Balanced weights move the 0.5 decision, so the false-negative and false-positive counts are not the rates of an unweighted fit.

False negatives are observed Dropout rows predicted as Not Dropout. False positives are observed Enrolled or Graduate rows predicted as Dropout. Those two source labels are counted separately. No Low, Moderate, or High threshold is defined. No UCC mapping and no grade conversion are invented. None of the feature sets is production-validated.

SOURCE-DATA RESEARCH DEMO. A scored UCI row is not a prediction for a UCC student.

Training rows: 3539. Holdout rows: 885.
Training source counts: {'Graduate': 1767, 'Dropout': 1137, 'Enrolled': 635}.
Holdout source counts: {'Graduate': 442, 'Dropout': 284, 'Enrolled': 159}.
Training binary counts: {'Dropout': 1137, 'Not Dropout': 2402}.
Holdout binary counts: {'Dropout': 284, 'Not Dropout': 601}.
Full-file source counts: {'Graduate': 2209, 'Dropout': 1421, 'Enrolled': 794}.
Full-file binary counts: {'Dropout': 1421, 'Not Dropout': 3003}.
Holdout index SHA-256: `de6543c05ab9f5217ac461430845211cb2d0046be45724fd4e407c6db8a4798c`.

Repeated CV uses 5 splits × 5 repeats on the training partition. The out-of-fold tables use one stratified 5-fold pass, separate from the repeated folds used for selection.

## Selected comparison

Each row is the algorithm chosen inside that feature set by training-partition Dropout F1. The scholarship row is a weak compatibility baseline, not a promotion to deployment. The full and timing rows still use UCI fields that have no verified UCC equivalent.

| Feature set | Role | Columns | Selected by Dropout F1 | CV Dropout F1 | CV ROC-AUC | CV PR-AUC | CV Brier | CV accuracy | OOF FN | OOF FP |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| full | exploratory_reference | 36 | Logistic Regression | 0.7965 ± 0.0183 | 0.9235 ± 0.0120 | 0.8845 ± 0.0174 | 0.0996 ± 0.0072 | 0.8657 ± 0.0118 | 206 | 264 |
| after_first_semester | timing_sensitivity | 30 | Logistic Regression | 0.7717 ± 0.0160 | 0.9046 ± 0.0120 | 0.8586 ± 0.0187 | 0.1133 ± 0.0067 | 0.8484 ± 0.0110 | 231 | 312 |
| at_enrollment | timing_sensitivity | 24 | Logistic Regression | 0.6542 ± 0.0223 | 0.8236 ± 0.0165 | 0.7372 ± 0.0242 | 0.1644 ± 0.0063 | 0.7613 ± 0.0151 | 340 | 510 |
| scholarship_only | weak_compatibility_baseline | 1 | Logistic Regression | 0.5405 ± 0.0088 | 0.6116 ± 0.0115 | 0.3799 ± 0.0071 | 0.2341 ± 0.0040 | 0.5089 ± 0.0130 | 115 | 1623 |

## Feature timing

### full

Uses all 36 UCI predictors, including second-semester curricular units and grades. Those fields are late academic results. This set is an exploratory reference, not an enrollment-time model, and it has no verified UCC mapping.

Columns kept: 36. Removed: None. The set keeps all 36 UCI predictors.

### after_first_semester

Drops the six second-semester curricular columns and keeps first-semester curricular results. The columns are appropriate only after those first-semester results exist. Grades stay on the UCI scale. No UCC conversion is applied.

Columns kept: 30. Removed: Curricular units 2nd sem (credited), Curricular units 2nd sem (enrolled), Curricular units 2nd sem (evaluations), Curricular units 2nd sem (approved), Curricular units 2nd sem (grade), Curricular units 2nd sem (without evaluations).

### at_enrollment

Drops all twelve curricular-unit columns. Debtor and tuition fees up to date stay in this cut, matching the M25 timing definition, but their snapshot date is unverified and they may not be known at enrollment. Macroeconomic fields and demographic codes remain. They are UCI source fields. No UCC mapping was added.

Columns kept: 24. Removed: Curricular units 1st sem (credited), Curricular units 1st sem (enrolled), Curricular units 1st sem (evaluations), Curricular units 1st sem (approved), Curricular units 1st sem (grade), Curricular units 1st sem (without evaluations), Curricular units 2nd sem (credited), Curricular units 2nd sem (enrolled), Curricular units 2nd sem (evaluations), Curricular units 2nd sem (approved), Curricular units 2nd sem (grade), Curricular units 2nd sem (without evaluations).

### scholarship_only

Uses only the UCI Scholarship holder column coded 0 or 1 in the source file. This is a weak compatibility baseline. It is not a model of the five EduPredict scholarship statuses, and a missing UCC profile is not recoded to 0.

Columns kept: 1. Removed: Every UCI predictor except Scholarship holder.

## Results by feature set

### full

Role: exploratory_reference. Uses all 36 UCI predictors, including second-semester curricular units and grades. Those fields are late academic results. This set is an exploratory reference, not an enrollment-time model, and it has no verified UCC mapping.

Training-partition repeated CV. Accuracy is the last metric and is not the selection rule.

| Algorithm | Precision | Recall | Dropout F1 | Balanced accuracy | ROC-AUC | PR-AUC | Brier | Accuracy | Train Dropout F1 | F1 gap |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.7762 ± 0.0231 | 0.8190 ± 0.0303 | 0.7965 ± 0.0183 | 0.8534 ± 0.0145 | 0.9235 ± 0.0120 | 0.8845 ± 0.0174 | 0.0996 ± 0.0072 | 0.8657 ± 0.0118 | 0.8239 | 0.0273 |
| Random Forest Classifier | 0.8764 ± 0.0213 | 0.6909 ± 0.0325 | 0.7722 ± 0.0234 | 0.8223 ± 0.0164 | 0.9166 ± 0.0118 | 0.8725 ± 0.0172 | 0.1006 ± 0.0063 | 0.8693 ± 0.0115 | 1.0000 | 0.2278 |
| Gradient Boosting Classifier | 0.7783 ± 0.0226 | 0.8075 ± 0.0371 | 0.7921 ± 0.0229 | 0.8491 ± 0.0183 | 0.9205 ± 0.0132 | 0.8793 ± 0.0187 | 0.1011 ± 0.0069 | 0.8640 ± 0.0138 | 0.8480 | 0.0558 |
| Majority baseline | 0.0000 ± 0.0000 | 0.0000 ± 0.0000 | 0.0000 ± 0.0000 | 0.5000 ± 0.0000 | n/a | 0.3213 ± 0.0007 | 0.3213 ± 0.0007 | 0.6787 ± 0.0007 | 0.0000 | 0.0000 |

Selected by Dropout F1: **Logistic Regression**. Contenders within 0.01: Logistic Regression, Gradient Boosting Classifier.

Highest accuracy: Random Forest Classifier. The saved algorithm was not chosen by accuracy.

Highest balanced accuracy: Logistic Regression. That match does not make balanced accuracy the selection metric.

Highest PR-AUC: Logistic Regression. That match does not make PR-AUC the selection metric.

Training-partition out-of-fold scores for every algorithm. This is the error analysis used to inspect false negatives and false positives. It is not the reused holdout.

| Algorithm | Precision | Recall | Dropout F1 | Balanced accuracy | FN | FNR | FP | FP Enrolled | FP Graduate | FPR | ROC-AUC | PR-AUC | Brier | Accuracy |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.7791 | 0.8188 | 0.7985 | 0.8545 | 206 | 0.1812 | 264 | 176 | 88 | 0.1099 | 0.9237 | 0.8830 | 0.0991 | 0.8672 |
| Random Forest Classifier | 0.8798 | 0.6887 | 0.7726 | 0.8221 | 354 | 0.3113 | 107 | 89 | 18 | 0.0445 | 0.9171 | 0.8704 | 0.1009 | 0.8697 |
| Gradient Boosting Classifier | 0.7729 | 0.8083 | 0.7902 | 0.8479 | 218 | 0.1917 | 270 | 194 | 76 | 0.1124 | 0.9186 | 0.8763 | 0.1016 | 0.8621 |
| Majority baseline | 0.0000 | 0.0000 | 0.0000 | 0.5000 | 1137 | 1.0000 | 0 | 0 | 0 | 0.0000 | n/a | 0.3213 | 0.3213 | 0.6787 |

Out-of-fold confusion for the selected algorithm (Logistic Regression). Rows are observed labels and columns are predicted labels.

| Observed \ Predicted | Not Dropout | Dropout |
| --- | --- | --- |
| Not Dropout | 2138 | 264 |
| Dropout | 206 | 931 |

Out-of-fold calibration for that selected algorithm, quantile bins of predicted P(Dropout).

| Mean predicted P(Dropout) | Observed Dropout fraction |
| --- | --- |
| 0.0263 | 0.0181 |
| 0.0562 | 0.0430 |
| 0.0924 | 0.0633 |
| 0.1596 | 0.0677 |
| 0.3048 | 0.1923 |
| 0.5767 | 0.3937 |
| 0.9086 | 0.8213 |
| 0.9919 | 0.9707 |

### after_first_semester

Role: timing_sensitivity. Drops the six second-semester curricular columns and keeps first-semester curricular results. The columns are appropriate only after those first-semester results exist. Grades stay on the UCI scale. No UCC conversion is applied.

Training-partition repeated CV. Accuracy is the last metric and is not the selection rule.

| Algorithm | Precision | Recall | Dropout F1 | Balanced accuracy | ROC-AUC | PR-AUC | Brier | Accuracy | Train Dropout F1 | F1 gap |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.7485 ± 0.0233 | 0.7975 ± 0.0291 | 0.7717 ± 0.0160 | 0.8350 ± 0.0127 | 0.9046 ± 0.0120 | 0.8586 ± 0.0187 | 0.1133 ± 0.0067 | 0.8484 ± 0.0110 | 0.8005 | 0.0288 |
| Random Forest Classifier | 0.8574 ± 0.0232 | 0.6544 ± 0.0324 | 0.7417 ± 0.0239 | 0.8013 ± 0.0163 | 0.8974 ± 0.0131 | 0.8470 ± 0.0211 | 0.1136 ± 0.0063 | 0.8539 ± 0.0116 | 1.0000 | 0.2583 |
| Gradient Boosting Classifier | 0.7542 ± 0.0218 | 0.7838 ± 0.0373 | 0.7683 ± 0.0245 | 0.8314 ± 0.0193 | 0.9000 ± 0.0137 | 0.8541 ± 0.0190 | 0.1146 ± 0.0065 | 0.8484 ± 0.0144 | 0.8266 | 0.0582 |
| Majority baseline | 0.0000 ± 0.0000 | 0.0000 ± 0.0000 | 0.0000 ± 0.0000 | 0.5000 ± 0.0000 | n/a | 0.3213 ± 0.0007 | 0.3213 ± 0.0007 | 0.6787 ± 0.0007 | 0.0000 | 0.0000 |

Selected by Dropout F1: **Logistic Regression**. Contenders within 0.01: Logistic Regression, Gradient Boosting Classifier.

Highest accuracy: Random Forest Classifier. The saved algorithm was not chosen by accuracy.

Highest balanced accuracy: Logistic Regression. That match does not make balanced accuracy the selection metric.

Highest PR-AUC: Logistic Regression. That match does not make PR-AUC the selection metric.

Training-partition out-of-fold scores for every algorithm. This is the error analysis used to inspect false negatives and false positives. It is not the reused holdout.

| Algorithm | Precision | Recall | Dropout F1 | Balanced accuracy | FN | FNR | FP | FP Enrolled | FP Graduate | FPR | ROC-AUC | PR-AUC | Brier | Accuracy |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.7438 | 0.7968 | 0.7694 | 0.8335 | 231 | 0.2032 | 312 | 196 | 116 | 0.1299 | 0.9044 | 0.8563 | 0.1134 | 0.8466 |
| Random Forest Classifier | 0.8593 | 0.6552 | 0.7435 | 0.8022 | 392 | 0.3448 | 122 | 89 | 33 | 0.0508 | 0.8981 | 0.8460 | 0.1139 | 0.8548 |
| Gradient Boosting Classifier | 0.7525 | 0.7836 | 0.7678 | 0.8308 | 246 | 0.2164 | 293 | 211 | 82 | 0.1220 | 0.8987 | 0.8534 | 0.1146 | 0.8477 |
| Majority baseline | 0.0000 | 0.0000 | 0.0000 | 0.5000 | 1137 | 1.0000 | 0 | 0 | 0 | 0.0000 | n/a | 0.3213 | 0.3213 | 0.6787 |

Out-of-fold confusion for the selected algorithm (Logistic Regression). Rows are observed labels and columns are predicted labels.

| Observed \ Predicted | Not Dropout | Dropout |
| --- | --- | --- |
| Not Dropout | 2090 | 312 |
| Dropout | 231 | 906 |

Out-of-fold calibration for that selected algorithm, quantile bins of predicted P(Dropout).

| Mean predicted P(Dropout) | Observed Dropout fraction |
| --- | --- |
| 0.0362 | 0.0271 |
| 0.0761 | 0.0588 |
| 0.1233 | 0.0792 |
| 0.1992 | 0.0926 |
| 0.3378 | 0.1855 |
| 0.5709 | 0.4072 |
| 0.8679 | 0.7534 |
| 0.9873 | 0.9661 |

### at_enrollment

Role: timing_sensitivity. Drops all twelve curricular-unit columns. Debtor and tuition fees up to date stay in this cut, matching the M25 timing definition, but their snapshot date is unverified and they may not be known at enrollment. Macroeconomic fields and demographic codes remain. They are UCI source fields. No UCC mapping was added.

Training-partition repeated CV. Accuracy is the last metric and is not the selection rule.

| Algorithm | Precision | Recall | Dropout F1 | Balanced accuracy | ROC-AUC | PR-AUC | Brier | Accuracy | Train Dropout F1 | F1 gap |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.6123 ± 0.0225 | 0.7033 ± 0.0341 | 0.6542 ± 0.0223 | 0.7460 ± 0.0175 | 0.8236 ± 0.0165 | 0.7372 ± 0.0242 | 0.1644 ± 0.0063 | 0.7613 ± 0.0151 | 0.6877 | 0.0336 |
| Random Forest Classifier | 0.7852 ± 0.0313 | 0.4725 ± 0.0360 | 0.5892 ± 0.0314 | 0.7056 ± 0.0182 | 0.8156 ± 0.0139 | 0.7235 ± 0.0193 | 0.1535 ± 0.0046 | 0.7889 ± 0.0129 | 1.0000 | 0.4108 |
| Gradient Boosting Classifier | 0.6175 ± 0.0259 | 0.6788 ± 0.0289 | 0.6463 ± 0.0218 | 0.7396 ± 0.0167 | 0.8231 ± 0.0169 | 0.7394 ± 0.0246 | 0.1624 ± 0.0059 | 0.7613 ± 0.0161 | 0.7286 | 0.0823 |
| Majority baseline | 0.0000 ± 0.0000 | 0.0000 ± 0.0000 | 0.0000 ± 0.0000 | 0.5000 ± 0.0000 | n/a | 0.3213 ± 0.0007 | 0.3213 ± 0.0007 | 0.6787 ± 0.0007 | 0.0000 | 0.0000 |

Selected by Dropout F1: **Logistic Regression**. Contenders within 0.01: Logistic Regression, Gradient Boosting Classifier.

Highest accuracy: Random Forest Classifier. The saved algorithm was not chosen by accuracy.

Highest balanced accuracy: Logistic Regression. That match does not make balanced accuracy the selection metric.

Highest PR-AUC: Gradient Boosting Classifier. The saved algorithm was not chosen by PR-AUC.

Training-partition out-of-fold scores for every algorithm. This is the error analysis used to inspect false negatives and false positives. It is not the reused holdout.

| Algorithm | Precision | Recall | Dropout F1 | Balanced accuracy | FN | FNR | FP | FP Enrolled | FP Graduate | FPR | ROC-AUC | PR-AUC | Brier | Accuracy |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.6098 | 0.7010 | 0.6522 | 0.7443 | 340 | 0.2990 | 510 | 216 | 294 | 0.2123 | 0.8233 | 0.7375 | 0.1642 | 0.7598 |
| Random Forest Classifier | 0.7808 | 0.4573 | 0.5768 | 0.6983 | 617 | 0.5427 | 146 | 62 | 84 | 0.0608 | 0.8153 | 0.7196 | 0.1540 | 0.7844 |
| Gradient Boosting Classifier | 0.6120 | 0.6799 | 0.6442 | 0.7379 | 364 | 0.3201 | 490 | 212 | 278 | 0.2040 | 0.8191 | 0.7330 | 0.1641 | 0.7587 |
| Majority baseline | 0.0000 | 0.0000 | 0.0000 | 0.5000 | 1137 | 1.0000 | 0 | 0 | 0 | 0.0000 | n/a | 0.3213 | 0.3213 | 0.6787 |

Out-of-fold confusion for the selected algorithm (Logistic Regression). Rows are observed labels and columns are predicted labels.

| Observed \ Predicted | Not Dropout | Dropout |
| --- | --- | --- |
| Not Dropout | 1892 | 510 |
| Dropout | 340 | 797 |

Out-of-fold calibration for that selected algorithm, quantile bins of predicted P(Dropout).

| Mean predicted P(Dropout) | Observed Dropout fraction |
| --- | --- |
| 0.0783 | 0.0542 |
| 0.1571 | 0.1063 |
| 0.2300 | 0.1357 |
| 0.3156 | 0.1896 |
| 0.4259 | 0.2760 |
| 0.5670 | 0.3643 |
| 0.7467 | 0.5611 |
| 0.9497 | 0.8826 |

### scholarship_only

Role: weak_compatibility_baseline. Uses only the UCI Scholarship holder column coded 0 or 1 in the source file. This is a weak compatibility baseline. It is not a model of the five EduPredict scholarship statuses, and a missing UCC profile is not recoded to 0.

Training-partition repeated CV. Accuracy is the last metric and is not the selection rule.

| Algorithm | Precision | Recall | Dropout F1 | Balanced accuracy | ROC-AUC | PR-AUC | Brier | Accuracy | Train Dropout F1 | F1 gap |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.3865 ± 0.0074 | 0.8989 ± 0.0142 | 0.5405 ± 0.0088 | 0.6116 ± 0.0115 | 0.6116 ± 0.0115 | 0.3799 ± 0.0071 | 0.2341 ± 0.0040 | 0.5089 ± 0.0130 | 0.5405 | -0.0000 |
| Random Forest Classifier | 0.3865 ± 0.0074 | 0.8989 ± 0.0142 | 0.5405 ± 0.0088 | 0.6116 ± 0.0115 | 0.6116 ± 0.0115 | 0.3799 ± 0.0071 | 0.2339 ± 0.0040 | 0.5089 ± 0.0130 | 0.5405 | -0.0000 |
| Gradient Boosting Classifier | 0.3865 ± 0.0074 | 0.8989 ± 0.0142 | 0.5405 ± 0.0088 | 0.6116 ± 0.0115 | 0.6116 ± 0.0115 | 0.3799 ± 0.0071 | 0.2341 ± 0.0040 | 0.5089 ± 0.0130 | 0.5405 | -0.0000 |
| Majority baseline | 0.0000 ± 0.0000 | 0.0000 ± 0.0000 | 0.0000 ± 0.0000 | 0.5000 ± 0.0000 | n/a | 0.3213 ± 0.0007 | 0.3213 ± 0.0007 | 0.6787 ± 0.0007 | 0.0000 | 0.0000 |

Selected by Dropout F1: **Logistic Regression**. Contenders within 0.01: Logistic Regression, Random Forest Classifier, Gradient Boosting Classifier.

Highest accuracy: Majority baseline. The saved algorithm was not chosen by accuracy.

Highest balanced accuracy: Logistic Regression, Random Forest Classifier, Gradient Boosting Classifier. Logistic Regression is in that tie. The selection metric remains Dropout F1.

Highest PR-AUC: Logistic Regression, Random Forest Classifier, Gradient Boosting Classifier. Logistic Regression is in that tie. The selection metric remains Dropout F1.

Training-partition out-of-fold scores for every algorithm. This is the error analysis used to inspect false negatives and false positives. It is not the reused holdout.

| Algorithm | Precision | Recall | Dropout F1 | Balanced accuracy | FN | FNR | FP | FP Enrolled | FP Graduate | FPR | ROC-AUC | PR-AUC | Brier | Accuracy |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Logistic Regression | 0.3864 | 0.8989 | 0.5405 | 0.6116 | 115 | 0.1011 | 1623 | 531 | 1092 | 0.6757 | 0.6067 | 0.3789 | 0.2340 | 0.5089 |
| Random Forest Classifier | 0.3864 | 0.8989 | 0.5405 | 0.6116 | 115 | 0.1011 | 1623 | 531 | 1092 | 0.6757 | 0.6076 | 0.3797 | 0.2339 | 0.5089 |
| Gradient Boosting Classifier | 0.3864 | 0.8989 | 0.5405 | 0.6116 | 115 | 0.1011 | 1623 | 531 | 1092 | 0.6757 | 0.6067 | 0.3789 | 0.2340 | 0.5089 |
| Majority baseline | 0.0000 | 0.0000 | 0.0000 | 0.5000 | 1137 | 1.0000 | 0 | 0 | 0 | 0.0000 | n/a | 0.3213 | 0.3213 | 0.6787 |

Out-of-fold confusion for the selected algorithm (Logistic Regression). Rows are observed labels and columns are predicted labels.

| Observed \ Predicted | Not Dropout | Dropout |
| --- | --- | --- |
| Not Dropout | 779 | 1623 |
| Dropout | 115 | 1022 |

Out-of-fold calibration for that selected algorithm, quantile bins of predicted P(Dropout).

| Mean predicted P(Dropout) | Observed Dropout fraction |
| --- | --- |
| 0.2343 | 0.1381 |
| 0.2441 | 0.1140 |
| 0.5683 | 0.3970 |
| 0.5706 | 0.3867 |
| 0.5711 | 0.3845 |
| 0.5716 | 0.3832 |
| 0.5725 | 0.3807 |

## Exploratory reuse of the M25 holdout

The holdout rows below are the same M25 20% partition. They were already reviewed in the three-class experiment. They were not used to fit these pipelines or to choose the algorithm. They are not an independent final validation.

| Feature set | Algorithm | Precision | Recall | Dropout F1 | FN | FP | FP Enrolled | FP Graduate | ROC-AUC | PR-AUC | Brier | Accuracy |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| full | Logistic Regression | 0.7607 | 0.8169 | 0.7878 | 52 | 73 | 51 | 22 | 0.9132 | 0.8796 | 0.1064 | 0.8588 |
| after_first_semester | Logistic Regression | 0.7360 | 0.7852 | 0.7598 | 61 | 80 | 50 | 30 | 0.9043 | 0.8611 | 0.1152 | 0.8407 |
| at_enrollment | Logistic Regression | 0.6031 | 0.6901 | 0.6437 | 88 | 129 | 49 | 80 | 0.8157 | 0.7298 | 0.1683 | 0.7548 |
| scholarship_only | Logistic Regression | 0.3897 | 0.9331 | 0.5498 | 19 | 415 | 133 | 282 | 0.6213 | 0.3851 | 0.2323 | 0.5096 |

Exploratory holdout confusion, full, Logistic Regression.

| Observed \ Predicted | Not Dropout | Dropout |
| --- | --- | --- |
| Not Dropout | 528 | 73 |
| Dropout | 52 | 232 |

Exploratory holdout confusion, after_first_semester, Logistic Regression.

| Observed \ Predicted | Not Dropout | Dropout |
| --- | --- | --- |
| Not Dropout | 521 | 80 |
| Dropout | 61 | 223 |

Exploratory holdout confusion, at_enrollment, Logistic Regression.

| Observed \ Predicted | Not Dropout | Dropout |
| --- | --- | --- |
| Not Dropout | 472 | 129 |
| Dropout | 88 | 196 |

Exploratory holdout confusion, scholarship_only, Logistic Regression.

| Observed \ Predicted | Not Dropout | Dropout |
| --- | --- | --- |
| Not Dropout | 186 | 415 |
| Dropout | 19 | 265 |

## Artifacts

Saved pipelines were fit on the training partition only. `production_validated` is false and `risk_thresholds` is null. The M25 three-class scholarship candidate and the employability linear regression were not overwritten.

| Feature set | Algorithm | Model version | Path |
| --- | --- | --- | --- |
| full | Logistic Regression | m27-binary-dropout-full-logistic_regression-2026-10-22T00:15:50+08:00 | ml/models/dropout_binary_full_exploratory_m27.joblib |
| after_first_semester | Logistic Regression | m27-binary-dropout-after_first_semester-logistic_regression-2026-10-22T00:15:50+08:00 | ml/models/dropout_binary_after_first_semester_m27.joblib |
| at_enrollment | Logistic Regression | m27-binary-dropout-at_enrollment-logistic_regression-2026-10-22T00:15:50+08:00 | ml/models/dropout_binary_at_enrollment_m27.joblib |
| scholarship_only | Logistic Regression | m27-binary-dropout-scholarship_only-logistic_regression-2026-10-22T00:15:50+08:00 | ml/models/dropout_binary_scholarship_baseline_m27.joblib |

M25 dropout candidate SHA-256 unchanged: `24a9505b241fa6587a44c2a572f64470aee00f3d6dd2cecd0168fe940250c95f`.
M25 employability candidate SHA-256 unchanged: `e8e30ce6ccc5785abc96b6bb896533a1fefcc31cdd4818558908a6d9370fe705`.

## Employability benchmark

The existing research benchmark remains linear regression on Pianda `CompetenceMean` and `PsyCapMean`. M27 does not refit it and does not pass UCC questionnaire scores through it.

Artifact: `employability_candidate_v1`. Algorithm: Linear Regression. Trained at: 2026-10-21T22:41:22+08:00. Stored CV RMSE: 0.3664. production_validated: False. Refit in M27: false.

The source-data demo scores one held-out Pianda row with that saved pipeline:

```bash
python -m src.demo_m27 employability --offset 0
```

## Dropout source-data demo

The dropout demo loads a saved M27 pipeline and one held-out UCI row. The default feature set is the full exploratory model, so the printed probability uses late curricular fields. The command prints the source-data banner, the observed UCI label, the model version, and P(Dropout). It does not present the row as a UCC student.

```bash
python -m src.demo_m27 dropout --offset 0
python -m src.demo_m27 dropout --offset 0 --feature-set scholarship_only
```

## Reproducibility

Scholarship-only CV, all algorithms, matched a second run: True.
Full-set selected algorithm (Logistic Regression) CV matched a second run: True.

Library versions: {'numpy': '2.3.4', 'pandas': '2.3.3', 'scipy': '1.16.2', 'scikit-learn': '1.7.2', 'matplotlib': '3.10.7', 'joblib': '1.5.2', 'openpyxl': '3.1.5'}.
Dataset: {'path': 'ml/data/raw/dropout/data.csv', 'bytes': 533230, 'sha256': '3ef126de5cefff26eb11fbb4237f1a1401cb64b488e2f1d598c23cedeb4c45ae'}.

## Warnings

No library warnings were recorded.

## Boundaries

Laravel, the database, the questionnaire, FeatureBuilder, HeuristicPredictor, and the previous prediction artifacts were not modified. No FastAPI service was added. No risk band was chosen.
