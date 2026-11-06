# UCC scholarship semantics versus UCI `Scholarship holder` (M43 decision memo)

**Later decision:** D1 (2026-11-06) is the feasibility contract. Read `d1_academic_feature_definitions.md` and `ucc_feature_and_target_contract_v1.md`. This memo stays as the M43 inventory.

**Status:** documentation only. No FeatureBuilder change, no snapshot change, no profile-form change.  
**Date:** 2026-11-02  
**Branch:** `feature/m43-research-inference`  
**Purpose:** inventory local scholarship states and propose a refusal rule for unknown/missing values. Collapsing any local status into UCI `Scholarship holder` remains **pending human confirmation**.

UCI `Scholarship holder` on dataset id 697 is a **binary 0/1** with no missing values in the local file. EduPredict does not store that variable.

## 1. Locally modeled statuses

Configured in `config/edupredict.php` `profile.scholarship_statuses` and validated by `SocioeconomicProfileRequest`:

| Stored value | UI label | Present in UCI 0/1 codebook? |
| --- | --- | --- |
| `none` | No scholarship | No. A **proposed** map to `0` is unverified. |
| `partial` | Partial scholarship | No. A **proposed** map to `1` is unverified. |
| `full` | Full scholarship | No. Same. |
| `government` | Government scholarship | No. Same. |
| `private` | Private scholarship | No. Same. |

The five categories cannot be recovered from the UCI file. M25 already recorded that a collapse “every status other than `none` → 1” was implemented as an idea, not as a verified Portuguese equivalent (`ml/reports/ucc_dropout_feature_feasibility.md`, `next_training_decisions.md` D5).

There is no local stored enum value named `unknown`.

## 2. Unknown, draft, none, and verified awarded — they are not the same

| State | How it exists in the app | What FeatureBuilder emits today | What a new prediction snapshot stores |
| --- | --- | --- | --- |
| **No profile row** | Student never saved socioeconomic data | `scholarshipStatus = null`, `hasScholarship = false` (hard-coded; profile is not read, M37.1) | `scholarship_status` and `has_scholarship` forced to **null** and listed in `unavailable_inputs` (`PredictionRequester`) |
| **Draft profile** | `socioeconomic_profiles.is_draft = true`. Draft rules make `scholarship_status` nullable. | Same as above: household fields are not read | Same: null / unavailable. Not scored as “no scholarship.” |
| **Published `none`** | Saved, not draft, status `none` | Still null / false at build time; profile is not read for new predictions | Still null / unavailable on **new** snapshots |
| **Published awarded** (`partial`, `full`, `government`, `private`) | Saved, not draft | Still not read | Still null / unavailable |
| **Historic `placeholder-heuristic-v0` snapshot** | Older attempts may still contain a household status | Not rewritten | Left as stored; not used as a UCI 0/1 |

M37.1 decision: discontinued household fields are **not** treated as poverty, as no scholarship, or as zero. Encrypted `socioeconomic_profiles.scholarship_status` remains on file and is not copied into a new FeatureSet.

`FeatureSet.hasScholarship` is a PHP `bool`, so the builder currently writes `false` rather than a nullable unknown. The requester then overwrites the snapshot key to `null`. That type mismatch is a local modeling detail. It is **not** a UCI missing-data code.

`has_scholarship` is therefore **not** a verified awarded-scholarship flag on current predictions.

## 3. Difference from UCI `Scholarship holder`

| | UCI | EduPredict now |
| --- | --- | --- |
| Coding | 0 or 1 only | Five labels, plus draft/missing/unavailable |
| Missing values | None in the research file | Common: no profile, draft, or M37.1-unavailable |
| Timing | Source “after published profile” analogue is not defined for UCC | Known, if ever used, only after a published non-draft profile |
| Construct | Portuguese scholarship-holder flag | Local self-reported category, encrypted, unused in v1 heuristic |

Writing `0` for a missing or draft status would invent “not a scholarship holder.” The UCI scholarship-only pipeline rejects missing predictors and does not impute. M42 `uci_scholarship_only_m42` is a weak three-class source baseline (`deployment_status: pending_mapping`).

## 4. Proposed binary rule (not implemented)

**Pending human confirmation.** Not applied in M43.

Proposed scoring eligibility, if a later milestone is ever allowed to derive a research-only binary **after** owner approval:

1. **Refuse** (do not score, do not impute) when:
   - there is no socioeconomic profile
   - the profile is draft
   - `scholarship_status` is null/blank
   - the snapshot lists `scholarship_status` / `has_scholarship` as unavailable
2. **Do not** convert those refusals to UCI `0` or to `false`.
3. Only a published, non-draft status would even be a *candidate* for collapse, and only after an owner states:
   - evidence that UCC “scholarship” matches the UCI scholarship-holder definition (or explicitly accepts that it does not, and retires the column)
   - whether `partial`, `government`, and `private` are all `1`
   - whether free tuition / UCC-wide aid (DECISIONS M37) is `1`, `0`, or out of scope
4. If D5 rejects the collapse, keep `uci_scholarship_only_m42` as a source-only exploratory baseline and do not engineer a student feature.

Required evidence before any collapse is accepted:

- written owner/registrar definition of “scholarship holder” at UCC
- confirmation that self-report on the encrypted profile is that definition
- a missing-data policy that can fail review (this memo’s refusal rule is the candidate)
- no use of draft or unavailable snapshot keys

## 5. What M43 does instead

The isolated research loader accepts only the source column `Scholarship holder` as integer 0 or 1 inside the audited UCI coding. UCC keys `scholarship_status` and `has_scholarship` are rejected. Unknown/missing is not coerced.

No student snapshot, profile form, or FeatureBuilder path was changed.
