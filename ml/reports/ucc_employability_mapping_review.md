# UCC and Pianda employability mapping review

Review date: 2026-10-21. This review reads the approved UCC questionnaire PDFs that were absent when M25 commit `d1615b10` was written. It does not retrain a model, does not change the saved linear-regression artifact, and does not treat either M25 candidate as production-ready.

The employability research candidate remains a linear regression fit on the Pianda training split. Its inputs are `CompetenceMean` (unweighted mean of Pianda C1–C10) and `PsyCapMean` (unweighted mean of Pianda PsyCap1–PsyCap14). Its target is `EmployabilityMean` (unweighted mean of EVS1–EVS12). UCC section means are not validated substitutes for those inputs.

## Document status

This review was asked to treat the two PDFs as the approved UCC instrument. The PDF text itself still says the questionnaire is a draft for expert validation and that the scoring paper is a consultation draft. Both statements are recorded. Panel approval, and the request to treat the files as approved, is not psychometric validation, construct equivalence, or external validity.

| File | Bytes | SHA-256 | PDF title and date |
| --- | --- | --- | --- |
| `src/cursor/reference/approved-questionnaire/EduPredict_Questionnaire.pdf` | 172602 | `fd34cfccd89c70ca790d3c495889b057645ffc7fab1202473a804ba8ac40ef43` | "EduPredict Student Questionnaire". Header: "Draft instrument for expert validation". Created 2026-10-21. |
| `src/cursor/reference/approved-questionnaire/EduPredict_Scoring_and_Research_Basis.pdf` | 265759 | `0a47b9ca326494b6b63307cf9e85b98df5df36219b57ba052ff9882b66cdcb86` | "EduPredict: Questionnaire Scoring, Computation, and Supporting Studies". Header: "Scoring and research basis (consultation draft)". Created 2026-10-21. |

The PDFs are untracked. M25 `training_summary.md` correctly said they were missing at training time. Item text below was extracted from these files and checked against all 36 printed codes. The Pianda English wording was taken from `ml/data/raw/employability/Survey questionnaire.docx` and checked against the `Codebook` sheet of `Raw Dataset.xlsx` (SHA-256 `f5e6fcdbb4e5687871a0b1727ca1f2ba3e1e7e6f785895ad763324617bcc064f`).

The scoring PDF, section 1, says the employability and dropout models are not trained yet and that training should wait for the revised questionnaire and the final dropout-feature set. M25 later fit source-data research candidates anyway. Those fits do not meet the scoring document's waiting condition for use on UCC answers.

Three sampling descriptions of the Pianda data are now on record and were not reconciled:

- Mendeley Version 4, as audited in M25: simple random sampling.
- Pianda et al. (2025) article, as audited in M25: cluster sample of 266 students in 17 schools.
- Scoring PDF reference (Data in Brief title on page 9): "A stratified survey dataset".

## How to read the comparison

Conceptual similarity is a content reading of the English sentences in this review. It is not an inter-rater code, a factor loading, or a claim that two items measure the same quantity.

| Label | Meaning in this review |
| --- | --- |
| Close content | The sentences describe the same claimed behavior in similar words. |
| Partial | The sentences share a theme. The behavior, object, or referent differs. |
| None | No Pianda competence or PsyCap item states that behavior. |

Validated measurement equivalence would require evidence that the two scores estimate the same construct on the same metric. None of that evidence is in the workbook, the codebook, the questionnaire file, or the UCC PDFs. Every row below is therefore **not measurement-equivalent**, including rows marked close content.

EVS1–EVS12 are the Pianda target and are not eligible predictor pairs. SIE1–SIE14 are internship-experience items. The UCC instrument has no SIE section. They are not used as pairs for C or P.

No Pianda item and no UCC item share the same English sentence. A normalized full-string comparison of the codebook English against the 14 UCC C and P sentences found no exact match.

## Shared scoring arithmetic, different instruments

