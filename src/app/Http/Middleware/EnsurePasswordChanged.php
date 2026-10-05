<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password) {
            if ($request->routeIs('password.forced', 'password.forced.update', 'logout')) {
                return $next($request);
            }

            return redirect()->route('password.forced');
        }

        return $next($request);
    }
}
