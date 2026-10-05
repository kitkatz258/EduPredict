<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionnaireResponse extends Model
{
    /** @use HasFactory<\Database\Factories\QuestionnaireResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'submitted_at',
        'construct_scores',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'construct_scores' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuestionnaireAnswer::class);
    }
}
