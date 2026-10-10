<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Program;
use Illuminate\Contracts\View\View;

/**
 * Sign In and Create Account share one card so the toggle can switch panels in place.
 */
final class AuthAccessView
{
    public static function make(string $mode): View
    {
        return view('auth.access', [
            'mode' => $mode === 'register' ? 'register' : 'login',
            'programs' => Program::query()->inPilotScope()->orderBy('name')->get(),
            'consentVersion' => config('edupredict.consent.current_version'),
        ]);
    }
}
