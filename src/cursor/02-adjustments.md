# 02 – Adjustments

> Read `cursor/01-initial-project.md` for context.
> Read `cursor/rules/edupredict.mdc`.
> Inspect the current codebase before editing.
> Use the screenshots in `cursor/ui-reference/` as the visual direction.
> Do ONLY what is listed here.
> Run commands through Docker, e.g. `docker compose exec app ...`.
> Use additive migrations only. Do not run destructive database resets against preserved development data.
> After every milestone: run relevant tests/build checks, review `git diff`, then commit that milestone before starting the next one.

---

## What is wrong / what to change

### A. Roles, scope, access, and academic structure

1. **Faculty must be fully removed from the active product** – Keep only Student, Department Head, Dean, and Administrator as active roles. Remove Faculty from active selectors, navigation, filters, account creation, dashboards, demo accounts, and role-specific UI. Preserve legacy faculty-linked history safely if old rows exist.

2. **Department Head becomes the student-level reviewer** – Department Head may view individual student prediction results only within the authorized department/program scope.

3. **Dean is aggregate-only** – Dean must see college-level summaries/graphs only and must not access individual student names, IDs, rows, prediction details, exports, or drill-downs.

4. **Pilot/data-gathering scope is UCC CLAS only** – College of Liberal Arts and Sciences (CLAS), University of Caloocan City.

5. **CLAS programs** – Use:
   - BA Communication
   - Bachelor of Public Administration
   - Bachelor of Science in Computer Science
   - Bachelor of Science in Entertainment and Multimedia Computing
   - Bachelor of Science in Information Systems
   - Bachelor of Science in Information Technology
   - Bachelor of Science in Mathematics
   - Bachelor of Science in Psychology

6. **Academic hierarchy** – Preserve normalized College → Department → Program → Student relations. Do not hardcode department/program as strictly 1:1 if the schema supports one-to-many.

---

### B. Login, registration, shell, and navigation

7. **No extra guest landing page** – `/` should lead directly to the real sign-in experience for guests.

8. **Student login identifier** – Students sign in with Student Number + password, not school email.

9. **Controlled registration** – Student self-registration succeeds only if the student number exists in the admin-managed eligible/institution student list and has not already been claimed.

10. **Login redesign** – Use the supplied login reference as visual direction. Ignore the Figma role tabs. Include UCC/EduPredict identity, Student Number, Password, show/hide password, Remember me if already supported, Forgot password, Sign In, Create Account/Registration, concise privacy note, validation, responsive layout.

11. **About page** – Remove About from primary navigation and user-facing routes if it has no unique purpose. Keep Privacy.

12. **Student navigation must be simplified** – Student top navigation should contain only:
   - Dashboard
   - Assessment
   - History
   - Profile/Settings access
   - Logout

   Do not keep separate student top-level navigation items for My Grades, Questionnaire, Skills & Experience, or Career Matches.

13. **Student shell** – Use a clean sticky/fixed top navigation based on the references.

14. **Department Head / Dean / Admin shell** – Use a fixed left sidebar that stays visible while main content scrolls. Support responsive/mobile collapse.

15. **Icons** – Use a consistent icon set such as Remix Icons where compatible. Keep accessible labels for ambiguous/destructive actions.

16. **Global feedback foundation** – Add reusable loading and interaction feedback:
   - top progress or subtle navigation indicator
   - `wire:loading` / `wire:target`
   - busy/disabled submit buttons
   - reusable spinner/overlay for slower actions
   - prevent duplicate submits

17. **SweetAlert2 or equivalent** – Add reusable toast/confirmation behavior for success, errors, and destructive actions. Keep validation errors inline near fields.

---

### C. Unified Student Assessment flow

18. **Dashboard and Results are redundant** – Merge them into one student Dashboard / Results Overview.

19. **Questionnaire, Skills & Experience, and Grades are too fragmented** – Replace them as separate top-level pages with one unified **Assessment** page.

20. **Assessment steps** – One Assessment page should contain:
   1. Academic behavior + socioeconomic + employability questionnaire
   2. Technical skills + certifications + work experience
   3. Grades (optional update)
   4. Review / Run prediction

