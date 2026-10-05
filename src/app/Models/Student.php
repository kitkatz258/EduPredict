<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_number',
        'program_id',
        'year_level',
        'adviser_id',
        'enrollment_year',
        'semesters_completed',
        'consent_version',
    ];

    protected function casts(): array
    {
        return [
            'year_level' => 'integer',
            'enrollment_year' => 'integer',
            'semesters_completed' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    public function gradeReports(): HasMany
    {
        return $this->hasMany(GradeReport::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function latestPrediction(): HasOne
    {
        return $this->hasOne(Prediction::class)->latestOfMany();
    }

    public function hasLimitedHistory(): bool
    {
        return $this->semesters_completed < (int) config('edupredict.prediction.limited_history_semesters', 2);
    }

    public function socioeconomicProfile(): HasOne
    {
        return $this->hasOne(SocioeconomicProfile::class);
    }

    public function skillsExperience(): HasOne
    {
        return $this->hasOne(SkillsExperience::class);
    }

    public function questionnaireResponses(): HasMany
    {
        return $this->hasMany(QuestionnaireResponse::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            \App\Enums\UserRole::Student => $query->where('user_id', $user->id),
            \App\Enums\UserRole::Faculty => $query->where('adviser_id', $user->id),
            \App\Enums\UserRole::DepartmentHead => $query->where('program_id', $user->program_id),
            \App\Enums\UserRole::Dean => $query->whereHas('program', fn (Builder $programs) => $programs->where('college_id', $user->college_id)),
            \App\Enums\UserRole::Administrator => $query,
        };
    }
}