| Rule | Pianda C and PsyCap | Approved UCC C and P |
| --- | --- | --- |
| Response scale | 1 strongly disagree to 5 strongly agree | 1 Strongly Disagree to 5 Strongly Agree |
| Midpoint label | "Neither Agree nor Disagree". The Indonesian line is "Tidak setuju maupun tidak menolak". | "Neutral" |
| Reverse scoring | None stated in the codebook or the bilingual form | Scoring PDF section 3.1: all 36 items are positively worded, so none are reverse scored |
| Section score | Unweighted mean. Stored `AVERAGE (C)` and `AVERAGE (PsyCap)` match the recomputed means (max absolute difference 0) | Unweighted mean: sum of responses divided by the number of items in the section |
| Item count | C: 10. PsyCap: 14 | C: 8. P: 6 |
| Published subscales | C: life and career (C1–C4), learning and innovation (C5–C7), information technology and media (C8–C10). PsyCap: hope (1–3), optimism (4–7), resiliency (8–11), self-efficacy (12–14) | No item subgroups. P is described with Luthans' hope, optimism, resilience, and self-efficacy, but the six sentences are a new set |
| Numeric range of a complete mean | 1–5 | 1.00–5.00 |
| Population | 266 SMK vocational students in Aceh with internship experience, surveyed August–December 2022, codebook ages about 16–18 | Intended for UCC CLAS undergraduates. The scoring PDF says the instrument has not been validated for those students |

The mean formula is the same kind of average. A mean of 10 items and a mean of 8 different items are not the same measurement. Substituting one for the other is not justified by the shared 1–5 arithmetic.

The Laravel scorer is a third procedure. `QuestionnaireScorer` reverse-scores selected draft items as `6 - value`, then scales each construct mean to 0–100. The seeded instrument is `draft-v1`: 20 academic-behavior items in study habits, time management, motivation, procrastination, and engagement. It does not contain C1–C8 or P1–P6. `FeatureBuilder` reads those five 0–100 scores. They are outside the saved pipeline's `likert_mean` rule, which requires columns named `CompetenceMean` and `PsyCapMean` inside 1–5. The schema checks names and range. It cannot detect a UCC mean placed in a Pianda column.

## Competence: UCC C1–C8 against Pianda C1–C10

UCC section C is "Competence / Employability Skills". Pianda section D is "Competence (C)". The scoring PDF says these items come from the Pianda dataset. That is an adaptation claim. The sentences below are not the Pianda items.

| UCC | UCC statement | Closest Pianda item | Pianda statement | Content reading | Equivalence |
| --- | --- | --- | --- | --- | --- |
| C1 | I can communicate my ideas clearly to other people. | C6, and secondarily C2 | C6: I was able to communicate clearly and collaborate with other group members. C2: I have the ability to interact clearly and effectively with group members who have different backgrounds, beliefs or opinions. | Partial. UCC C1 is general communication. Pianda C6 adds collaboration. Pianda C2 is diverse-group interaction. | Not established |
| C2 | I can work effectively with other people in a team. | C6 and C2 | Same as above. Pianda C4 is leadership, not teamwork. | Partial. No Pianda competence item says the person can work effectively in a team. | Not established |
| C3 | I can identify problems and develop appropriate solutions. | C5 and C8 | C5: I am able to use inductive or deductive reasoning for a variety of situations; use systems thinking and decision making. C8: I am able to access information effectively and efficiently and evaluate the information used competently, and effectively to solve problems. | Partial. Reasoning and information evaluation are not the same behavior as identifying a problem and developing a solution. | Not established |
| C4 | I can adapt to new tools, technologies, and changing situations. | C1 | I have the ability to adapt quickly to changing situations or demands that occur in learning or group activities. | Partial. Both mention adaptation to change. Pianda C1 does not mention tools or technologies, and it is limited to learning or group activities. | Not established |
| C5 | I can use digital tools and technology effectively for academic or practical tasks. | C8, C9, and C10 | C9: I am able to select and develop media used for communication. C10: I was able to analyse information media and create appropriate media for communication. C8 is information access and evaluation. | Partial. These are the information-technology and media items. None states effective use of digital tools for academic or practical tasks. | Not established |
| C6 | I can apply what I learned in school to practical tasks or real-world situations. | None in C1–C10 | No competence item states transfer from school learning to practical or real-world tasks. Internship items describe the placement, and EVS items are the target. | None | Not established |
| C7 | I can organize my work and responsibilities to complete assigned tasks. | C3 | I have the ability to plan, organise and execute projects efficiently and effectively. | Partial. Both involve organization. Pianda C3 is project execution. UCC C7 is work, responsibilities, and assigned tasks. | Not established |
| C8 | I can learn new skills when a task or situation requires them. | None in C1–C10 | Pianda C7 is creative thinking and new innovations, which is a different behavior. | None | Not established |

