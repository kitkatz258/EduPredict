# EduPredict M43A — Approved 60-Item Questionnaire Visibility and Activation

**Scope:** local application/UI work only. **No ML model change or deployment.**
**Suggested working branch:** `feature/questionnaire-60-admin` created manually from the verified local application baseline that contains M41.1. Do not assume which branch or database the final-defense demo used.
**Status:** ready to give to Cursor Agent. Run only this milestone first.

## Fixed objective
An authorized administrator sees the **actual approved** `ucc-panel-revised-60-v2` instrument, and a new eligible student sees exactly **60 questions in six sections of ten** in the assessment. Historical `ucc-panel-approved-v1` 36-item submissions are unchanged. Diagnosis must explain why the 60 questions were missing from the **final-defense environment**; do not attribute it to any cause without evidence. The user has confirmed panel approval; do not mistake this for completed reliability or predictive validation.

## Required preflight (report before changing files)
1. Run `git status -sb`, `git branch --show-current`, `git log -3 --oneline`. Stop unless on `feature/questionnaire-60-admin` and the worktree is clean or all existing changes are accounted for. Agent must not create/switch branches.
2. Read `src/cursor/reference/approved-questionnaire/SystemQuestions.pdf` and `EduPredict_Scoring_and_Research_Basis_Updated.pdf` (adjust exact path based on actual tree), the M41/M41.1 work, all questionnaire migrations, seeds/catalogs, version/active selectors, student assessment and admin questionnaire Livewire/Blade components, controllers, services, authorization, and tests.
3. Compare **three independent states**: (a) code/canonical instrument definitions, (b) local database active/published question version and item counts, and (c) evidence of the defense deployment's exact commit, active version, and database state if accessible without altering the service. Never assume local, GitHub `main`, Render or `feature/render-deploy` share the same code.
4. Diagnose if source has 60 but DB active points to 36, seed never applied, version filtering fails, a front-end modal is showing only one step, the defense deployment is on an older commit, or another supported cause. Report proven causes separately from hypotheses. Do not claim remote inspection unless actually performed.

## Implementation after preflight
1. Make the **existing** v2 instrument the **chosen active/published questionnaire locally**, preserving all 60 exact approved statements, codes and six sections: A/S/B/C/P/E; each must contain questions 1..10.
2. If v2 is already defined correctly, **repair only the activation, retrieval or rendering defect**; do not duplicate 60 questions or create a third v2 record.
3. Use an idempotent, version-aware mechanism to add missing approved questions if needed. Never use an unconditional seed, `migrate:fresh`, database wipe, or live production modification. Any database writes must be restricted to authorized local disposable/test environments and carefully documented.
4. Confirm students see exactly 60 questions (10 each), with 1–5 responses, correct labels and order, save-draft/resume, validation, completion state, and section-mean scoring. Incomplete response is not Neutral. No automatic reverse scoring; no single all-60 score.
5. Preserve all older 36-item definitions and answer histories. An old submission must still render using its original version, not v2 question wording. Existing responses are immutable.
6. New response records should carry the correct active version identifier, and the admin list should clearly distinguish published/active/draft/archived without falsely marking psychometric validity.
7. Add idempotency tests, 60-item assertions, archived-history regression tests and role/authorization tests. Run existing applicable PHP tests and frontend build; for browser checks, use a local test account and capture actual evidence if tooling permits. Do not claim browser coverage without browser evidence.

## Acceptance checklist
- [ ] Exact source wording for all 60 questions compared to approved `SystemQuestions.pdf` (not generated or paraphrased).
- [ ] 6 sections x 10 items present; admin and student see consistent active-version data.
- [ ] New assessment uses `ucc-panel-revised-60-v2` and old results still use original v1.
- [ ] User can save, resume and complete the questionnaire; score validation works.
- [ ] No duplicate version records or non-idempotent import.
- [ ] Proven cause of missing defense questionnaire identified, or precisely listed as unverified when remote access unavailable.
- [ ] No modifications to live heuristic, FastAPI, ML artifacts, Render, Supabase or unrelated data.
- [ ] Exact tests/commands, code diff, screenshots where possible and local commit reported.

## Stop conditions
Stop before any step that would require switching branch, changing Render/Supabase, modifying professor-managed databases, destructive reseeding, editing answered historical questions, bypassing permissions, or inventing the approved questionnaire text.

## Agent response format
Summarize in simple language: **cause**, **what was fixed**, **how many questions are active**, **what students/admin see**, **tests and browser evidence**, **files changed**, **local commit id**, and **anything remaining unverified**. Local commit may be made when tests pass; no push/merge/deploy.
