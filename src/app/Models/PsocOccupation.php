<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsocOccupation extends Model
{
    /** @use HasFactory<\Database\Factories\PsocOccupationFactory> */
    use HasFactory;

    protected $fillable = [
        'psoc_code',
        'title',
        'major_group',
        'description',
        'skill_tags',
        'related_program_codes',
    ];

    protected function casts(): array
    {
        return [
            'skill_tags' => 'array',
            'related_program_codes' => 'array',
        ];
    }

    public function careerMatches(): HasMany
    {
        return $this->hasMany(CareerMatch::class);
    }
}
