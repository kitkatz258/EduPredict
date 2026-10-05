<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionStudent extends Model
{
    /** @use HasFactory<\Database\Factories\InstitutionStudentFactory> */
    use HasFactory;

    protected $fillable = [
        'student_number',
        'last_name',
        'first_name',
        'program_id',
        'year_level',
        'birthdate',
        'email',
        'is_registered',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'is_registered' => 'boolean',
            'year_level' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