Pianda competence items with no UCC sentence that states the same behavior:

| Pianda | Codebook subscale | Statement | What remains unmatched |
| --- | --- | --- | --- |
| C1 | Life and career | Adapt quickly to changing situations or demands in learning or group activities. | Only partly overlaps UCC C4. Tools and technologies are new in UCC. |
| C2 | Life and career | Interact clearly and effectively with group members who differ in background, belief, or opinion. | Diversity-specific interaction has no UCC item. |
| C3 | Life and career | Plan, organise, and execute projects efficiently and effectively. | Only partly overlaps UCC C7. |
| C4 | Life and career | Lead and inspire group members with a clear vision, purpose, and values. | No UCC competence item. |
| C5 | Learning and innovation | Use inductive or deductive reasoning, systems thinking, and decision making. | Only partly overlaps UCC C3. |
| C6 | Learning and innovation | Communicate clearly and collaborate with other group members. | Split loosely across UCC C1 and C2. |
| C7 | Learning and innovation | Think and work creatively, and create new innovations. | No UCC competence item. |
| C8 | Information technology and media | Access and evaluate information effectively to solve problems. | Only partly overlaps UCC C3 and C5. |
| C9 | Information technology and media | Select and develop media used for communication. | No UCC competence item. |
| C10 | Information technology and media | Analyse information media and create media for communication. | No UCC competence item. |

UCC C is an 8-item employability-skill list (communication, teamwork, problem solving, adaptation, digital tools, transfer, organization, learning new skills). Pianda C is a 10-item 21st-century competence list with three codebook subscales. The sets intersect in theme. They are not a subset, a reordering, or a shortened form of the same items.

## Psychological capital: UCC P1–P6 against Pianda PsyCap1–PsyCap14

UCC section P is "Psychological Capital / Career Readiness". Pianda section C is "Psychological Capital (PsyCap)". Three UCC items are close in content to three Pianda items: P1 with PsyCap12, P2 with PsyCap1, and P4 with PsyCap5. Close content is still not equivalence. The words differ, the referents differ, and the other eleven Pianda items do not have a close UCC sentence. UCC P6 does not correspond to a Luthans facet as written.

| UCC | UCC statement | Closest Pianda item | Pianda statement and codebook subscale | Content reading | Equivalence |
| --- | --- | --- | --- | --- | --- |
| P1 | I am confident that I can successfully handle difficult tasks related to my studies or future work. | PsyCap12 | I have a strong belief that I can complete difficult tasks successfully. Self-efficacy. | Close content. UCC adds studies or future work, and "confident" / "handle" replaces "strong belief" / "complete". | Not established |
| P2 | When I encounter a problem, I can think of different ways to overcome it. | PsyCap1, and secondarily PsyCap2 | PsyCap1: I can find various ways to overcome obstacles that arise during my studies. Hope. PsyCap2: I realized that many approaches can be used to overcome the problems faced. Hope. | Close content with PsyCap1. UCC drops the study setting and uses "problem" rather than "obstacles". PsyCap2 is recognition that approaches exist, not a first-person claim of being able to think of them. | Not established |
| P3 | I continue working toward my goals even after experiencing setbacks. | PsyCap10 and PsyCap3 | PsyCap10: I understand that everyone experiences failure and getting back up is the key to achieving long-term success. Resiliency. PsyCap3: I am committed to doing my best and working hard to achieve the goals I set. Hope. | Partial. PsyCap10 is a general belief about other people. PsyCap3 has no setback clause. Neither says "I continue after setbacks". | Not established |
| P4 | I generally expect that my efforts toward my studies and career will lead to positive results. | PsyCap5 | I am optimistic that the investment of time and effort I am making now will bring positive results in the future. Optimism. | Close content. UCC names studies and career and does not use the word optimistic. | Not established |
| P5 | I can recover and continue after academic or personal difficulties affect my progress. | PsyCap10 and PsyCap9 | PsyCap9: I always try to find out the cause of the low grades I receive and find solutions to improve them. Resiliency. | Partial. Recovery from academic or personal difficulties is not the general belief in PsyCap10 and is not the low-grade problem solving in PsyCap9. | Not established |
| P6 | I believe I can improve my abilities through effort, learning, and experience. | PsyCap11 | I have a strong intrinsic motivation to achieve high academic achievements and am always looking for ways to continuously improve myself. Resiliency. | Partial. P6 is a belief that ability can improve. PsyCap11 is motivation for high academic achievement. P6 is not a hope, optimism, resilience, or self-efficacy item in the Luthans sense cited by the scoring PDF. | Not established |

