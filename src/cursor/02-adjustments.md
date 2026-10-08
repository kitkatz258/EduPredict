# 02 – Adjustments

> Read `cursor/01-initial-project.md` for context.
> Read `cursor/rules/edupredict.mdc`.
> Inspect the current codebase before editing.
> Use the screenshots in `cursor/ui-reference/` as the visual direction.
> Do ONLY what is listed here.
> Run commands through Docker, e.g. `docker compose exec app ...`.
> Use additive migrations only. Do not run destructive database resets against preserved development data.
> After every milestone: run the relevant tests/build checks, review `git diff`, then commit that milestone before starting the next one.

---

## What is wrong / what to change

### A. Roles, scope, access, and academic structure

1. **Faculty role is no longer needed** – Faculty currently overlaps with Department Head responsibilities. – Remove Faculty from the active operational flow and keep only:
   - Student
   - Department Head
   - Dean
   - Administrator

   Preserve legacy data safely if Faculty records already exist. Do not blindly delete historical users, reviews, or references.

2. **Department Head becomes the student-level reviewer** – Department Head should handle student-level monitoring previously duplicated under Faculty. – Department Head may view student-level prediction information only within their authorized department/program scope.

3. **Dean access is too broad if individual students are visible** – Dean should not inspect specific students. – Dean must see aggregated college-level analytics only:
   - department/program summaries
   - year-level summaries
   - employability distribution
   - dropout-risk distribution
   - cohort trends
   - other aggregate graphs/tables

   Do not expose student names, IDs, individual predictions, raw questionnaire answers, or per-student drill-down to the Dean.

4. **Scope is now CLAS only** – Current structure may still imply institution-wide pilot scope. – Limit testing/data-gathering scope to the **College of Liberal Arts and Sciences (CLAS), University of Caloocan City**.

5. **CLAS program list** – Update academic reference/demo data to the following programs:
   - BA Communication
   - Bachelor of Public Administration
   - Bachelor of Science in Computer Science
   - Bachelor of Science in Entertainment and Multimedia Computing
   - Bachelor of Science in Information Systems
   - Bachelor of Science in Information Technology
   - Bachelor of Science in Mathematics
   - Bachelor of Science in Psychology

6. **Academic hierarchy** – Keep the schema capable of:
   - College
   - Department
   - Program
   - Student

   A department may have one program, but do not hardcode a strict 1:1 relationship if the existing schema already supports one-to-many.

---

### B. Login, registration, navigation, and general shell

7. **Guest landing page is unnecessary** – The app currently has an extra landing-style step before login. – Make the guest entry route go directly to the real sign-in page.

8. **Student sign-in should not depend on school email** – UCC students in this system do not require school email login. – Students should sign in using **student number** plus password.

9. **Student self-registration must remain controlled** – A student should not be able to create an arbitrary account. – Registration succeeds only when the entered student number exists in the admin-maintained institutional/eligible-student list and is not already claimed.

10. **Login page is too plain** – Redesign it using the supplied login reference:
    - centered UCC/EduPredict identity
    - polished card
    - student number field
    - password field
    - show/hide password
    - forgot password
    - sign in
    - create account/student registration
    - concise privacy/data-protection note
    - responsive layout

    Ignore the Figma-only role tabs shown above the reference login card; do not add role-switch tabs.

11. **About page is not needed** – Remove About from primary navigation and routes if it has no unique function. – Keep **Privacy** as the dedicated privacy/data-protection page and keep consent where registration/data collection requires it.

12. **Navigation shell is inconsistent** – Student references use a clean top navigation while staff/admin references use a fixed sidebar. – Follow the provided references:
    - Student: clean sticky/fixed top navigation
    - Department Head/Admin/Dean: fixed left sidebar
    - content area scrolls independently
    - mobile layout must collapse accessibly

13. **Sidebar currently scrolls away** – Staff/Admin sidebar must remain visible while long pages scroll. – Use a viewport-height fixed/sticky sidebar with its own overflow behavior where needed.