21. **Assessment is resumable** – Save/Resume progress and allow the student to update only the section they want without restarting everything.

22. **Grades are optional on later attempts** – If the student updates only questionnaire or experience data, allow using the latest confirmed grades on file.

23. **Grades step choice when grades exist** – Show:
   - Use latest confirmed grades
   - Update grades

24. **No grades ever submitted** – Do not fabricate academic data. Use existing missing-data/lower-confidence behavior and explain the limitation.

25. **Immutable prediction snapshot** – Each prediction must retain the exact questionnaire version/answers snapshot, skills/experience snapshot, grade report/version used, and model version.

26. **Assessment progress indicator** – Replace misleading “profile completeness improves prediction accuracy” wording with a workflow/progress concept.

---

### D. Questionnaire section inside Assessment

27. **Questionnaire UI is cluttered** – Redesign using the reference: clear title/instructions, one logical section at a time, progress, answered count, category chips/tabs, clear Likert/radio cards, selected state, Save/Continue, Back, responsive layout.

28. **Questionnaire groups** – Prepare for:
   - Academic behavior
   - Socioeconomic factors
   - Employability-related self-assessment

29. **Employability themes are not final questions** – Prepare configurable support for mental alertness, self-confidence, ability to present ideas, communication skills, manner of speaking, and student performance rating. Do not invent final questionnaire items or validated scoring yet.

30. **Research basis required** – Final items must be adopted/adapted from cited research or approved sources. Keep questionnaire definitions versionable.

31. **No arbitrary weighting** – Do not hardcode equal or invented percentages for constructs. Final contribution belongs to approved methodology/trained models later.

32. **Socioeconomic privacy** – Preserve encrypted storage and do not expose raw private socioeconomic answers to Department Heads, Dean, or Admin.

---

### E. Skills, certifications, and experience inside Assessment

33. **Freeform multiline storage is too weak** – Replace active UI with structured records and Add/View/Edit/Delete or Archive actions.

34. **Technical Skills** – Structured entries with skill name and optional category/proficiency/evidence only if retained by requirements.

35. **Certifications** – Modal/form fields: certificate/title, issuer/provider, issue date/year, optional expiry, optional credential/reference, optional description.

36. **Work Experience** – Modal/form fields: organization/employer, role/title, experience type, start date, end date or ongoing, optional responsibilities/description.

37. **OJT/Internship** – Treat as a Work Experience type, not a separate top-level category.

38. **Projects** – Remove Projects from the active Assessment flow. Preserve legacy data safely rather than blindly deleting it.

39. **Modal UX** – Labeled fields, validation, cancel/close, save, keyboard support, loading feedback.

---

### F. Grades section inside Assessment

40. **Current Grades UI is cluttered** – Rebuild visually using the supplied grade references.

41. **Do not copy AI extraction wording** – Current extraction uses portal parsing, PDF text extraction, OCR/image extraction, and manual review. Do not claim AI extracts grades.

42. **Grade modes** – Present:
   - Upload Document
   - Paste from UCC Portal
   - Manual Entry

43. **Upload Document mode** – Large drag/drop area, click-to-browse, accepted types, chosen filename, parsing state, errors, review-before-save copy.

44. **Parsed review table** – Show AY/semester, subject code, subject name, units, final grade, remarks, edit-row action, low-confidence/needs-review marker if available, Confirm & Save Grades.

45. **Manual Entry mode** – AY selector, semester selector, subject rows, code, name, units, final grade, add/remove row, Save Grades.

46. **Paste from Portal mode** – Large paste area, instructions, Parse button, same review table, Confirm & Save.

47. **Professor names in portal paste** – Exclude instructor/professor names from saved subject descriptions and student-facing records.

48. **Preserve OCR** – Keep the current OCR/PDF extraction pipeline and mandatory student review/confirmation.

49. **INC correction** – Preserve `INC`, numeric grade null/blank, exclude from GWA numerator/denominator, do not automatically count as failed, flag provisional GWA when incomplete subjects exist, allow later replacement/recalculation.

50. **Historical grade reports** – Keep View action with read-only modal/detail. Previous prediction snapshots must not change when a grade report is later updated.

---

### G. Student Dashboard / Results