Pianda PsyCap items with no close UCC sentence:

| Pianda | Subscale | Statement | Note |
| --- | --- | --- | --- |
| PsyCap2 | Hope | Many approaches can be used to overcome the problems faced. | Secondary only to UCC P2. |
| PsyCap3 | Hope | Committed to doing my best and working hard to achieve the goals I set. | No setback language. |
| PsyCap4 | Optimism | I still have high hopes that I can succeed in learning situations during the pandemic. | Context-specific to pandemic learning. Absent from UCC. |
| PsyCap6 | Optimism | I tend to see positive lessons from every learning experience, both success and failure. | No UCC item. |
| PsyCap7 | Optimism | I have a strong belief that I can make my dreams come true and achieve the big goals I set. | No UCC item. |
| PsyCap8 | Resiliency | I can identify and analyze various problems that arise in learning. | Codebook places this under resiliency. The sentence is problem analysis. |
| PsyCap9 | Resiliency | I always try to find the cause of low grades and solutions to improve them. | No UCC item. |
| PsyCap10 | Resiliency | Everyone experiences failure, and getting back up is the key to long-term success. | Discussed under UCC P3 and P5. The sentence is a general belief, not "I continue" or "I can recover". |
| PsyCap11 | Resiliency | Strong intrinsic motivation for high academic achievement and continuous self-improvement. | Only partly overlaps UCC P6. |
| PsyCap13 | Self-efficacy | Enough self-confidence to ask the teacher when an assignment is difficult. | No UCC item. |
| PsyCap14 | Self-efficacy | Confident I can make a meaningful contribution to others with the abilities I have. | No UCC item. |

The codebook resiliency block (PsyCap8–PsyCap11) does not match the usual resilience wording, and it does not match UCC P3 or P5. A 6-item UCC mean is not a shortened 14-item Pianda mean. Eleven Pianda items have no close UCC sentence, including the pandemic item (PsyCap4), the low-grade item (PsyCap9), the teacher-help item (PsyCap13), and the contribution item (PsyCap14). UCC P3, P5, and P6 add wording the Pianda scale does not contain.

## What the saved linear model actually contains

The artifact `ml/models/employability_candidate_v1.joblib` was reloaded for this review. It was not refit.

- Algorithm: linear regression inside a pipeline that standardizes the two means, fit on the 212-row training split only.
- Standardized coefficients: CompetenceMean 0.296275, PsyCapMean 0.197444. Intercept 3.962657.
- Training-split scaler means: CompetenceMean 3.831604, PsyCapMean 4.134771.
- Selection: lowest mean repeated-CV RMSE, with a 0.01 margin. Linear regression and ridge were inside the margin. Linear regression won by the pre-specified order.
- Measured CV RMSE 0.3664 ± 0.0462, CV R² 0.5708 ± 0.1532, on the native 1–5 EVS mean. Holdout RMSE 0.2957 was not used to choose the algorithm.
- Metadata `production_validated` is false. `risk_thresholds` is null.

