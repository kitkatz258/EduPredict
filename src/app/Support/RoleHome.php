<?php

namespace App\Support;

use App\Models\User;

final class RoleHome
{
    public static function routeName(User $user): string
    {
        return $user->role->homeRoute();
    }

    public static function url(User $user): string
    {
        return route($user->role->homeRoute());
    }
}
