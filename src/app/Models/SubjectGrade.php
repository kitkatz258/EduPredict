<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectGrade extends Model
{
    /** @use HasFactory<\Database\Factories\SubjectGradeFactory> */
    use HasFactory;

    protected $fillable = [
        'grade_report_id',
        'subject_code',
        'subject_name',
        'units',
        'midterm_grade',
        'final_exam_grade',
        'final_grade',
        'remarks',
        'is_failed',
        'is_major_subject',
        'needs_review',
    ];

    protected function casts(): array
    {
        return [
            'units' => 'float',
            'is_failed' => 'boolean',
            'is_major_subject' => 'boolean',
            'needs_review' => 'boolean',
        ];
    }

    public function gradeReport(): BelongsTo
    {
        return $this->belongsTo(GradeReport::class);
    }
}
