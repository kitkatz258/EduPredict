<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prediction extends Model
{
    /** @use HasFactory<\Database\Factories\PredictionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'requested_by',
        'model_version',
        'employability_score',
        'dropout_probability',
        'dropout_risk',
        'confidence',
        'program_shift_flag',
        'factors',
        'feature_snapshot',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'employability_score' => 'float',
            'dropout_probability' => 'float',
            'factors' => 'array',
            'feature_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function careerMatches(): HasMany
    {
        return $this->hasMany(CareerMatch::class);
    }

    public function recommendedActions(): HasMany
    {
        return $this->hasMany(RecommendedAction::class);
    }
}