14. **Icons are inconsistent / missing** – Add a consistent icon set such as Remix Icons for navigation and actions, but keep text labels or accessible labels for clarity.

---

### C. Student workflow and page structure

15. **Dashboard and Results are redundant** – They currently show overlapping student prediction information. – Merge into one main **Dashboard / Results Overview** page based on the supplied student dashboard references.

16. **History is buried inside other pages** – Move historical attempts into a dedicated **History** page.

17. **Profile completeness is being used like a prediction-quality claim** – Replace this with a clearer workflow/progress concept. – Use a proper assessment progress indicator rather than wording that implies completeness itself guarantees prediction accuracy.

18. **Student workflow should be resumable and not force re-entry** – Use this flow:
    1. Questionnaire
    2. Skills & Experience
    3. Grades (optional update)
    4. Results

19. **Grades should not be mandatory for every new assessment attempt** – If the student only updates questionnaire and/or skills/experience, allow them to continue using the **latest confirmed grades on file**.

20. **When grades already exist** – The Grades step should clearly offer:
    - **Use latest confirmed grades**
    - **Update grades**

21. **Prediction snapshot integrity** – Every generated prediction must record the exact versions/snapshots used:
    - questionnaire response/version
    - skills/experience state
    - confirmed grade report/version
    - model version

    Historical attempts must not silently change when current profile data changes.

22. **If no confirmed grades exist at all** – Do not fabricate grades. Use existing missing-data / lower-confidence behavior and make the limitation visible.

---

### D. Questionnaire redesign

23. **Questionnaire is visually cluttered** – Redesign it to match the questionnaire UI reference:
    - clear page title and instructions
    - one logical section at a time
    - progress indicator
    - answered count
    - clear section tabs/chips
    - consistent Likert/radio cards
    - obvious selected state
    - Save/Continue
    - Back where appropriate
    - responsive layout

24. **Questionnaire structure is changing** – Prepare the questionnaire to support:
    - Academic behavior
    - Socioeconomic factors
    - Employability-related self-assessment

25. **Employability constructs are not final questions yet** – Prepare configurable support for themes such as:
    - mental alertness
    - self-confidence
    - ability to present ideas
    - communication skills
    - manner of speaking
    - student performance rating

    Do not invent final survey items, scoring weights, or validated claims yet.

26. **Research basis is required** – The final questions must come from credited/adopted/adapted references or an approved research basis. Keep questionnaire definitions versionable so final questions can be inserted later without redesigning the UI.

27. **Questionnaire constructs must not automatically have equal weight** – Do not hardcode arbitrary percentages. Leave feature weighting to the approved methodology/trained model later.

28. **Socioeconomic privacy** – Preserve encrypted storage and ownership rules. Department Heads may use resulting prediction factors, but must not see the student’s raw encrypted socioeconomic answers.

---

### E. Skills & Experience redesign

29. **Current multiline textareas are too unstructured** – Replace the freeform list style with structured cards/tables and Add/View/Edit/Delete or Archive actions.

30. **Technical Skills** – Use structured entries with fields such as:
    - skill name
    - optional category
    - optional proficiency/evidence only if retained by project requirements

31. **Certifications** – Use a modal or structured form with fields such as:
    - certificate/title
    - issuing organization/provider
    - issue date or year
    - optional expiry
    - optional credential/reference
    - optional description

32. **Work Experience** – Use a modal or structured form with:
    - employer/organization
    - role/title
    - experience type
    - start date
    - end date or ongoing
    - optional description/responsibilities

33. **OJT is not separate anymore** – Include OJT/Internship as one Work Experience type.

34. **Projects are not part of the revised Skills & Experience flow** – Remove Projects from this section/UI. Preserve legacy data safely if it already exists rather than destroying it.

35. **Modal behavior** – Add/Edit forms should open as accessible modals or equivalent structured overlays:
    - labeled fields
    - validation
    - cancel/close
    - save
    - keyboard accessibility
    - loading feedback