51. **One Dashboard / Results Overview** – Use the supplied student dashboard references. Include greeting, program/year, employability, dropout risk, confidence, program-fit/disengagement, top factors, latest assessment date, and clear next actions.

52. **Career Matches belong inside Dashboard** – Remove Career Matches as a separate top-level student page. Show ranked career cards/summary inside Dashboard and use a modal for details if needed.

53. **Career visual direction** – Use prominent compatibility score, PSOC code/category, Why this match, matched/missing skill chips, optional detail modal, and compatibility legend where useful.

54. **Career ranking remains deterministic/rule-based** – AI may phrase explanations only; do not claim AI decides the career match.

55. **Clear student actions** – Prefer Update Assessment, Update Grades, Run/Request New Prediction, View Career Details. Avoid confusing “Retake” wording where the action is simply editing data.

56. **Placeholder disclosure** – Do not present heuristic output as validated trained ML probability.

---

### H. Dedicated Student History

57. **History is its own page** – Do not place long prediction history below Dashboard/Grades.

58. **History list** – Include date/time, employability, dropout risk, confidence, model version, status, View.

59. **View History** – Open read-only modal/detail showing the saved attempt snapshot: questionnaire version/answers where appropriate to the owning student, skills/experience snapshot, grade version used, results, factors, shift indicator, and stored references.

60. **Old attempts without complete snapshot** – Show “Not available for this attempt” instead of substituting current mutable data.

61. **Avoid destructive history deletion** – Prefer immutable retention/archive unless an approved policy says otherwise.

---

### I. Department Head

62. **Dashboard should stay analytics-focused** – Do not put the full student list inside the Department Head Dashboard.

63. **Dedicated Students page** – Create/keep a separate Department Head Students page with:
   - high-risk banner
   - totals for high/moderate/low/program concern
   - search
   - risk filters
   - program filter
   - year filters: 1st, 2nd, 3rd, 4th Year
   - student rows
   - risk label
   - shift/engagement indicators
   - employability score
   - View action

64. **Student View should be a modal** – Do not redirect to a separate student detail page. Modal shows authorized status/report details.

65. **Remove Faculty-related columns/filters** – No Faculty Adviser column or Faculty filter.

66. **Department Head detail modal** – Show employability, dropout classification, confidence, contributing factors, program-fit/disengagement context, approved institutional interventions, review/note/status. Do not expose raw private questionnaire or encrypted socioeconomic answers.

67. **Institutional interventions** – Select only from approved predefined interventions. AI may rephrase/explain selected items but cannot invent actions.

68. **AI unavailable** – Use deterministic stored fallback text.

---

### J. Dean

69. **Dean is aggregate-only** – CLAS dashboard with department/program/year/period filters and aggregate charts/tables only.

70. **Dean must not have** student search, student names/IDs, individual rows, individual prediction detail, per-student export, or student drill-down.

71. **Enforce backend restrictions** – Do not rely only on hidden links.

---

### K. Admin

72. **Admin Dashboard is analytics-focused** – Remove the visible demo/student table from the Dashboard. It may remain in code/layout for reference but must not render in the active view.

73. **Users page is the main user-management list** – Support role filters:
   - All
   - Student
   - Department Head
   - Dean
   - Administrator

   Keep search/status filters as useful.

74. **User Add/Edit uses modals** – Replace always-visible create/edit forms with Add User/Edit User modals.

75. **Eligible student list** – Keep the admin-managed institutional/eligible-student list that controls student-number registration. Present it as a dedicated list/tab/view, not a Dashboard table.

76. **Academic Structure page** – Use list/table + filters/search. Add/Edit College/Department/Program through modals instead of always-visible forms.

77. **Questionnaire admin page** – Use list + filters/search. Add/Edit questionnaire/version/question records through modals. Preserve versioning for historical attempts.

78. **PSOC Occupations page** – Use list + search/filter. Add/Edit/View through modals instead of always-visible forms.

79. **Interventions page** – Use list + search/filter. Add/Edit/View approved interventions through modals instead of always-visible forms.

80. **Rename Audit Log** – Visible navigation/page label should become **Activity Log** (or **System Activity** if the contents fit better). Keep the audit backend/data intact.

