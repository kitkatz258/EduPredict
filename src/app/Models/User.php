<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'college_id',
        'department_id',
        'program_id',
        'is_active',
        'consented_at',
        'last_login_at',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'consented_at' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(AccountDeletionRequest::class);
    }

    public function isRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canSignIn(): bool
    {
        return $this->is_active && ! $this->role->isLegacy();
    }

    /**
     * Active department heads whose student-level scope includes the student.
     */
    public function scopeReviewersOf(Builder $query, Student $student): Builder
    {
        $student->loadMissing('program');

        return $query
            ->where('role', UserRole::DepartmentHead)
            ->where('is_active', true)
            ->where(function (Builder $scope) use ($student): void {
                $scope->where('program_id', $student->program_id)
                    ->orWhere(function (Builder $departmentWide) use ($student): void {
                        $departmentWide->whereNull('program_id')
                            ->whereNotNull('department_id')
                            ->where('department_id', $student->program?->department_id);
                    });
            });
    }
}