---

### F. Grades workflow redesign

36. **Current Grades page is cluttered and upload controls are unclear** – Rebuild the page visually using the new grade-upload references.

37. **Do not copy the AI wording in the reference** – The reference says grades are extracted “using AI,” but EduPredict currently uses:
    - portal/table parsing
    - PDF text extraction where available
    - OCR/image extraction
    - manual review

    Do **not** add AI extraction claims to the UI.

38. **Grade entry modes** – Present clearly:
    - Upload Document
    - Paste from UCC Portal
    - Manual Entry

    The reference screenshots show Upload Document and Manual Entry; keep Paste from Portal as an additional clearly designed mode because it already exists and is required.

39. **Upload Document mode** – Match the visual direction:
    - large drag-and-drop area
    - click-to-browse
    - accepted types shown
    - selected filename
    - upload/parsing state
    - clear errors
    - review-before-save explanation

40. **Parsed document review** – After parsing:
    - show AY / semester
    - subject code
    - subject name
    - units
    - final grade
    - remarks
    - row edit action
    - low-confidence/needs-review indicators if available from OCR/parser
    - Confirm & Save Grades button

41. **Manual Entry mode** – Match the reference:
    - AY selector
    - Semester selector
    - subject rows
    - subject code
    - subject name
    - units
    - final grade
    - add/remove row
    - Save Grades

42. **Paste from UCC Portal mode** – Keep it but redesign it with the same visual quality as Upload/Manual:
    - large paste area
    - clear instructions
    - Parse button
    - parsed review table identical to the document-review table
    - explicit Confirm & Save

43. **Portal parser currently includes professor names** – Update parsing so instructor/professor names are excluded from the saved subject description and do not appear in student-facing records.

44. **Do not remove existing OCR capability** – Preserve the existing extraction pipeline and student review/confirmation requirement.

45. **INC handling is wrong if treated numerically** – Change INC rules:
    - preserve literal `INC`
    - numeric grade remains null/blank
    - exclude INC from GWA numerator and denominator
    - do not count INC as failed automatically
    - mark GWA as provisional when incomplete subjects exist
    - allow later actual-grade replacement/recalculation

46. **Grade history list** – Historical grade reports should remain accessible from History or the Grades page using a clear View action.

47. **View action** – Use `View` rather than `Open`; an eye icon may accompany it. Show a read-only modal/detail view with the saved term and subject rows.

48. **Do not make successful existing grade reports mutable in a way that changes old predictions** – Previous predictions must retain the original grade snapshot they used.

---

### G. Student Dashboard / Results redesign

49. **Results presentation is too dense and repetitive** – Redesign using the supplied student dashboard references:
    - greeting / latest snapshot
    - program/year metadata
    - employability score card
    - dropout-risk classification
    - confidence indicator where applicable
    - program-shift / engagement indicator
    - top contributing factors
    - concise recommended next steps
    - career-matches link/summary
    - latest assessment date

50. **Do not imply heuristic percentages are validated ML probabilities** – While placeholder mode is active, keep clear model disclosure and avoid pseudo-precision.

51. **Confusing buttons** – Replace unclear wording like “Retake Questionnaire” when the real action is simply updating part of an assessment. Use clearer actions such as:
    - Update Questionnaire
    - Update Skills & Experience
    - Update Grades
    - Run New Prediction / Request New Prediction
    - View Career Matches

52. **Recommended institutional actions** – Students should not receive internally sensitive institutional intervention workflow details unless explicitly intended. Department Head is the main reviewer of approved interventions.

---

### H. Career Matches redesign

53. **Current Career Matches page is functional but visually weak** – Restyle using the supplied career-matches reference:
    - ranked cards
    - prominent compatibility score
    - PSOC code/category
    - clearer “Why this match?” explanation area
    - matched/missing skill chips
    - pathway link/detail action
    - compatibility-scale legend

