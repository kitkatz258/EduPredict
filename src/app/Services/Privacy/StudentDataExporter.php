<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\Student;

/**
 * A student's own copy of stored data. Institutional action text stays with staff.
 */
class StudentDataExporter
{
    /**
     * @return array<string, mixed>
     */
    public function forStudent(Student $student): array
    {
        $student->load([
            'user.consents',
            'program.college',
            'gradeReports.subjectGrades',
            'socioeconomicProfile',
            'skillsExperience',
            'skills',
            'certifications',
            'workExperiences',
            'questionnaireResponses.answers.item',
            'predictions.careerMatches.occupation',
        ]);

        $user = $student->user;

        return [
            'exported_at' => now('Asia/Manila')->toIso8601String(),
            'notice' => 'This file is your copy of data EduPredict stores about you. Specific institutional support actions are held by your department head and are not included. Prediction figures are estimates from the recorded model version, not a final trained model unless that version says so.',
            'account' => [
                'name' => $user?->name,
                'email' => $user?->email,
                'role' => $user?->role?->value,
                'consented_at' => $user?->consented_at?->toIso8601String(),
            ],
            'consents' => $user?->consents->map(fn ($consent): array => [
                'version' => $consent->version,
                'accepted_at' => $consent->accepted_at?->toIso8601String(),
            ])->values()->all() ?? [],
            'student' => [
                'student_number' => $student->student_number,
                'program' => $student->program?->code,
                'college' => $student->program?->college?->code,
                'year_level' => $student->year_level,
                'enrollment_year' => $student->enrollment_year,
                'semesters_completed' => $student->semesters_completed,
                'consent_version' => $student->consent_version,
            ],
            'grades' => $student->gradeReports->map(fn ($report): array => [
                'school_year' => $report->school_year,
                'semester' => $report->semester,
                'source' => $report->source,
                'status' => $report->status,
                'confirmed_at' => $report->confirmed_at?->toIso8601String(),
                'subjects' => $report->subjectGrades->map(fn ($grade): array => [
                    'subject_code' => $grade->subject_code,
                    'subject_name' => $grade->subject_name,
                    'units' => $grade->units,
                    'midterm_grade' => $grade->midterm_grade,
                    'final_exam_grade' => $grade->final_exam_grade,
                    'final_grade' => $grade->final_grade,
                    'remarks' => $grade->remarks,
                    'is_failed' => $grade->is_failed,
                    'is_major_subject' => $grade->is_major_subject,
                ])->values()->all(),
            ])->values()->all(),
            'socioeconomic' => $student->socioeconomicProfile?->only([
                'household_income_bracket',
                'household_size',
                'scholarship_status',
                'employment_status',
                'living_arrangement',
                'has_internet',
                'has_device',
                'has_study_space',
                'is_draft',
            ]),
            'skills' => [
                'section_is_draft' => $student->skillsExperience?->is_draft,
                'technical_skills' => $student->skills->map(fn ($skill): array => [
                    'name' => $skill->name,
                    'archived_at' => $skill->archived_at?->toIso8601String(),
                ])->values()->all(),
                'certifications' => $student->certifications->map(fn ($row): array => [
                    'title' => $row->title,
                    'issuer' => $row->issuer,
                    'issued_year' => $row->issued_year,
                    'issued_on' => $row->issued_on?->toDateString(),
                    'expires_on' => $row->expires_on?->toDateString(),
                    'credential_reference' => $row->credential_reference,
                    'description' => $row->description,
                    'archived_at' => $row->archived_at?->toIso8601String(),
                ])->values()->all(),
                'work_experience' => $student->workExperiences->map(fn ($row): array => [
                    'experience_type' => $row->experience_type,
                    'organization' => $row->organization,
                    'role_title' => $row->role_title,
                    'start_date' => $row->start_date?->toDateString(),
                    'end_date' => $row->end_date?->toDateString(),
                    'is_ongoing' => $row->is_ongoing,
                    'description' => $row->description,
                    'archived_at' => $row->archived_at?->toIso8601String(),
                ])->values()->all(),
                'legacy_entries' => $student->skillsExperience?->only([
                    'technical_skills',
                    'certifications',
                    'internships',
                    'projects',
                    'work_experience',
                ]),
            ],
            'questionnaire' => $student->questionnaireResponses->map(fn ($response): array => [
                'submitted_at' => $response->submitted_at?->toIso8601String(),
                'construct_scores' => $response->construct_scores,
                'answers' => $response->answers->map(fn ($answer): array => [
                    'construct' => $answer->item?->construct,
                    'value' => $answer->value,
                ])->values()->all(),
            ])->values()->all(),
            'predictions' => $student->predictions->map(fn ($prediction): array => [
                'model_version' => $prediction->model_version,
                'employability_score' => $prediction->employability_score,
                'dropout_probability' => $prediction->dropout_probability,
                'dropout_risk' => $prediction->dropout_risk,
                'confidence' => $prediction->confidence,
                'program_shift_flag' => $prediction->program_shift_flag,
                'created_at' => $prediction->created_at?->toIso8601String(),
                'factors' => $prediction->factors,
                'career_matches' => $prediction->careerMatches->map(fn ($match): array => [
                    'psoc_code' => $match->occupation?->psoc_code,
                    'title' => $match->occupation?->title,
                    'compatibility_score' => $match->compatibility_score,
                    'explanation_source' => $match->explanation_source,
                    'explanation' => $match->explanation,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
