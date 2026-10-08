<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCertification extends Model
{
    /** @use HasFactory<\Database\Factories\StudentCertificationFactory> */
    use Archivable, HasFactory;

    protected $fillable = [
        'student_id',
        'title',
        'issuer',
        'issued_year',
        'issued_on',
        'expires_on',
        'credential_reference',
        'description',
        'legacy_source',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_year' => 'integer',
            'issued_on' => 'date',
            'expires_on' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