81. **Deletion Requests are account deletion requests** – Keep a separate **Deletion Requests** page. The current feature refers to account deletion requests; approval currently deactivates login while preserving prediction history unless policy changes later.

82. **Deletion Requests UI** – Dedicated list with segmented/toggle-style status control inspired by the Grades mode switch, e.g. Pending / Approved / Rejected / All. Review opens a modal.

83. **Admin navigation** – Keep useful pages such as Dashboard, Users, Academic Structure, Questionnaire, PSOC Occupations, Interventions, Activity Log, Deletion Requests, Settings/Profile, Logout.

---

### L. Loading, feedback, accessibility, responsiveness

84. **Loading feedback** – Add to sign in/register, Assessment saves, modal saves, grade upload/OCR/parser, Confirm & Save, prediction request, filters, and admin/staff actions.

85. **SweetAlert2** – Reusable toast/confirm layer for success, errors, archive/delete/deactivate confirmations. Keep inline validation.

86. **Accessibility** – Modal focus management, keyboard support, Escape where appropriate, accessible labels/tooltips, mobile-safe scrolling.

87. **Responsive UI** – Tables/cards/charts must have usable small-screen behavior and clear empty states.

---

### M. Deployment-readiness boundaries

88. **Do not add FastAPI/fourth container yet** – Final questionnaire/features are not stable enough.

89. **Preserve predictor abstraction** – Keep `PredictorInterface`, FeatureBuilder, placeholder heuristic, and later HTTP/FastAPI integration path.

90. **Keep PostgreSQL/Render compatibility in mind** – Avoid new MySQL/MariaDB-specific SQL/schema assumptions. Actual DB migration is a later dedicated milestone.

91. **Do not train final models yet** – Model comparison/training happens after inputs/features are finalized.

---

## Milestones / commit plan

### M13 – Roles, CLAS scope, and authorization
Already completed. Do not redo it. Apply only the follow-up Faculty cleanup required in M14 where it affects active shell/navigation/auth/product role presentation.

---

### M14 – Login, registration, shell, Faculty cleanup, and global feedback
Implement:
- fully remove Faculty from active product UI/code paths while preserving legacy data safely
- direct sign-in landing
- student-number login
- controlled student registration
- improved login UI
- Student nav: Dashboard / Assessment / History / Profile-Settings / Logout only
- staff/admin fixed sidebar
- remove About, retain Privacy
- icon system
- reusable loading/progress states
- reusable SweetAlert2/equivalent toast/confirmation system

Checks:
- auth/registration tests
- Faculty cannot appear or be created as active role
- legacy Faculty-linked history remains readable
- student-number login works
- guest `/` reaches sign-in
- Department Head/Dean authorization regression tests
- frontend build

Commit:
`M14: Redesign authentication and navigation shell`

---

### M15 – Unified Assessment shell and questionnaire
Implement:
- one Assessment page replacing separate Questionnaire / Skills & Experience / Grades student nav pages
- Assessment stepper/progress/save-resume
- questionnaire grouped/step-based UI
- academic/socioeconomic/employability sections
- configurable/versioned question definitions
- no invented final items/weights

Commit:
`M15: Build unified assessment workflow`

---

### M16 – Structured Assessment records
Implement:
- Technical Skills records/modals
- Certifications records/modals
- Work Experience records/modals
- OJT/Internship as experience type
- remove Projects from active Assessment UI
- legacy data compatibility/migration where necessary

Commit:
`M16: Structure skills, certifications, and work experience`

---

### M17 – Grades section and INC correction
Implement:
- Upload / Paste / Manual modes inside Assessment
- reference-inspired upload/review/manual UI
- professor-name removal from portal parsing
- preserve OCR, no AI extraction wording
- INC correction
- Use latest confirmed grades path
- immutable grade-version snapshot behavior

Commit:
`M17: Redesign grades workflow and correct INC handling`

---

### M18 – Student Dashboard and History
Implement:
- merge redundant Dashboard/Results
- integrate Career Matches inside Dashboard
- Career Match detail modal if useful
- dedicated History page
- historical snapshot modal/detail
- clear actions and model disclosure

Commit:
`M18: Consolidate student dashboard and history`

---

