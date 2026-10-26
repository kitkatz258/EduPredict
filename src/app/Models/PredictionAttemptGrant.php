<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditable extra prediction attempts for one Philippine calendar date.
 * M37 stores the rows. M38 is the administrator screen that creates them.
 */
class PredictionAttemptGrant extends Model
{
    protected $fillable = [
        'student_id',
        'manila_date',
        'extra_attempts',
        'granted_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'manila_date' => 'date',
            'extra_attempts' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
