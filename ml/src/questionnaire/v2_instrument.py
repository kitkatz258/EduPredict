"""Locked 60-item v2 statements transcribed from SystemQuestions.pdf.

This catalog is research-only. It does not modify Laravel questionnaires,
seed data, or stored student responses. Version `ucc-panel-revised-60-v2`
is pending panel reapproval and is not psychometrically validated.
"""

from __future__ import annotations

VERSION = "ucc-panel-revised-60-v2"
SOURCE_PDF = "src/cursor/reference/approved-questionnaire/SystemQuestions.pdf"
SCORING_PDF = (
    "src/cursor/reference/approved-questionnaire/"
    "EduPredict_Scoring_and_Research_Basis_Updated.pdf"
)

LIKERT_MIN = 1
LIKERT_MAX = 5
ITEMS_PER_SECTION = 10

RESPONSE_LABELS = {
    1: "Strongly Disagree",
    2: "Disagree",
    3: "Neutral",
    4: "Agree",
    5: "Strongly Agree",
}

# Unrounded-mean boundaries from the updated scoring PDF, section 3.3.
# Classification uses these cuts; formatting happens after classification.
BAND_CUTS = (
    (1.80, "Very Low"),
    (2.60, "Low"),
    (3.40, "Moderate"),
    (4.20, "High"),
    (5.00, "Very High"),
)
CONTEXT_FLAG_AT_OR_BELOW = 2.60
CONTEXT_SECTIONS = frozenset({"academic", "socioeconomic", "behavioral"})

SECTIONS = (
    {
        "key": "academic",
        "code": "A",
        "label": "Academic factors / academic self-perception",
        "mean_name": "AcademicMean",
        "role": "context",
        "construct": "academic_self_perception",
        "item_codes": tuple(f"A{index}" for index in range(1, 11)),
    },
    {
        "key": "socioeconomic",
        "code": "S",
        "label": "Socioeconomic factors / learning resources",
        "mean_name": "SocioeconomicMean",
        "role": "context",
        "construct": "socioeconomic_context",
        "item_codes": tuple(f"S{index}" for index in range(1, 11)),
    },
    {
        "key": "behavioral",
        "code": "B",
        "label": "Behavioral factors / student engagement",
        "mean_name": "BehavioralMean",
        "role": "context",
        "construct": "behavioral_engagement",
        "item_codes": tuple(f"B{index}" for index in range(1, 11)),
    },
    {
        "key": "competence",
        "code": "C",
        "label": "Competence / employability skills",
        "mean_name": "CompetenceMean",
        "role": "candidate_predictor",
        "construct": "competence",
        "item_codes": tuple(f"C{index}" for index in range(1, 11)),
    },
    {
        "key": "psychological_capital",
        "code": "P",
        "label": "Psychological capital / career readiness",
        "mean_name": "PsyCapMean",
        "role": "candidate_predictor",
        "construct": "psychological_capital",
        "item_codes": tuple(f"P{index}" for index in range(1, 11)),
    },
    {
        "key": "self_perceived_employability",
        "code": "E",
        "label": "Self-perceived employability / readiness",
        "mean_name": "SelfPerceivedEmployabilityMean",
        "role": "descriptive_comparison",
        "construct": "self_perceived_employability",
        "item_codes": tuple(f"E{index}" for index in range(1, 11)),
    },
)

