<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

/**
 * Revised 60-item instrument pending adviser/panel reapproval.
 * Wording is transcribed from the uploaded SystemQuestions DOCX. No item is reverse-scored.
 * This is not the panel-approved 36-item instrument and must not be labeled as such.
 */
final class RevisedInstrumentCatalog
{
    public const VERSION = 'ucc-panel-revised-60-v2';

    /**
     * @return list<array{code: string, section: string, construct: string, text: string, sort_order: int}>
     */
    public static function items(): array
    {
        return [
            self::item('A1', 'academic', 'academic_self_perception', 'I am satisfied with my current academic performance.'),
            self::item('A2', 'academic', 'academic_self_perception', 'I can understand the lessons and topics discussed in my classes.'),
            self::item('A3', 'academic', 'academic_self_perception', 'I can manage the academic workload required by my degree program.'),
            self::item('A4', 'academic', 'academic_self_perception', 'I can learn difficult topics when I put in enough effort.'),
            self::item('A5', 'academic', 'academic_self_perception', 'I am confident that I can pass my enrolled subjects.'),
            self::item('A6', 'academic', 'academic_self_perception', 'I am confident that I can complete my degree program.'),
            self::item('A7', 'academic', 'academic_self_perception', 'I can explain the topics I have learned to my classmates when they ask.'),
            self::item('A8', 'academic', 'academic_self_perception', 'I do well in exams and written assessments in my subjects.'),
            self::item('A9', 'academic', 'academic_self_perception', 'I can find ways to improve my grades when they go down.'),
            self::item('A10', 'academic', 'academic_self_perception', 'I know which subjects I need to improve in and what I need to do about it.'),
            self::item('S1', 'socioeconomic', 'socioeconomic_context', 'My available budget is sufficient to cover my school-related expenses.'),
            self::item('S2', 'socioeconomic', 'socioeconomic_context', 'I can afford the transportation expenses needed to attend classes.'),
            self::item('S3', 'socioeconomic', 'socioeconomic_context', 'I have reliable access to a computer or device for my academic tasks.'),
            self::item('S4', 'socioeconomic', 'socioeconomic_context', 'I have reliable access to the internet for my academic requirements.'),
            self::item('S5', 'socioeconomic', 'socioeconomic_context', 'My financial situation allows me to focus on my studies.'),
            self::item('S6', 'socioeconomic', 'socioeconomic_context', 'I have sufficient financial support to continue my education.'),
            self::item('S7', 'socioeconomic', 'socioeconomic_context', 'I can handle unexpected expenses without having to stop or delay my studies.'),
            self::item('S8', 'socioeconomic', 'socioeconomic_context', 'I have enough money for my daily meals while attending classes.'),
            self::item('S9', 'socioeconomic', 'socioeconomic_context', 'I have a quiet place at home or nearby where I can study.'),
            self::item('S10', 'socioeconomic', 'socioeconomic_context', 'My work or household responsibilities do not get in the way of my studies.'),
            self::item('B1', 'behavioral', 'behavioral_engagement', 'I attend my scheduled classes regularly.'),
            self::item('B2', 'behavioral', 'behavioral_engagement', 'I set aside regular time to study or review my lessons outside class.'),
            self::item('B3', 'behavioral', 'behavioral_engagement', 'I actively participate in class activities and discussions when given the opportunity.'),
            self::item('B4', 'behavioral', 'behavioral_engagement', 'I make use of the learning materials and resources provided by my instructors when studying.'),
            self::item('B5', 'behavioral', 'behavioral_engagement', 'I complete my academic requirements within the given deadlines.'),
            self::item('B6', 'behavioral', 'behavioral_engagement', 'I continue working toward my academic goals even when I experience difficulties in my studies.'),
            self::item('B7', 'behavioral', 'behavioral_engagement', 'I take notes or make summaries during or after my lessons.'),
            self::item('B8', 'behavioral', 'behavioral_engagement', 'I join study groups or academic activities outside regular class hours.'),
            self::item('B9', 'behavioral', 'behavioral_engagement', 'I ask my instructors or classmates for help when I do not understand a lesson.'),
            self::item('B10', 'behavioral', 'behavioral_engagement', 'I stay focused during class and avoid distractions such as my phone.'),
            self::item('C1', 'competence', 'competence', 'I can communicate my ideas clearly to other people.'),
            self::item('C2', 'competence', 'competence', 'I can work effectively with other people in a team.'),
            self::item('C3', 'competence', 'competence', 'I can identify problems and develop appropriate solutions.'),
            self::item('C4', 'competence', 'competence', 'I can adapt to new tools, technologies, and changing situations.'),
            self::item('C5', 'competence', 'competence', 'I can use digital tools and technology effectively for academic or practical tasks.'),
            self::item('C6', 'competence', 'competence', 'I can apply what I learned in school to practical tasks or real-world situations.'),
            self::item('C7', 'competence', 'competence', 'I can organize my work and responsibilities to complete assigned tasks.'),
            self::item('C8', 'competence', 'competence', 'I can learn new skills when a task or situation requires them.'),
            self::item('C9', 'competence', 'competence', 'I show responsibility and professional behavior when working on tasks or with others.'),
            self::item('C10', 'competence', 'competence', 'I can accept feedback and use it to improve my work.'),
            self::item('P1', 'psychological_capital', 'psychological_capital', 'I am confident that I can successfully handle difficult tasks related to my studies or future work.'),
            self::item('P2', 'psychological_capital', 'psychological_capital', 'When I encounter a problem, I can think of different ways to overcome it.'),
            self::item('P3', 'psychological_capital', 'psychological_capital', 'I continue working toward my goals even after experiencing setbacks.'),
            self::item('P4', 'psychological_capital', 'psychological_capital', 'I generally expect that my efforts toward my studies and career will lead to positive results.'),
            self::item('P5', 'psychological_capital', 'psychological_capital', 'I can recover and continue after academic or personal difficulties affect my progress.'),
            self::item('P6', 'psychological_capital', 'psychological_capital', 'I believe I can improve my abilities through effort, learning, and experience.'),
            self::item('P7', 'psychological_capital', 'psychological_capital', 'I can think of many ways to reach my career goals.'),
            self::item('P8', 'psychological_capital', 'psychological_capital', 'I feel energized when pursuing my goals for my studies and future work.'),
            self::item('P9', 'psychological_capital', 'psychological_capital', 'I stay calm and keep going when things do not go as planned.'),
            self::item('P10', 'psychological_capital', 'psychological_capital', 'I look at the bright side of difficult situations.'),
            self::item('E1', 'self_perceived_employability', 'self_perceived_employability', 'I feel prepared to apply for a job related to my degree program.'),
            self::item('E2', 'self_perceived_employability', 'self_perceived_employability', 'I believe I can demonstrate useful skills to a potential employer.'),
            self::item('E3', 'self_perceived_employability', 'self_perceived_employability', 'I can explain how my knowledge and experiences are relevant to a future job.'),
            self::item('E4', 'self_perceived_employability', 'self_perceived_employability', 'I am confident that I can continue developing the skills needed for my chosen career.'),
            self::item('E5', 'self_perceived_employability', 'self_perceived_employability', 'I believe I can find a job related to my degree after graduation.'),
            self::item('E6', 'self_perceived_employability', 'self_perceived_employability', 'I know what kinds of jobs I can apply for with my degree program.'),
            self::item('E7', 'self_perceived_employability', 'self_perceived_employability', 'I believe employers would value the skills I have gained in school.'),
            self::item('E8', 'self_perceived_employability', 'self_perceived_employability', 'I am confident that I can do well in a job interview.'),
            self::item('E9', 'self_perceived_employability', 'self_perceived_employability', 'I believe I can find other work opportunities if my first-choice job is not available.'),
            self::item('E10', 'self_perceived_employability', 'self_perceived_employability', 'I have a plan for what I will do after graduation.'),
        ];
    }

    /**
     * @return array{code: string, section: string, construct: string, text: string, sort_order: int}
     */
    private static function item(string $code, string $section, string $construct, string $text): array
    {
        return [
            'code' => $code,
            'section' => $section,
            'construct' => $construct,
            'text' => $text,
            'sort_order' => (int) substr($code, 1),
        ];
    }
}
