<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

/**
 * Transcription of the panel-approved UCC questionnaire.
 * Wording matches EduPredict_Questionnaire.pdf. No item is reverse-scored.
 * The source PDF still calls the instrument a draft for expert validation.
 */
final class ApprovedInstrumentCatalog
{
    public const VERSION = 'ucc-panel-approved-v1';

    /**
     * @return list<array{code: string, section: string, construct: string, text: string}>
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
            self::item('S1', 'socioeconomic', 'socioeconomic_context', 'My available budget is sufficient to cover my school-related expenses.'),
            self::item('S2', 'socioeconomic', 'socioeconomic_context', 'I can afford the transportation expenses needed to attend classes.'),
            self::item('S3', 'socioeconomic', 'socioeconomic_context', 'I have reliable access to a computer or device for my academic tasks.'),
            self::item('S4', 'socioeconomic', 'socioeconomic_context', 'I have reliable access to the internet for my academic requirements.'),
            self::item('S5', 'socioeconomic', 'socioeconomic_context', 'My financial situation allows me to focus on my studies.'),
            self::item('S6', 'socioeconomic', 'socioeconomic_context', 'I have sufficient financial support to continue my education.'),
            self::item('B1', 'behavioral', 'behavioral_engagement', 'I attend my scheduled classes regularly.'),
            self::item('B2', 'behavioral', 'behavioral_engagement', 'I set aside regular time to study or review my lessons outside class.'),
            self::item('B3', 'behavioral', 'behavioral_engagement', 'I actively participate in class activities and discussions when given the opportunity.'),
            self::item('B4', 'behavioral', 'behavioral_engagement', 'I make use of the learning materials and resources provided by my instructors when studying.'),
            self::item('B5', 'behavioral', 'behavioral_engagement', 'I complete my academic requirements within the given deadlines.'),
            self::item('B6', 'behavioral', 'behavioral_engagement', 'I continue working toward my academic goals even when I experience difficulties in my studies.'),
            self::item('C1', 'competence', 'competence', 'I can communicate my ideas clearly to other people.'),
            self::item('C2', 'competence', 'competence', 'I can work effectively with other people in a team.'),
            self::item('C3', 'competence', 'competence', 'I can identify problems and develop appropriate solutions.'),
            self::item('C4', 'competence', 'competence', 'I can adapt to new tools, technologies, and changing situations.'),
            self::item('C5', 'competence', 'competence', 'I can use digital tools and technology effectively for academic or practical tasks.'),
            self::item('C6', 'competence', 'competence', 'I can apply what I learned in school to practical tasks or real-world situations.'),
            self::item('C7', 'competence', 'competence', 'I can organize my work and responsibilities to complete assigned tasks.'),
            self::item('C8', 'competence', 'competence', 'I can learn new skills when a task or situation requires them.'),
            self::item('P1', 'psychological_capital', 'psychological_capital', 'I am confident that I can successfully handle difficult tasks related to my studies or future work.'),
            self::item('P2', 'psychological_capital', 'psychological_capital', 'When I encounter a problem, I can think of different ways to overcome it.'),
            self::item('P3', 'psychological_capital', 'psychological_capital', 'I continue working toward my goals even after experiencing setbacks.'),
            self::item('P4', 'psychological_capital', 'psychological_capital', 'I generally expect that my efforts toward my studies and career will lead to positive results.'),
            self::item('P5', 'psychological_capital', 'psychological_capital', 'I can recover and continue after academic or personal difficulties affect my progress.'),
            self::item('P6', 'psychological_capital', 'psychological_capital', 'I believe I can improve my abilities through effort, learning, and experience.'),
            self::item('E1', 'self_perceived_employability', 'self_perceived_employability', 'I feel prepared to apply for a job related to my degree program.'),
            self::item('E2', 'self_perceived_employability', 'self_perceived_employability', 'I believe I can demonstrate useful skills to a potential employer.'),
            self::item('E3', 'self_perceived_employability', 'self_perceived_employability', 'I can explain how my knowledge and experiences are relevant to a future job.'),
            self::item('E4', 'self_perceived_employability', 'self_perceived_employability', 'I am confident that I can continue developing the skills needed for my chosen career.'),
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
