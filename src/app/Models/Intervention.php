<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intervention extends Model
{
    /** @use HasFactory<\Database\Factories\InterventionFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'description',
        'targets_factor',
        'min_risk_level',
    ];

    protected function casts(): array
    {
        return [
            'targets_factor' => 'array',
        ];
    }

    public function recommendedActions(): HasMany
    {
        return $this->hasMany(RecommendedAction::class);
    }
}
