<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'login',
            'subject_type' => User::class,
            'subject_id' => 1,
            'meta' => [],
            'ip' => '127.0.0.1',
        ];
    }
}
