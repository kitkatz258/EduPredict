<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireAnswer extends Model
{
    protected $fillable = [
        'questionnaire_response_id',
        'questionnaire_item_id',
        'value',
        'protected_value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'protected_value' => 'encrypted',
        ];
    }

    /**
     * Likert value actually given. Socioeconomic answers are stored encrypted
     * in protected_value; the plain value column is 0 for those rows.
     */
    public function responseValue(): ?int
    {
        if ($this->protected_value !== null && $this->protected_value !== '') {
            $value = (int) $this->protected_value;

            return $value >= 1 && $value <= 5 ? $value : null;
        }

        $value = (int) $this->value;

        return $value >= 1 && $value <= 5 ? $value : null;
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireResponse::class, 'questionnaire_response_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireItem::class, 'questionnaire_item_id');
    }
}
