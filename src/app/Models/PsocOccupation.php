<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PsocOccupationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsocOccupation extends Model
{
    /** @use HasFactory<PsocOccupationFactory> */
    use HasFactory;

    protected $fillable = [
        'psoc_code',
        'title',
        'major_group',
        'description',
        'skill_tags',
        'related_program_codes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'skill_tags' => 'array',
            'related_program_codes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function careerMatches(): HasMany
    {
        return $this->hasMany(CareerMatch::class);
    }
}
