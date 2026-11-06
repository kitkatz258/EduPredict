"""Item-level mapping for the 60-item v2 instrument.

Matching labels or a shared 1–5 scale do not prove construct equivalence.
No English sentence in SystemQuestions.pdf is identical to a Pianda codebook
or questionnaire sentence. UCC inference stays blocked.
"""

from __future__ import annotations

from src.data.dropout import (
    FIRST_SEMESTER_COLUMNS,
    PREDICTOR_COLUMNS,
    SECOND_SEMESTER_COLUMNS,
    TARGET_COLUMN,
)
from src.features.catalog import GRADE_COLUMNS_BLOCKED
from src.questionnaire.v2_instrument import ITEMS, SECTIONS, VERSION

PIANDA_COMPETENCE = {
    "C1": "I have the ability to adapt quickly to changing situations or demands that occur in learning or group activities.",
    "C2": "I have the ability to interact clearly and effectively with group members who have different backgrounds, beliefs or opinions.",
    "C3": "I have the ability to plan, organise and execute projects efficiently and effectively.",
    "C4": "I have the ability to lead and inspire group members with a clear vision, purpose and values.",
    "C5": "I am able to use inductive or deductive reasoning for a variety of situations; use systems thinking and decision making.",
    "C6": "I was able to communicate clearly and collaborate with other group members.",
    "C7": "I am able to think work creatively and create new innovations.",
    "C8": "I am able to access information effectively and efficiently and evaluate the information used competently, and effectively to solve problems.",
    "C9": "I am able to select and develop media used for communication.",
    "C10": "I was able to analyse information media and create appropriate media for communication.",
}

PIANDA_PSYCAP = {
    "PsyCap1": "I can find various ways to overcome obstacles that arise during my studies.",
    "PsyCap2": "I realized that many approaches can be used to overcome the problems faced.",
    "PsyCap3": "I am committed to doing my best and working hard to achieve the goals I set.",
    "PsyCap4": "I still have high hopes that I can succeed in learning situations during the pandemic.",
    "PsyCap5": "I am optimistic that the investment of time and effort I am making now will bring positive results in the future.",
    "PsyCap6": "I tend to see positive lessons and learnings from every learning experience, both success and failure.",
    "PsyCap7": "I have a strong belief that I can make my dreams come true and achieve the big goals I set.",
    "PsyCap8": "I can identify and analyze various problems that arise in learning.",
    "PsyCap9": "I always try to find out the cause of the low grades I receive and find solutions to improve them.",
    "PsyCap10": "I understand that everyone experiences failure and getting back up is the key to achieving long-term success.",
    "PsyCap11": "I have a strong intrinsic motivation to achieve high academic achievements and am always looking for ways to continuously improve myself.",
    "PsyCap12": "I have a strong belief that I can complete difficult tasks successfully.",
    "PsyCap13": "I have enough self-confidence to ask the teacher when I have difficulty doing an assignment.",
    "PsyCap14": "I feel confident that I can make a meaningful and useful contribution to others with the abilities I have.",
}

PIANDA_EVS = {
    "EVS1": "I have the ability to discuss professionally with various professionals or technical aspects of work.",
    "EVS2": "I achieved a level of education sufficient to operate as a prospective new employee in the job.",
    "EVS3": "I am able to evaluate sufficient technical skills to be able to complete tasks or solve problems at school.",
    "EVS4": "I have a satisfactory level of professional experience to operate on the job.",
    "EVS5": "I am able to convince others about work when decisions within the team have to be made.",
    "EVS6": "Building social relationships can have a positive effect on my life.",
    "EVS7": "I have a clear plan for my career.",
    "EVS8": "I have clear career goals.",
    "EVS9": "I have a clear strategy to achieve my career goals.",
    "EVS10": "I have a clear idea of the target industry according to my field of work.",
    "EVS11": "I am able to achieve a balance between my work goals and the support of my colleagues.",
    "EVS12": "I am able to achieve a balance between my career goals and the support of my colleagues.",
}

EQUIVALENCE_NOTE = (
    "Conceptual similarity is a content reading of English sentences. It is not "
    "an inter-rater code, a factor loading, or measurement equivalence. No UCC "
    "item shares a verbatim English sentence with a Pianda codebook item."
)

