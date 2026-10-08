<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Confirmed reports are never edited in place. Updating a term confirms a new
 * version and stamps the previous one `superseded_at`, so earlier predictions
 * keep pointing at the grades they actually used.
 */
class GradeReport extends Model
{
    /** @use HasFactory<\Database\Factories\GradeReportFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'school_year',
        'semester',
        'source',
        'status',
        'version',
        'supersedes_id',
        'original_file_path',
        'detected_gpa',
        'warnings',
        'confirmed_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'superseded_at' => 'datetime',
            'detected_gpa' => 'float',
            'version' => 'integer',
            'warnings' => 'array',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    public function isCurrent(): bool
    {
        return $this->isConfirmed() && ! $this->isSuperseded();
    }

    /**
     * Confirmed and not replaced by a newer version: the grades on file.
     *
     * @param  Builder<GradeReport>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', 'confirmed')->whereNull('superseded_at');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subjectGrades(): HasMany
    {
        return $this->hasMany(SubjectGrade::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id');
    }
}
