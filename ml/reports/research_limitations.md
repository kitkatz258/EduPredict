# Research limitations (M42)

## External population gap

Pianda respondents are vocational secondary students in Aceh, Indonesia, ages about 16–18, all with internship experience, surveyed in 2022. Intended EduPredict users are UCC CLAS undergraduates in Caloocan across multiple programs. UCI respondents are students in Portuguese higher education with administrative enrollment and curricular-unit records. Neither sample is UCC.

## Survey validation

`ucc-panel-revised-60-v2` is a panel-revised 60-item draft pending reapproval. Expert I-CVI, S-CVI/Ave, and per-section Cronbach alpha are **not observed**. The instrument must not be labeled panel-reapproved or psychometrically validated. Historic 36-item responses stay on `ucc-panel-approved-v1` and were not used as training data.

## Construct transfer

No UCC item is a verbatim Pianda item. C item counts now both equal 10; that is not equivalence. P is 10 versus 14. E is 10 versus 12 EVS target items and is excluded from predictors. Same-occasion self-report association on Pianda can produce a moderate R² without predicting hiring.

## Dropout transfer

Directly usable UCI predictors in EduPredict: **none**. Scholarship collapse is unverified. Unit counts lack a first-semester definition. Grades are the wrong scale and were excluded rather than converted. P(Dropout) from these models is source-trained and not UCC-calibrated. No Low/Moderate/High cut is defined.

## Calibration and labels

No UCC employment or dropout outcome labels exist in this repository. Synthesizing labels from current grades would leak the outcome from the predictors. Calibration on Portuguese or Indonesian samples does not calibrate UCC probabilities.

## What cannot be fed into the trained models

UCC questionnaire answers, UCC section means, Philippine GWA, invented missing values, A/S/B as dropout features, E as an employability feature, and UCI second-semester fields at enrollment.

## Missing UCC outcome-label blockers for M43

1. Locked, reapproved instrument with recorded CVI and a CLAS-similar pilot.
2. Equivalence study or an explicit decision that UCC C/P are new scales.
3. Consenting later UCC employability and retention labels, not reconstructed from GWA.
4. Written unit-count and scholarship rules that can fail review.
5. Local calibration and fairness review before any driver switch.

A research candidate that works on Pianda or UCI data is **not** validated on UCC data.
