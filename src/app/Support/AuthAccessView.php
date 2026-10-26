<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\View\View;

/**
 * Guest sign-in uses a single login form. Student accounts are provisioned by administrators.
 */
final class AuthAccessView
{
    public static function make(string $mode = 'login'): View
    {
        return view('auth.access', [
            'mode' => 'login',
        ]);
    }
}
