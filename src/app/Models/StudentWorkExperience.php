<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentWorkExperience extends Model
{
    /** @use HasFactory<\Database\Factories\StudentWorkExperienceFactory> */
    use Archivable, HasFactory;

    public const TYPE_OJT_INTERNSHIP = 'ojt_internship';

    protected $fillable = [
        'student_id',
        'experience_type',
        'organization',
        'role_title',
        'start_date',
        'end_date',
        'is_ongoing',
        'description',
        'legacy_source',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_ongoing' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function isInternship(): bool
    {
        return $this->experience_type === self::TYPE_OJT_INTERNSHIP;
    }

    public function typeLabel(): string
    {
        return (string) (config('edupredict.profile.experience_types')[$this->experience_type] ?? str_replace('_', ' ', $this->experience_type));
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