54. **Do not claim AI decides career matches** – Career ranking remains deterministic/rule-based unless the approved architecture changes later. AI may only phrase explanations.

55. **Do not use Projects as a career-match input after Projects are removed from the profile flow unless legacy handling is explicitly preserved** – Update FeatureBuilder/matching logic carefully.

---

### I. Department Head redesign

56. **Department Head dashboard needs better hierarchy** – Restyle using the supplied Department Risk Monitoring reference:
    - total students
    - average employability
    - high-risk count
    - program-fit concerns
    - dropout-risk distribution
    - employability distribution
    - cohort trend
    - risk trend by semester
    - program/year filters

57. **Department student list** – Redesign using the reference:
    - high-risk alert banner
    - total/high/moderate/low/program-concern summary
    - search
    - risk filters
    - program filter
    - year filter
    - student rows
    - risk label
    - shift/engagement indicators
    - employability score
    - View action

58. **Remove Faculty Adviser column/filter references** – Faculty is no longer an active role.

59. **Department Head student detail** – Show only authorized student-level outputs:
    - employability
    - dropout classification
    - confidence
    - contributing factors
    - program-fit/disengagement context
    - approved institutional intervention suggestions
    - review/note/status

    Do not show raw private questionnaire answers or encrypted socioeconomic responses.

60. **Institutional intervention catalog** – Use only a predefined institution-approved set of interventions. AI may explain/rephrase selected approved interventions but may not invent new actions.

61. **AI unavailability** – Use deterministic stored **fallback** text for the selected intervention when the AI API is unavailable.

---

### J. Dean redesign

62. **Dean should be aggregate-only** – Build/retain a dashboard similar to the analytics references but scoped to CLAS.

63. **Dean filters may include**:
    - department
    - program
    - year level
    - date/academic period

64. **Dean must not have**:
    - student search
    - student names/IDs
    - individual student rows
    - individual prediction detail
    - per-student export
    - student drill-down

65. **Apply access restriction in backend queries/policies too** – Do not rely only on hiding links.

---

### K. Admin redesign

66. **Admin dashboard/user management should follow the supplied visual direction** – Restyle the analytics and user-management pages consistently with the references.

67. **Admin user management must show only active revised roles**:
    - Student
    - Department Head
    - Dean
    - Administrator

68. **Admin manages eligible student list** – Maintain the list that controls student self-registration by student number.

69. **Academic Structure page** – Admin manages CLAS departments/programs and assignments using the existing normalized models where possible.

70. **Questionnaire administration** – Keep configuration/versioning support for later insertion of finalized approved questions. Do not let Admin silently alter questions tied to historical prediction snapshots without versioning.

---

### L. Dedicated History page

71. **History is scattered across pages** – Create one dedicated History page for the student.

72. **History list should include**:
    - requested/generated date
    - employability result
    - dropout-risk label
    - confidence
    - model version
    - status
    - View action

73. **View History modal/detail** – Show the saved historical snapshot used for that attempt:
    - questionnaire snapshot/version
    - skills/experience snapshot
    - grade report/version used
    - employability result
    - dropout result
    - contributing factors
    - program-shift indicator
    - career/intervention result references where stored

74. **If old attempts lack complete snapshots** – Display “Not available for this attempt” rather than showing current mutable data.

75. **Avoid destructive prediction-history deletion** unless an approved retention rule explicitly requires it. Prefer immutable history / archival.

---

### M. Loading, feedback, accessibility, responsiveness

76. **Slow or silent actions are confusing** – Add loading feedback to:
    - sign in
    - register
    - questionnaire save/continue
    - skills modal saves
    - grade upload
    - OCR/parser processing
    - portal parse
    - Confirm & Save Grades
    - prediction request
    - history filtering
    - admin/staff filters

77. **Livewire actions** – Use targeted `wire:loading` / disabled state to prevent duplicate submissions.

78. **Forms and modals** – Add visible validation, focus management, keyboard support, Escape-to-close where appropriate, and mobile-safe scrolling.

