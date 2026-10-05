<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionnaireItem extends Model
{
    /** @use HasFactory<\Database\Factories\QuestionnaireItemFactory> */
    use HasFactory;

    protected $fillable = [
        'construct',
        'text',
        'reverse_scored',
        'is_active',
        'is_draft',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'reverse_scored' => 'boolean',
            'is_active' => 'boolean',
            'is_draft' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuestionnaireAnswer::class);
    }
}