A UCC Competence mean and a UCC PsyCap mean could satisfy the pipeline's column names and the 1–5 range check. The coefficients and the scaler were estimated on different items, a different population, and a same-occasion self-report target. Passing UCC means through this pipeline would apply the Pianda standardizer and the Pianda coefficients to a different instrument. That is not a supported use.

The display conversion `((score - 1) / 4) * 100` is defined for a native score inside 1–5. The scoring PDF uses the same formula and then places results in Pimentel equal-width verbal bands (0.80 points) and a context flag at a section mean of 2.60 or below. The PDF says those bands are a descriptive convention, not cut-offs tested against data. They are not part of the saved artifact. This review does not choose a rounding rule for band edges.

## Evidence required before this model can score UCC responses

The following evidence is absent. Until it exists, the linear regression candidate stays a Pianda-sample research fit.

1. A locked instrument. The PDFs still say draft and consultation draft. The app still seeds a different `draft-v1` behavior questionnaire. Someone must record which text is the instrument that students will answer.
2. Content validation, as the scoring PDF already specifies and has not done: item CVI at least 0.78 and scale CVI average at least 0.90, with every revision recorded. Approval of the current file does not supply those indices.
3. Pilot reliability on students similar to CLAS users, computed separately for the 8-item C scale and the 6-item P scale. The PDF cites 0.70 as the usual Cronbach alpha benchmark. No pilot matrix is in the repository.
4. A measurement decision with data behind it. Either declare that UCC C and P are new scales and must not be scored by the Pianda coefficients, or run an equivalence study that can fail. Equivalence evidence would include matched item content, a measurement model, and a check that the means are on the same metric. Close wording on P1, P2, and P4 is not that study. No UCC response rows exist here, so this repository cannot host that study yet.
5. A target decision. The fitted target is the Pianda EVS mean from the same sitting as the predictors. It is not a job offer, a placement, or UCC E1–E4. E1–E4 is a four-item self-perception scale and remains a comparison measure. Common-method association can produce the observed CV R² of about 0.57 without predicting employment.
6. Population and sampling evidence. The training rows are internship-experienced vocational secondary students in Aceh. UCC CLAS undergraduates are a different population. The simple-random, cluster, and stratified descriptions disagree.
7. A local criterion, collected later with consent, if the claim is about UCC employability rather than about the Pianda questionnaire. The scoring PDF, section 6, already states this limitation.

Retraining the same two Pianda means on the same 266 rows would not create any of this evidence.

## Blockers and decisions

| Blocker | Decision required |
| --- | --- |
| UCC C1–C8 is not Pianda C1–C10. C1–C5 and C7 are only partial matches. C6 and C8 have no competence pair. | Do not score UCC C with `CompetenceMean` from the saved pipeline. Decide whether a future model will be trained on new data with the UCC items, which cannot happen until those responses and a target exist. |
| UCC P1–P6 is not Pianda PsyCap1–PsyCap14. P1, P2, and P4 are close in content. P3, P5, and P6 are partial. PsyCap4 is pandemic-specific and has no UCC item. | Do not score UCC P with `PsyCapMean` from the saved pipeline. |
| The app does not collect C or P. It collects 20 reverse-scored draft behavior items and stores 0–100 construct scores. | Adopting the PDF instrument is a later application change. It is out of scope here. Do not remap the current construct scores into the two Pianda columns. |
| Expert CVI and pilot alpha are specified and not computed. | Record the indices before any claim that the UCC scales are ready for a model. |
| The target is same-occasion EVS self-report. E1–E4 is a different self-report. | Keep E out of predictors. Do not describe the readiness index as a probability of employment. |
| Verbal bands and the 2.60 context flag are institution-defined. | Do not turn those bands into model-quality or risk thresholds. A later display still needs an explicit rounding rule at the 1.80 / 1.81 style boundaries. This review does not set that rule. |
| The PDFs are not in git. | Decide whether to commit the two PDFs so this item reading can be repeated from the repository. This review does not commit them. |

The linear regression candidate is not production-ready. The dropout candidate is not production-ready. Nothing in this mapping clears either artifact for deployment.