# Verbatim statements from SystemQuestions.pdf (checked against
# RevisedInstrumentCatalog). Line wraps in the PDF were joined with a space.
ITEMS: tuple[dict[str, str], ...] = (
    {
        "code": "A1",
        "section": "academic",
        "text": "I am satisfied with my current academic performance.",
    },
    {
        "code": "A2",
        "section": "academic",
        "text": "I can understand the lessons and topics discussed in my classes.",
    },
    {
        "code": "A3",
        "section": "academic",
        "text": "I can manage the academic workload required by my degree program.",
    },
    {
        "code": "A4",
        "section": "academic",
        "text": "I can learn difficult topics when I put in enough effort.",
    },
    {
        "code": "A5",
        "section": "academic",
        "text": "I am confident that I can pass my enrolled subjects.",
    },
    {
        "code": "A6",
        "section": "academic",
        "text": "I am confident that I can complete my degree program.",
    },
    {
        "code": "A7",
        "section": "academic",
        "text": "I can explain the topics I have learned to my classmates when they ask.",
    },
    {
        "code": "A8",
        "section": "academic",
        "text": "I do well in exams and written assessments in my subjects.",
    },
    {
        "code": "A9",
        "section": "academic",
        "text": "I can find ways to improve my grades when they go down.",
    },
    {
        "code": "A10",
        "section": "academic",
        "text": "I know which subjects I need to improve in and what I need to do about it.",
    },
    {
        "code": "S1",
        "section": "socioeconomic",
        "text": "My available budget is sufficient to cover my school-related expenses.",
    },
    {
        "code": "S2",
        "section": "socioeconomic",
        "text": "I can afford the transportation expenses needed to attend classes.",
    },
    {
        "code": "S3",
        "section": "socioeconomic",
        "text": "I have reliable access to a computer or device for my academic tasks.",
    },
    {
        "code": "S4",
        "section": "socioeconomic",
        "text": "I have reliable access to the internet for my academic requirements.",
    },
    {
        "code": "S5",
        "section": "socioeconomic",
        "text": "My financial situation allows me to focus on my studies.",
    },
    {
        "code": "S6",
        "section": "socioeconomic",
        "text": "I have sufficient financial support to continue my education.",
    },
    {
        "code": "S7",
        "section": "socioeconomic",
        "text": "I can handle unexpected expenses without having to stop or delay my studies.",
    },
    {
        "code": "S8",
        "section": "socioeconomic",
        "text": "I have enough money for my daily meals while attending classes.",
    },
    {
        "code": "S9",
        "section": "socioeconomic",
        "text": "I have a quiet place at home or nearby where I can study.",
    },
    {
        "code": "S10",
        "section": "socioeconomic",
        "text": "My work or household responsibilities do not get in the way of my studies.",
    },
    {
        "code": "B1",
        "section": "behavioral",
        "text": "I attend my scheduled classes regularly.",
    },
    {
        "code": "B2",
        "section": "behavioral",
        "text": "I set aside regular time to study or review my lessons outside class.",
    },
    {
        "code": "B3",
        "section": "behavioral",
        "text": "I actively participate in class activities and discussions when given the opportunity.",
    },
    {
        "code": "B4",
        "section": "behavioral",
        "text": "I make use of the learning materials and resources provided by my instructors when studying.",
    },
    {
        "code": "B5",
        "section": "behavioral",
        "text": "I complete my academic requirements within the given deadlines.",
    },
    {
        "code": "B6",
        "section": "behavioral",
        "text": "I continue working toward my academic goals even when I experience difficulties in my studies.",
    },
    {
        "code": "B7",
        "section": "behavioral",
        "text": "I take notes or make summaries during or after my lessons.",
    },
    {
        "code": "B8",
        "section": "behavioral",
        "text": "I join study groups or academic activities outside regular class hours.",
    },
    {
        "code": "B9",
        "section": "behavioral",
        "text": "I ask my instructors or classmates for help when I do not understand a lesson.",
    },
    {
        "code": "B10",
        "section": "behavioral",
        "text": "I stay focused during class and avoid distractions such as my phone.",
    },
    {
        "code": "C1",
        "section": "competence",
        "text": "I can communicate my ideas clearly to other people.",
    },
    {
        "code": "C2",
        "section": "competence",
        "text": "I can work effectively with other people in a team.",
    },
    {
        "code": "C3",
        "section": "competence",
        "text": "I can identify problems and develop appropriate solutions.",
    },
    {
        "code": "C4",
        "section": "competence",
        "text": "I can adapt to new tools, technologies, and changing situations.",
    },
    {
        "code": "C5",
        "section": "competence",
        "text": "I can use digital tools and technology effectively for academic or practical tasks.",
    },
    {
        "code": "C6",
        "section": "competence",
        "text": "I can apply what I learned in school to practical tasks or real-world situations.",
    },
    {
        "code": "C7",
        "section": "competence",
        "text": "I can organize my work and responsibilities to complete assigned tasks.",
    },
    {
        "code": "C8",
        "section": "competence",
        "text": "I can learn new skills when a task or situation requires them.",
    },
    {
        "code": "C9",
        "section": "competence",
        "text": "I show responsibility and professional behavior when working on tasks or with others.",
    },
    {
        "code": "C10",
        "section": "competence",
        "text": "I can accept feedback and use it to improve my work.",
    },
    {
        "code": "P1",
        "section": "psychological_capital",
        "text": "I am confident that I can successfully handle difficult tasks related to my studies or future work.",
    },
    {
        "code": "P2",
        "section": "psychological_capital",
        "text": "When I encounter a problem, I can think of different ways to overcome it.",
    },
    {
        "code": "P3",
        "section": "psychological_capital",
        "text": "I continue working toward my goals even after experiencing setbacks.",
    },
    {
        "code": "P4",
        "section": "psychological_capital",
        "text": "I generally expect that my efforts toward my studies and career will lead to positive results.",
    },
    {
        "code": "P5",
        "section": "psychological_capital",
        "text": "I can recover and continue after academic or personal difficulties affect my progress.",
    },
    {
        "code": "P6",
        "section": "psychological_capital",
        "text": "I believe I can improve my abilities through effort, learning, and experience.",
    },
    {
        "code": "P7",
        "section": "psychological_capital",
        "text": "I can think of many ways to reach my career goals.",
    },
    {
        "code": "P8",
        "section": "psychological_capital",
        "text": "I feel energized when pursuing my goals for my studies and future work.",
    },
    {
        "code": "P9",
        "section": "psychological_capital",
        "text": "I stay calm and keep going when things do not go as planned.",
    },
    {
        "code": "P10",
        "section": "psychological_capital",
        "text": "I look at the bright side of difficult situations.",
    },
    {
        "code": "E1",
        "section": "self_perceived_employability",
        "text": "I feel prepared to apply for a job related to my degree program.",
    },
    {
        "code": "E2",
        "section": "self_perceived_employability",
        "text": "I believe I can demonstrate useful skills to a potential employer.",
    },
    {
        "code": "E3",
        "section": "self_perceived_employability",
        "text": "I can explain how my knowledge and experiences are relevant to a future job.",
    },
    {
        "code": "E4",
        "section": "self_perceived_employability",
        "text": "I am confident that I can continue developing the skills needed for my chosen career.",
    },
    {
        "code": "E5",
        "section": "self_perceived_employability",
        "text": "I believe I can find a job related to my degree after graduation.",
    },
    {
        "code": "E6",
        "section": "self_perceived_employability",
        "text": "I know what kinds of jobs I can apply for with my degree program.",
    },
    {
        "code": "E7",
        "section": "self_perceived_employability",
        "text": "I believe employers would value the skills I have gained in school.",
    },
    {
        "code": "E8",
        "section": "self_perceived_employability",
        "text": "I am confident that I can do well in a job interview.",
    },
    {
        "code": "E9",
        "section": "self_perceived_employability",
        "text": "I believe I can find other work opportunities if my first-choice job is not available.",
    },
    {
        "code": "E10",
        "section": "self_perceived_employability",
        "text": "I have a plan for what I will do after graduation.",
    },
)

ITEM_BY_CODE = {item["code"]: item for item in ITEMS}
ALL_ITEM_CODES = tuple(item["code"] for item in ITEMS)
REVERSE_SCORED_CODES: tuple[str, ...] = ()


def section_by_key(key: str) -> dict:
    for section in SECTIONS:
        if section["key"] == key:
            return section
    raise KeyError(key)
