<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SocioeconomicProfile;
use App\Models\User;

class SocioeconomicProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->ownsAStudentRecord($user);
    }

    public function view(User $user, SocioeconomicProfile $profile): bool
    {
        return $this->owns($user, $profile->student?->user_id);
    }

    public function create(User $user): bool
    {
        return $this->ownsAStudentRecord($user);
    }

    public function update(User $user, SocioeconomicProfile $profile): bool
    {
        return $this->owns($user, $profile->student?->user_id);
    }

    private function ownsAStudentRecord(User $user): bool
    {
        return $user->isRole(UserRole::Student) && $user->student !== null;
    }

    private function owns(User $user, ?int $ownerId): bool
    {
        return $this->ownsAStudentRecord($user) && $ownerId === $user->id;
    }
}
