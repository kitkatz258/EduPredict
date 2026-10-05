<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $allowed = array_map(fn (string $role): UserRole => UserRole::from($role), $roles);

        if (! $user->isRole(...$allowed)) {
            abort(403);
        }

        return $next($request);
    }
}
