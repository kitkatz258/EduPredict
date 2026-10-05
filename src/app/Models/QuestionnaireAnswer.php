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
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
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
