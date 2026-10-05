<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    /** @use HasFactory<\Database\Factories\ProgramFactory> */
    use HasFactory;

    protected $fillable = [
        'college_id',
        'name',
        'code',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
