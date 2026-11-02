<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionnaireVersion extends Model
{
    public const STATE_DRAFT = 'draft';

    public const STATE_PUBLISHED = 'published';

    public const CONTRACT_SIX_BY_TEN = 'six-by-ten';

    protected $fillable = [
        'code',
        'name',
        'state',
        'current_slot',
        'source_code',
        'change_summary',
        'panel_approved',
        'approval_evidence',
        'scoring',
        'reverse_scoring',
        'navigation',
        'sections',
        'contract',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'current_slot' => 'integer',
            'panel_approved' => 'boolean',
            'reverse_scoring' => 'boolean',
            'sections' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuestionnaireItem::class, 'definition_version', 'code');
    }

    public function isCurrent(): bool
    {
        return (int) $this->current_slot === 1;
    }

    public function isMutable(): bool
    {
        return $this->state === self::STATE_DRAFT;
    }

    public function shortLabel(): string
    {
        return match ($this->code) {
            'ucc-panel-revised-60-v2' => 'UCC 60-Item · v2',
            'ucc-panel-approved-v1' => 'UCC 36-Item · v1',
            'draft-v1' => 'Draft research · v1',
            default => $this->name,
        };
    }

    public function stateLabel(): string
    {
        if ($this->isCurrent()) {
            return 'Active';
        }

        if ($this->state === self::STATE_DRAFT) {
            return 'Draft';
        }

        return 'Historical';
    }

    public function selectorLabel(int $count): string
    {
        $noun = $count === 1 ? 'item' : 'items';

        return $this->shortLabel().' · '.$this->stateLabel().' · '.$count.' '.$noun;
    }
}
