<?php

namespace App\Models;

use App\Enums\UserRole;
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

    /**
     * Students whose individual records the user may open.
     * Deans and legacy faculty accounts have no student-level access.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Student => $query->where('students.user_id', $user->id),
            UserRole::DepartmentHead => $this->departmentHeadScope($query, $user),
            UserRole::Administrator => $query,
            UserRole::Dean, UserRole::Faculty => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Students the user may count in aggregate analytics (never listed by name).
     */
    public function scopeAggregatableBy(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::DepartmentHead => $this->departmentHeadScope($query, $user),
            UserRole::Dean => $query->whereIn(
                'students.program_id',
                Program::query()->select('id')->where('college_id', $user->college_id ?? 0)->where('is_active', true),
            ),
            UserRole::Administrator => $query->whereIn(
                'students.program_id',
                Program::query()->select('id')->where('is_active', true),
            ),
            UserRole::Student, UserRole::Faculty => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * A department head sees their whole department, or only their program
     * when the account is narrowed to one program.
     */
    private function departmentHeadScope(Builder $query, User $user): Builder
    {
        if ($user->program_id !== null) {
            return $query->where('students.program_id', $user->program_id);
        }

        if ($user->department_id !== null) {
            return $query->whereIn(
                'students.program_id',
                Program::query()->select('id')->where('department_id', $user->department_id),
            );
        }

        return $query->whereRaw('1 = 0');
    }
}
