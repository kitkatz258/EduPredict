<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerMatch extends Model
{
    /** @use HasFactory<\Database\Factories\CareerMatchFactory> */
    use HasFactory;

    protected $fillable = [
        'prediction_id',
        'psoc_occupation_id',
        'compatibility_score',
        'explanation',
        'explanation_source',
        'matched_skills',
        'missing_skills',
    ];

    protected function casts(): array
    {
        return [
            'compatibility_score' => 'float',
            'matched_skills' => 'array',
            'missing_skills' => 'array',
        ];
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }

    public function occupation(): BelongsTo
    {
        return $this->belongsTo(PsocOccupation::class, 'psoc_occupation_id');
    }
}