# Closest source candidates. Status is never "exact".
ITEM_MAP: dict[str, dict] = {
    "A1": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Academic self-perception. Pianda has no academic-satisfaction block. Not a UCI grade.",
        "model_use": "no",
    },
    "A2": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Lesson comprehension self-report. Not a UCI curricular-unit grade or evaluation count.",
        "model_use": "no",
    },
    "A3": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Workload self-management. No Pianda or UCI counterpart.",
        "model_use": "no",
    },
    "A4": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Effort-based learning belief. Not Pianda competence and not a UCI academic record.",
        "model_use": "no",
    },
    "A5": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Confidence in passing subjects. Substituting this for UCI approved units would invent a mapping.",
        "model_use": "no",
    },
    "A6": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Degree-completion confidence. Not the UCI three-class target and not a Pianda item.",
        "model_use": "no",
    },
    "A7": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Explaining topics to classmates. No source academic or competence item states this.",
        "model_use": "no",
    },
    "A8": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Self-rated exam performance. Not interchangeable with Portuguese 0–20 unit grades.",
        "model_use": "no",
    },
    "A9": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Improving grades after a drop. Closest Pianda sentence is PsyCap9 (low-grade problem solving), which is a different instrument and construct.",
        "model_use": "no",
    },
    "A10": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Knowing which subjects to improve. Not a UCI or Pianda field.",
        "model_use": "no",
    },
    "S1": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "School-expense budget. Not UCI Debtor, tuition, or scholarship.",
        "model_use": "no",
    },
    "S2": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Transport affordability. No UCI or Pianda field.",
        "model_use": "no",
    },
    "S3": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Device access self-report. EduPredict also stores a categorical device profile field; neither is a UCI column.",
        "model_use": "no",
    },
    "S4": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Internet access self-report. Not a UCI predictor.",
        "model_use": "no",
    },
    "S5": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Financial situation allowing study focus. Not Portuguese scholarship/debt/tuition.",
        "model_use": "no",
    },
    "S6": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Financial support to continue. Conceptual overlap with scholarship is insufficient; coding differs.",
        "model_use": "no",
    },
    "S7": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Unexpected expenses. No source counterpart.",
        "model_use": "no",
    },
    "S8": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Daily meals while attending class. No source counterpart.",
        "model_use": "no",
    },
    "S9": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Quiet study place. Not a UCI displaced or housing code.",
        "model_use": "no",
    },
    "S10": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Work/household responsibilities. Not parental occupation or employment status in UCI.",
        "model_use": "no",
    },
    "B1": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Class attendance self-report. UCI has no attendance count. Must not change dropout probability.",
        "model_use": "no",
    },
    "B2": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Study time outside class. Not a UCI feature.",
        "model_use": "no",
    },
    "B3": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Class participation. Not a UCI feature.",
        "model_use": "no",
    },
    "B4": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Use of instructor materials. Not a UCI feature.",
        "model_use": "no",
    },
    "B5": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Meeting deadlines. Not UCI evaluations or approved units.",
        "model_use": "no",
    },
    "B6": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Persistence through study difficulties. Not a dropout probability input.",
        "model_use": "no",
    },
    "B7": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Note-taking. Not a UCI or Pianda item.",
        "model_use": "no",
    },
    "B8": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Study groups outside class. Not a UCI feature.",
        "model_use": "no",
    },
    "B9": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Asking instructors or classmates for help. PsyCap13 is teacher-help self-efficacy in a different instrument.",
        "model_use": "no",
    },
    "B10": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Focus and phone avoidance. Not a UCI feature.",
        "model_use": "no",
    },
    "C1": {
        "status": "conceptual",
        "source": "Pianda C6 (secondary C2)",
        "source_statement": PIANDA_COMPETENCE["C6"],
        "reason": "General communication. Pianda C6 adds collaboration; C2 is diverse-group interaction. Not exact.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
    },
    "C2": {
        "status": "conceptual",
        "source": "Pianda C6 and C2",
        "source_statement": PIANDA_COMPETENCE["C6"],
        "reason": "Teamwork. No Pianda competence item says 'work effectively in a team' without collaboration/diversity clauses.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
    },
    "C3": {
        "status": "conceptual",
        "source": "Pianda C5 and C8",
        "source_statement": PIANDA_COMPETENCE["C5"],
        "reason": "Problem identification and solutions. Pianda C5 is reasoning/systems thinking; C8 is information evaluation.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
    },
    "C4": {
        "status": "conceptual",
        "source": "Pianda C1",
        "source_statement": PIANDA_COMPETENCE["C1"],
        "reason": "Both mention adaptation. Pianda C1 does not mention tools or technologies and is limited to learning or group activities.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
    },
    "C5": {
        "status": "conceptual",
        "source": "Pianda C8, C9, C10",
        "source_statement": PIANDA_COMPETENCE["C9"],
        "reason": "Digital-tool use. Pianda IT/media items are information access and media creation, not 'digital tools for academic or practical tasks'.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
    },
    "C6": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Transfer of school learning to real-world tasks. No Pianda C1–C10 item states this. SIE and EVS are not competence predictors.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
        "blocked_as_exact_input": True,
    },
    "C7": {
        "status": "conceptual",
        "source": "Pianda C3",
        "source_statement": PIANDA_COMPETENCE["C3"],
        "reason": "Organization. Pianda C3 is project planning and execution, not general work/responsibilities.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
    },
    "C8": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Learning new skills when required. Pianda C7 is creative innovation, a different behavior.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
        "blocked_as_exact_input": True,
    },
    "C9": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Responsibility and professional behavior. No Pianda competence item. EVS items are the target and cannot be used as predictors.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
        "blocked_as_exact_input": True,
    },
    "C10": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Accepting feedback. SIE11 is supervisor feedback during internship, not a competence self-rating, and SIE is not a v1 predictor.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_competence",
        "blocked_as_exact_input": True,
    },
    "P1": {
        "status": "conceptual",
        "source": "Pianda PsyCap12",
        "source_statement": PIANDA_PSYCAP["PsyCap12"],
        "reason": "Close content on completing difficult tasks. UCC adds studies or future work. Still not verbatim or measurement-equivalent.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
    },
    "P2": {
        "status": "conceptual",
        "source": "Pianda PsyCap1 (secondary PsyCap2)",
        "source_statement": PIANDA_PSYCAP["PsyCap1"],
        "reason": "Close content on multiple pathways. UCC drops the study setting. PsyCap2 is recognition that approaches exist.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
    },
    "P3": {
        "status": "conceptual",
        "source": "Pianda PsyCap10 and PsyCap3",
        "source_statement": PIANDA_PSYCAP["PsyCap10"],
        "reason": "Persistence after setbacks. PsyCap10 is a general belief about other people; PsyCap3 has no setback clause.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
    },
    "P4": {
        "status": "conceptual",
        "source": "Pianda PsyCap5",
        "source_statement": PIANDA_PSYCAP["PsyCap5"],
        "reason": "Close content on expected positive results. UCC names studies and career and does not use 'optimistic'.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
    },
    "P5": {
        "status": "conceptual",
        "source": "Pianda PsyCap10 and PsyCap9",
        "source_statement": PIANDA_PSYCAP["PsyCap9"],
        "reason": "Recovery after academic or personal difficulties. Not the same as PsyCap10's general belief or PsyCap9's low-grade problem solving.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
    },
    "P6": {
        "status": "conceptual",
        "source": "Pianda PsyCap11",
        "source_statement": PIANDA_PSYCAP["PsyCap11"],
        "reason": "Growth-through-effort belief. PsyCap11 is motivation for high academic achievement. Item counts still 10 vs 14.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
    },
    "P7": {
        "status": "conceptual",
        "source": "Pianda PsyCap1 and PsyCap7",
        "source_statement": PIANDA_PSYCAP["PsyCap1"],
        "reason": "Many ways to reach career goals. Pianda hope items are study obstacles or dreams, not career-goal pathways.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
    },
    "P8": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Feeling energized while pursuing goals. No Pianda PsyCap item states energy or vigor.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
        "blocked_as_exact_input": True,
    },
    "P9": {
        "status": "conceptual",
        "source": "Pianda PsyCap10",
        "source_statement": PIANDA_PSYCAP["PsyCap10"],
        "reason": "Staying calm when plans fail. PsyCap10 is a general recovery belief, not calm persistence.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
    },
    "P10": {
        "status": "conceptual",
        "source": "Pianda PsyCap6",
        "source_statement": PIANDA_PSYCAP["PsyCap6"],
        "reason": "Looking at the bright side. PsyCap6 is learning positive lessons from success and failure.",
        "model_use": "pending",
        "construct_role": "candidate_pianda_psycap",
    },
    "E1": {
        "status": "conceptual",
        "source": "Pianda EVS2 (target item)",
        "source_statement": PIANDA_EVS["EVS2"],
        "reason": "Job-application preparedness. EVS items are the employability TARGET. Using E as a predictor is circularity/leakage.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
    "E2": {
        "status": "conceptual",
        "source": "Pianda EVS3 (target item)",
        "source_statement": PIANDA_EVS["EVS3"],
        "reason": "Demonstrating skills to an employer. Target-adjacent. Excluded from predictors.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
    "E3": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Explaining knowledge relevance to a future job. No EVS sentence matches. Still excluded because E is the UCC self-assessment of employability.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
    "E4": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Continuing to develop career skills. Excluded from the employability predictor.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
    "E5": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Belief in finding a degree-related job. Not an employment outcome and not a predictor.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
    "E6": {
        "status": "conceptual",
        "source": "Pianda EVS10 (target item)",
        "source_statement": PIANDA_EVS["EVS10"],
        "reason": "Knowing which jobs the degree supports. EVS10 is target-industry knowledge. Target leakage if used as input.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
    "E7": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Belief that employers value school skills. Excluded.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
    "E8": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Interview confidence. No EVS match. Excluded.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
    "E9": {
        "status": "none",
        "source": None,
        "source_statement": None,
        "reason": "Alternative work opportunities. Excluded.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
    "E10": {
        "status": "conceptual",
        "source": "Pianda EVS7 (target item)",
        "source_statement": PIANDA_EVS["EVS7"],
        "reason": "Plan after graduation vs EVS7 clear career plan. Target item. Must not enter predictors.",
        "model_use": "no",
        "leakage": "target_adjacent",
    },
}


def questionnaire_rows() -> list[dict]:
    rows = []
    section_lookup = {section["key"]: section for section in SECTIONS}
    for item in ITEMS:
        mapping = ITEM_MAP[item["code"]]
        section = section_lookup[item["section"]]
        rows.append(
            {
                "code": item["code"],
                "statement": item["text"],
                "ucc_construct": section["label"],
                "section": item["section"],
                "role": section["role"],
                "matching_status": mapping["status"],
                "source_variable": mapping["source"],
                "source_statement": mapping["source_statement"],
                "reason": mapping["reason"],
                "transformation": "none; UCC answers are not converted into source features",
                "source_citation": (
                    "Pianda codebook sheet of Raw Dataset.xlsx and Survey questionnaire.docx"
                    if mapping["source"]
                    else "No defensible source item"
                ),
                "model_use": mapping["model_use"],
                "missingness": "Require all ten valid 1–5 integers; no Neutral imputation",
                "leakage": mapping.get("leakage", "none as a source predictor; UCC self-report is same-occasion"),
                "availability": "After a completed v2 questionnaire submission at prediction request",
                "equivalence": "not established",
            }
        )
    return rows


def _academic_row(**kwargs) -> dict:
    return kwargs


def academic_feature_rows() -> list[dict]:
    """Non-questionnaire UCC fields against UCI/Pianda definitions."""
    rows = [
        _academic_row(
            ucc_field="gwa / major_gwa / other_gwa",
            ucc_definition="Unit-weighted Philippine GWA, 1.00 best / 5.00 fail, INC and dropped excluded, NSTP excluded.",
            source_field="Curricular units 1st/2nd sem (grade); Previous qualification (grade); Admission grade",
            source_definition="Portuguese 0–20 unit averages (higher better) or 0–200 admission/prior grades.",
            status="blocked",
            reason="No defensible conversion is documented. Direct substitution is inappropriate.",
            availability="After a confirmed current grade report. No first-versus-second semester index exists.",
            leakage="A cumulative GWA is not a first-semester UCI grade. Later terms must not be written into 1st-sem columns.",
        ),
        _academic_row(
            ucc_field="failed_subjects",
            ucc_definition="Count of finals 5.00 or FAILED remarks on current confirmed rows.",
            source_field="None. UCI stores approved-unit counts, not a failure count.",
            source_definition="Curricular units approved is a count of approved units, not failed subjects.",
            status="blocked",
            reason="Different construct. Do not recode failures as unapproved units without a verified rule.",
            availability="After confirmed term results.",
            leakage="Unknown if used as if it were first-semester approved units.",
        ),
        _academic_row(
            ucc_field="incomplete_subjects / dropped_subjects",
            ucc_definition="INC and DRP/W/UD token counts. Left out of GWA.",
            source_field="Curricular units 1st/2nd sem (without evaluations)",
            source_definition="UCI count of units without evaluations. The 2nd-sem documentation says '1st semester'.",
            status="blocked",
            reason="Local tokens are not that UCI count.",
            availability="After confirmed term results.",
            leakage="Do not code a missing earlier term as zero incompletes.",
        ),
        _academic_row(
            ucc_field="major_units / other_units / subject units on grade rows",
            ucc_definition="GPA units from confirmed subject rows, split by is_major_subject. Cumulative across uploaded terms.",
            source_field="Curricular units 1st/2nd sem (enrolled) and (approved)",
            source_definition="Semester-specific enrolled and approved curricular-unit counts on the UCI file.",
            status="pending",
            reason=(
                "Unit aggregates could be related after a written term-identity and approval-definition memo. "
                "No such memo exists. Course-title matching is not required, but unit definitions are unverified."
            ),
            availability="After that term is confirmed. FeatureBuilder currently emits cumulative major/other units, not 1st/2nd sem counts.",
            leakage="Using a later term as '1st sem' or using 2nd-sem units at enrollment is hindsight leakage.",
        ),
        _academic_row(
            ucc_field="semesters_completed",
            ucc_definition="Count of distinct confirmed school_year|semester pairs. Alphabetical semester order is not calendar order.",
            source_field="None",
            source_definition="UCI does not store a completed-semester count; it stores per-semester unit fields.",
            status="blocked",
            reason="Not a UCI column.",
            availability="After confirmed reports exist.",
            leakage="A single uploaded later term looks like a first term.",
        ),
        _academic_row(
            ucc_field="scholarship_status / has_scholarship",
            ucc_definition="Encrypted profile statuses none/partial/full/government/private. FeatureBuilder currently stores scholarship as null and has_scholarship as false on new snapshots.",
            source_field="Scholarship holder",
            source_definition="UCI 0/1 flag, complete in the source file.",
            status="pending",
            reason="Binary collapse of every status other than none is implemented in older discussion and is not verified against the Portuguese field. Missing profiles must not be scored as 0.",
            availability="Only after a published socioeconomic profile. Currently not emitted as a live predictor.",
            leakage="Coercing a missing profile to 0 invents a 'no scholarship' value.",
        ),
        _academic_row(
            ucc_field="internship_count / work_experience_count / certification_count / technical_skill_count",
            ucc_definition="Counts of saved, non-archived structured rows.",
            source_field="Pianda SIE1–SIE14 / AVERAGE (SIE)",
            source_definition="14 Likert items on internship experience for vocational students who all had internships.",
            status="blocked",
            reason="A count is not the SIE mean. Optional internship models stay source-native comparisons only.",
            availability="After the Skills & Experience section is saved.",
            leakage="Not an employability target, but not a validated C/P substitute either.",
        ),
        _academic_row(
            ucc_field="program / year_level / enrollment_year / birthdate",
            ucc_definition="Copied from institution_students at registration. enrollment_year is the calendar year of registration, not historical first enrollment. birthdate is optional and is excluded from FeatureSet.",
            source_field="Course; Age at enrollment",
            source_definition="UCI original course codes (33, 171, 8014, …). Age in years at enrollment.",
            status="blocked",
            reason="UCC program codes are not UCI course codes. Age has no verified enrollment date and is a sensitive attribute.",
            availability="Program and year level at registration. Age not available to current inference.",
            leakage="Using registration year as enrollment date mismeasures continuing students.",
        ),
        _academic_row(
            ucc_field="legacy construct_scores (study_habits, time_management, motivation, procrastination, engagement)",
            ucc_definition="draft-v1 0–100 scores from a different 20-item instrument.",
            source_field="None",
            source_definition="Not A/S/B/C/P/E and not UCI columns.",
            status="blocked",
            reason="Historic instrument. Do not remap 0–100 scores into Pianda means.",
            availability="Latest submitted draft-v1 response only.",
            leakage="Not a source target, but the wrong instrument.",
        ),
        _academic_row(
            ucc_field="household profile (income, employment, living arrangement, internet, device, study space, household size)",
            ucc_definition="Encrypted socioeconomic_profiles. New predictions do not read these fields (M37.1).",
            source_field="Debtor; Tuition fees up to date; Displaced; Unemployment rate; Inflation rate; GDP",
            source_definition="Portuguese administrative and macro fields.",
            status="blocked",
            reason="Different constructs, coding, and populations. Living arrangement is not Displaced.",
            availability="Stored but not used on new snapshots.",
            leakage="Not target leakage; still blocked as invented UCI substitutes.",
        ),
    ]
    for name in GRADE_COLUMNS_BLOCKED:
        rows.append(
            _academic_row(
                ucc_field="no converted UCC value",
                ucc_definition="Philippine GWA must not be placed in this column.",
                source_field=name,
                source_definition="UCI grade field on the documented Portuguese scale.",
                status="blocked",
                reason="M42 excludes average-grade fields and does not invent a conversion.",
                availability="UCI file only.",
                leakage="First-semester grade at enrollment, or second-semester grade before that semester, is temporal leakage.",
            )
        )
    return rows


def construct_summary() -> list[dict]:
    return [
        {
            "group": "A",
            "items": 10,
            "allowed_research_role": "Academic self-perception/context",
            "restriction": "Not a native UCI academic feature",
            "mapping_validated": False,
            "model_use": "no",
        },
        {
            "group": "S",
            "items": 10,
            "allowed_research_role": "Resource/financial context and support",
            "restriction": "Not automatically Portuguese scholarship/debt/tuition",
            "mapping_validated": False,
            "model_use": "no",
        },
        {
            "group": "B",
            "items": 10,
            "allowed_research_role": "Student engagement and recommendations",
            "restriction": "Not a UCI feature; cannot affect dropout probability",
            "mapping_validated": False,
            "model_use": "no",
        },
        {
            "group": "C",
            "items": 10,
            "source_items": 10,
            "allowed_research_role": "Candidate Pianda competence input",
            "restriction": "Item count now matches (10 vs 10) but wording does not. Exact matches: 0.",
            "mapping_validated": False,
            "model_use": "pending",
        },
        {
            "group": "P",
            "items": 10,
            "source_items": 14,
            "allowed_research_role": "Candidate Pianda psychological capital input",
            "restriction": "UCC has 10 items; source has 14. Exact matches: 0. PsyCap4 is pandemic-specific.",
            "mapping_validated": False,
            "model_use": "pending",
        },
        {
            "group": "E",
            "items": 10,
            "source_items": 12,
            "allowed_research_role": "Separate UCC self-perceived employability score",
            "restriction": "Exclude from employability predictor to prevent circularity/leakage",
            "mapping_validated": False,
            "model_use": "no",
        },
    ]


def assert_mapping_complete() -> None:
    codes = [item["code"] for item in ITEMS]
    if set(codes) != set(ITEM_MAP):
        missing = sorted(set(codes) - set(ITEM_MAP))
        extra = sorted(set(ITEM_MAP) - set(codes))
        raise AssertionError(f"Mapping incomplete. Missing {missing}; extra {extra}.")
    if any(row["matching_status"] == "exact" for row in questionnaire_rows()):
        raise AssertionError("No exact item match is documented; do not mark exact without verbatim identity.")
    if VERSION != "ucc-panel-revised-60-v2":
        raise AssertionError("Mapping must follow the 60-item v2 instrument.")


def uci_timing_note(column: str) -> str:
    if column in SECOND_SEMESTER_COLUMNS:
        return "after_second_semester"
    if column in FIRST_SEMESTER_COLUMNS:
        return "after_first_semester"
    if column == TARGET_COLUMN:
        return "outcome"
    if column in PREDICTOR_COLUMNS:
        return "at_enrollment_if_collected"
    return "unknown"
