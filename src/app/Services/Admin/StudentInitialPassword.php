<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Validation\ValidationException;

/**
 * University-style initial student password: the registered last name in uppercase.
 * Internal spaces and legitimate surname punctuation are preserved.
 */
final class StudentInitialPassword
{
    public static function fromLastName(string $lastName): string
    {
        $password = mb_strtoupper(trim($lastName), 'UTF-8');
        if ($password === '') {
            throw ValidationException::withMessages([
                'lastName' => 'A last name is required to create the initial password.',
            ]);
        }

        return $password;
    }
}
