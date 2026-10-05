<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'original_file_path',
        'detected_gpa',
        'warnings',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'detected_gpa' => 'float',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subjectGrades(): HasMany
    {
        return $this->hasMany(SubjectGrade::class);
    }
}