79. **Tables/cards/charts** – Ensure responsive behavior and readable empty states.

---

### N. Deployment-readiness boundaries

80. **Do not create the final ML/FastAPI container yet in this adjustment pass** – The final questionnaire/features are not yet stable.

81. **Keep current predictor abstraction intact** – Preserve `PredictorInterface`, FeatureBuilder, placeholder heuristic, and the ability to add an HTTP/FastAPI predictor later.

82. **Keep implementation compatible with later PostgreSQL migration and Render deployment** – Avoid introducing new MySQL-specific SQL or schema assumptions during these changes.

83. **Do not train the final model in this milestone set** – Dataset comparison/training (e.g. Random Forest, Logistic Regression, Gradient Boosting, etc.) will be handled separately after inputs/features are finalized.

---

## Milestones / commit plan

### M13 – Roles, CLAS scope, and authorization
Implement:
- remove Faculty from active role flow
- Department Head student-level reviewer
- Dean aggregate-only
- CLAS scope
- CLAS program list
- academic hierarchy scoping
- authorization/policy tests

Checks:
- migrations succeed
- role/policy tests pass
- Dean cannot access individual student endpoints
- Department Head cannot access out-of-scope students

Commit:
`M13: Align roles, CLAS scope, and access control`

---

### M14 – Login, registration, and app shell
Implement:
- direct sign-in landing
- student-number login
- controlled student registration
- improved login UI
- student top nav
- staff/admin fixed sidebar
- remove About from navigation
- retain Privacy
- icon system

Checks:
- student login/registration tests
- staff/admin login unchanged except role cleanup
- responsive navigation smoke test
- no broken guest routes

Commit:
`M14: Redesign authentication and navigation shell`

---

### M15 – Questionnaire workflow
Implement:
- grouped/step-based questionnaire
- progress indicator
- save/resume
- configurable/versioned questionnaire structure
- prepare academic/socioeconomic/employability sections
- no invented final questionnaire items/weights

Checks:
- questionnaire drafts save
- resume works
- versioning tests
- existing responses remain readable
- privacy/encryption behavior preserved

Commit:
`M15: Restructure questionnaire workflow and versioning`

---

### M16 – Skills and Experience
Implement:
- structured Technical Skills
- structured Certifications
- structured Work Experience
- OJT/Internship as experience type
- remove Projects from active UI
- Add/Edit/View/Delete or Archive modals
- data migration/compatibility for legacy fields where necessary

Checks:
- ownership tests
- validation tests
- modal CRUD works
- legacy data is not silently lost
- FeatureBuilder remains functional

Commit:
`M16: Structure skills, certifications, and work experience`

---

### M17 – Grades UX and INC correction
Implement:
- Upload Document UI
- Paste from Portal UI
- Manual Entry UI
- parsed review table
- edit/confirm flow
- remove professor names from portal parsing
- preserve OCR
- no AI-extraction wording
- INC null/excluded/provisional behavior
- “Use latest confirmed grades” path
- immutable grade snapshot behavior

Checks:
- parser tests
- OCR sample tests
- manual entry tests
- INC/GWA tests
- update-questionnaire-without-new-grades test
- latest confirmed grade reuse test
- frontend build

Commit:
`M17: Redesign grades workflow and correct INC handling`

---

### M18 – Student Results, History, and Career Matches
Implement:
- merge redundant Dashboard/Results
- redesigned result overview
- dedicated History page
- historical snapshot View modal/detail
- career-match visual redesign
- clearer actions
- preserve placeholder-model disclosure

Checks:
- history uses historical snapshot, not current profile
- old incomplete history handled honestly
- cooldown behavior preserved
- career matching tests remain valid
- student end-to-end assessment flow works

Commit:
`M18: Consolidate student results, history, and career views`

---

### M19 – Department Head monitoring and interventions
Implement:
- redesigned Department Head dashboard
- redesigned scoped student list
- remove faculty-related columns/filters
- student result detail
- approved intervention catalog integration
- AI explanation/rephrase only
- deterministic fallback if AI unavailable

