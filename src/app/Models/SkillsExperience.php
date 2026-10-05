<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillsExperience extends Model
{
    /** @use HasFactory<\Database\Factories\SkillsExperienceFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'technical_skills',
        'certifications',
        'internships',
        'projects',
        'work_experience',
        'is_draft',
    ];

    protected function casts(): array
    {
        return [
            'technical_skills' => 'array',
            'certifications' => 'array',
            'internships' => 'array',
            'projects' => 'array',
            'work_experience' => 'array',
            'is_draft' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
