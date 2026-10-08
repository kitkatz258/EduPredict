<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Models\Student;
use App\Models\StudentCertification;
use App\Models\StudentSkill;
use App\Models\StudentWorkExperience;

/**
 * Reads the structured skills, certifications, and work-experience entries.
 * Entries count only while the section is saved (not a draft) and not archived.
 */
final class SkillsExperienceRecords
{
    public const SNAPSHOT_VERSION = 1;

    public function sectionSaved(Student $student): bool
    {
        $section = $student->skillsExperience()->first();

        return $section !== null && ! $section->is_draft;
    }

    /**
     * @return array{technical_skills: int, certifications: int, internships: int, work_experience: int}
     */
    public function counts(Student $student): array
    {
        if (! $this->sectionSaved($student)) {
            return ['technical_skills' => 0, 'certifications' => 0, 'internships' => 0, 'work_experience' => 0];
        }

        $internship = StudentWorkExperience::TYPE_OJT_INTERNSHIP;

        return [
            'technical_skills' => $student->skills()->active()->count(),
            'certifications' => $student->certifications()->active()->count(),
            'internships' => $student->workExperiences()->active()->where('experience_type', $internship)->count(),
            'work_experience' => $student->workExperiences()->active()->where('experience_type', '!=', $internship)->count(),
        ];
    }

    /**
     * The exact entries a prediction used. Stored on the prediction and never rewritten.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Student $student): array
    {
        $saved = $this->sectionSaved($student);

        return [
            'version' => self::SNAPSHOT_VERSION,
            'section_saved' => $saved,
            'technical_skills' => $saved
                ? $student->skills()->active()->orderBy('name')->get()
                    ->map(fn (StudentSkill $skill): array => ['name' => $skill->name])->values()->all()
                : [],
            'certifications' => $saved
                ? $student->certifications()->active()->orderByDesc('issued_year')->orderBy('title')->get()
                    ->map(fn (StudentCertification $row): array => [
                        'title' => $row->title,
                        'issuer' => $row->issuer,
                        'issued_year' => $row->issued_year,
                        'issued_on' => $row->issued_on?->toDateString(),
                        'expires_on' => $row->expires_on?->toDateString(),
                        'credential_reference' => $row->credential_reference,
                        'description' => $row->description,
                    ])->values()->all()
                : [],
            'work_experience' => $saved
                ? $student->workExperiences()->active()->orderByDesc('start_date')->orderBy('organization')->get()
                    ->map(fn (StudentWorkExperience $row): array => [
                        'experience_type' => $row->experience_type,
                        'organization' => $row->organization,
                        'role_title' => $row->role_title,
                        'start_date' => $row->start_date?->toDateString(),
                        'end_date' => $row->end_date?->toDateString(),
                        'is_ongoing' => $row->is_ongoing,
                        'description' => $row->description,
                    ])->values()->all()
                : [],
        ];
    }

    /**
     * Skill and certification labels used for PSOC skill-tag overlap.
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    public function tags(array $snapshot): array
    {
        $tags = [];
        foreach ((array) ($snapshot['technical_skills'] ?? []) as $skill) {
            if (is_array($skill) && is_string($skill['name'] ?? null)) {
                $tags[] = $skill['name'];
            }
        }
        foreach ((array) ($snapshot['certifications'] ?? []) as $certification) {
            if (is_array($certification) && is_string($certification['title'] ?? null)) {
                $tags[] = $certification['title'];
            }
        }

        return array_values(array_unique($tags));
    }
}
