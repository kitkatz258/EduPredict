<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocioeconomicProfile extends Model
{
    /** @use HasFactory<\Database\Factories\SocioeconomicProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'household_income_bracket',
        'household_size',
        'scholarship_status',
        'employment_status',
        'living_arrangement',
        'has_internet',
        'has_device',
        'has_study_space',
        'is_draft',
    ];

    protected function casts(): array
    {
        return [
            'household_income_bracket' => 'encrypted',
            'household_size' => 'encrypted',
            'scholarship_status' => 'encrypted',
            'employment_status' => 'encrypted',
            'living_arrangement' => 'encrypted',
            'has_internet' => 'encrypted',
            'has_device' => 'encrypted',
            'has_study_space' => 'encrypted',
            'is_draft' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