Checks:
- department scoping tests
- raw private questionnaire answers not exposed
- intervention selection stays within approved catalog
- AI failure uses fallback
- audit/history fields preserved

Commit:
`M19: Refine department monitoring and intervention review`

---

### M20 – Dean and Admin dashboards
Implement:
- Dean aggregate-only CLAS dashboard
- no student drill-down
- Admin dashboard visual redesign
- Admin user management revised to four roles
- eligible-student list
- academic structure consistency
- questionnaire configuration/version support

Checks:
- Dean leakage tests
- Admin CRUD/role tests
- CLAS filters work
- user counts/role filters updated
- exports do not leak student-level Dean data

Commit:
`M20: Update dean analytics and admin management`

---

### M21 – UI polish, loading states, accessibility, regression
Implement:
- loading states
- disabled duplicate-submit states
- mobile/responsive fixes
- modal accessibility
- empty/error/success states
- spacing/typography consistency
- final icon pass
- remove stale Faculty/About links/text
- documentation updates

Checks:
- full Laravel test suite
- `npm run build`
- route smoke test
- role-by-role manual walkthrough
- student full flow
- uploads/OCR
- history
- Department Head review
- Dean aggregate-only
- Admin management
- no broken navigation

Commit:
`M21: Polish UX, accessibility, and regression coverage`

---

## Do not touch

- Do not train the final machine-learning models yet.
- Do not create the FastAPI/fourth Docker container yet.
- Do not remove or bypass `PredictorInterface`.
- Do not present `placeholder-heuristic-v0` as trained ML.
- Do not remove OCR/PDF extraction that already works.
- Do not add AI grade extraction simply because the UI reference mentions AI.
- Do not remove student confirmation before parsed grades are saved.
- Do not regenerate or change `APP_KEY`.
- Do not expose raw encrypted socioeconomic responses to Department Heads, Deans, or Admin without an explicitly approved reason.
- Do not let Dean access individual student information.
- Do not reintroduce Faculty as an active role.
- Do not expand pilot/data-gathering scope outside CLAS.
- Do not hardcode final questionnaire items or arbitrary weights.
- Do not delete legacy records merely because a feature/role is removed from the active UI.
- Do not run `migrate:fresh` on preserved project data.
- Do not replace historical prediction snapshots with current mutable profile data.
- Do not remove Privacy.
- Do not re-add About to primary navigation.
- Do not add new MySQL/MariaDB-specific implementation that would make later PostgreSQL migration harder.

---

## Done when

- The active role set is Student, Department Head, Dean, and Administrator.
- Faculty is removed from active navigation/workflows without destroying historical data.
- CLAS and the eight approved programs are reflected in academic structure and scope.
- Dean is aggregate-only at backend and UI levels.
- Department Head can review only authorized department/program students.
- Guest users land directly on a polished login page.
- Students authenticate using student number and gated registration.
- Student navigation is clean and non-redundant.
- Questionnaire is structured, resumable, and ready for finalized research-backed questions.
- Skills/Experience uses structured entries and OJT is treated as Work Experience.
- Projects are removed from the active profile flow.
- Grades use a clear Upload / Paste / Manual workflow.
- UI does not falsely claim AI performs grade extraction.
- Professor names are removed from portal-pasted grade descriptions.
- INC is nonnumeric, excluded from GWA, and treated as incomplete rather than failed.
- Students can run an updated assessment without re-uploading grades and the latest confirmed grade version is snapshotted.
- Dashboard/Results are consolidated.
- History is its own page and shows immutable historical snapshots.
- Career Matches follows the supplied visual direction.
- Department Head monitoring and institutional interventions are revised.
- AI intervention wording has deterministic fallback behavior.
- Dean/Admin dashboards follow the supplied design direction.
- Loading states and accessibility improvements are present.
- Full test suite passes.
- Frontend build passes.
- Each milestone has its own clean commit.