### M19 – Department Head monitoring and interventions
Implement:
- analytics-focused dashboard
- separate Students page
- year/risk/program filters
- View student status in modal, no detail-page redirect
- remove Faculty adviser references
- approved intervention catalog + AI rephrase only + deterministic fallback

Commit:
`M19: Refine department monitoring and intervention review`

---

### M20 – Dean and Admin management redesign
Implement:
- Dean aggregate-only CLAS dashboard
- Admin dashboard without visible demo/student table
- Users role filters + Add/Edit modals
- eligible-student list
- Academic Structure filters + Add/Edit modals
- Questionnaire filters + Add/Edit modals
- PSOC Occupations filters + Add/Edit/View modals
- Interventions filters + Add/Edit/View modals
- rename visible Audit Log to Activity Log/System Activity
- separate Deletion Requests page with segmented status filter and review modal

Commit:
`M20: Update dean analytics and admin management`

---

### M21 – UI polish, loading, accessibility, regression
Implement:
- final loading-state coverage
- duplicate-submit protection
- SweetAlert consistency
- responsive fixes
- modal accessibility
- empty/error/success states
- spacing/typography/icon consistency
- remove stale Faculty/About/old student-nav references
- docs updates

Checks:
- full Laravel test suite
- frontend build
- route smoke tests
- role-by-role manual walkthrough
- Assessment flow
- grade upload/OCR
- History
- Department Head Students modal
- Dean aggregate-only
- Admin management pages

Commit:
`M21: Polish UX, accessibility, and regression coverage`

---

## Do not touch

- Do not train final ML models yet.
- Do not create the FastAPI/fourth container yet.
- Do not remove/bypass `PredictorInterface`.
- Do not present `placeholder-heuristic-v0` as trained ML.
- Do not remove OCR/PDF extraction that works.
- Do not add AI grade extraction because the UI reference mentions AI.
- Do not remove student confirmation before parsed grades are saved.
- Do not regenerate/change `APP_KEY`.
- Do not expose raw encrypted socioeconomic responses to Department Heads, Deans, or Admin.
- Do not let Dean access individual student information.
- Do not reintroduce Faculty as an active role.
- Do not expand pilot/data-gathering scope outside CLAS.
- Do not hardcode final questionnaire items or arbitrary weights.
- Do not delete legacy records merely because a role/feature is removed from active UI.
- Do not run `migrate:fresh` on preserved project data.
- Do not replace historical prediction snapshots with current mutable profile data.
- Do not remove Privacy.
- Do not re-add About to primary navigation.
- Do not restore separate student top-level pages for Grades, Questionnaire, Skills & Experience, or Career Matches after consolidation into Assessment/Dashboard.
- Do not add new MySQL/MariaDB-specific implementation that makes later PostgreSQL migration harder.

---

## Done when

- Active roles are Student, Department Head, Dean, Administrator only.
- Faculty is absent from active UI/flows while legacy history remains safe.
- CLAS + eight approved programs are reflected in scope.
- Dean is aggregate-only in backend and UI.
- Department Head Students is a separate page; student status opens in a modal.
- Guest users land directly on polished sign-in.
- Students sign in by student number with gated registration.
- Student nav contains only Dashboard, Assessment, History, Profile/Settings, Logout.
- Assessment is one resumable page containing questionnaire, structured skills/certifications/work experience, and optional grade update.
- Career Matches is inside Dashboard, not a separate top-level student page.
- Grades support Upload / Paste / Manual, with no false AI extraction claim.
- Professor names are removed from portal-pasted grade descriptions.
- INC is nonnumeric, excluded from GWA, and incomplete rather than failed.
- Students can update Assessment without re-uploading grades and the latest confirmed grade version is snapshotted.
- History is its own page with immutable historical snapshots.
- Department Head has scoped student filtering including year level and modal status review.
- Admin Dashboard has no rendered demo/student table.
- Users, Academic Structure, Questionnaire, PSOC Occupations, Interventions, Activity Log, and Deletion Requests are separate management pages with filters/modals as specified.
- Deletion Requests are clearly presented as account deletion requests.
- Loading states, SweetAlert/confirmation feedback, accessibility, and responsive behavior are present.
- Full test suite and frontend build pass.
- Each milestone has its own clean commit.
