<?php

namespace Database\Seeders;

use App\Models\Intervention;
use Illuminate\Database\Seeder;

class InterventionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'academic_tutoring',
                'title' => 'Academic tutoring',
                'description' => 'A faculty adviser can refer the student to scheduled subject tutoring. This is an option to discuss, not a decision about academic standing.',
                'targets_factor' => ['gwa', 'failed_subjects'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'peer_mentoring',
                'title' => 'Peer mentoring',
                'description' => 'A peer mentor can meet with the student about staying connected to the program. An adviser decides whether to offer the referral.',
                'targets_factor' => ['engagement', 'motivation'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'financial_aid_referral',
                'title' => 'Financial-aid referral',
                'description' => 'The student can be pointed to the campus financial-aid office to ask about aid options. Staff do not see household income in this suggestion.',
                'targets_factor' => ['income_bracket'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'scholarship_advising',
                'title' => 'Scholarship advising',
                'description' => 'An adviser can help the student look at scholarship options the campus already offers. This is a referral, not an award.',
                'targets_factor' => ['scholarship', 'income_bracket'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'guidance_counselling',
                'title' => 'Guidance counselling referral',
                'description' => 'A faculty adviser can offer a referral to the guidance office for a supportive conversation. This is not a clinical or diagnostic assessment.',
                'targets_factor' => ['motivation', 'engagement', 'procrastination', 'general'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'study_skills_workshop',
                'title' => 'Study-skills workshop',
                'description' => 'The student can be invited to a study-skills workshop. An adviser decides whether the invitation fits.',
                'targets_factor' => ['study_habits'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'time_management_coaching',
                'title' => 'Time-management coaching',
                'description' => 'Short coaching on planning study time can be offered. It is a support option, not a judgment of the student.',
                'targets_factor' => ['time_management', 'procrastination'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'ojt_placement',
                'title' => 'OJT and internship placement support',
                'description' => 'The department can help the student look for an OJT or internship placement. This is not a job offer.',
                'targets_factor' => ['internships'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'career_guidance',
                'title' => 'Career-guidance session',
                'description' => 'A career-guidance session can walk through broad occupational directions. It does not promise employment.',
                'targets_factor' => ['certifications', 'technical_skills', 'projects'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'adviser_check_in',
                'title' => 'Adviser check-in',
                'description' => 'The assigned adviser can schedule a check-in to talk through how the term is going. No automated action follows from it.',
                'targets_factor' => ['engagement', 'motivation', 'disengagement', 'general'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'program_fit_conversation',
                'title' => 'Program-fit conversation',
                'description' => 'An adviser can talk with the student about whether the program still fits. This follows the qualitative program-shift indicator. It is not a trained model and it has no percentage.',
                'targets_factor' => ['program_fit'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'attendance_monitoring',
                'title' => 'Attendance monitoring',
                'description' => 'The adviser can agree on a short period of attendance follow-up with the student. It is a support check, not a disciplinary record.',
                'targets_factor' => ['engagement', 'disengagement'],
                'min_risk_level' => 'high',
            ],
            [
                'code' => 'device_access_support',
                'title' => 'Learning-device access support',
                'description' => 'The student can be told how to ask about a shared learning device on campus. This suggestion does not include device ownership details.',
                'targets_factor' => ['device_access'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'internet_access_support',
                'title' => 'Internet access support',
                'description' => 'The student can be pointed to campus places with a reliable connection. An adviser decides whether to mention it.',
                'targets_factor' => ['internet_access'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'study_space_referral',
                'title' => 'Study-space referral',
                'description' => 'The student can be invited to use a campus study space. This is a practical option, not a finding about the household.',
                'targets_factor' => ['study_space'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'workload_review',
                'title' => 'Workload review',
                'description' => 'An adviser can talk with the student about balancing work hours and the current subject load. It does not change enrollment by itself.',
                'targets_factor' => ['employment_status'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'skills_coaching',
                'title' => 'Skills coaching',
                'description' => 'Coaching can focus on a skill or certification the profile does not show yet. It is practice support, not an employability guarantee.',
                'targets_factor' => ['technical_skills', 'certifications'],
                'min_risk_level' => 'moderate',
            ],
            [
                'code' => 'engagement_reconnect',
                'title' => 'Campus engagement reconnect',
                'description' => 'The student can be invited back into a class routine, a peer group, or an adviser conversation. The invitation is supportive.',
                'targets_factor' => ['engagement', 'disengagement'],
                'min_risk_level' => 'moderate',
            ],
        ];

        foreach ($rows as $row) {
            Intervention::query()->updateOrCreate(
                ['code' => $row['code']],
                $row,
            );
        }
    }
}
