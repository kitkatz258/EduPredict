<?php

namespace Database\Factories;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountDeletionRequest>
 */
class AccountDeletionRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reason' => 'I no longer want my account.',
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'admin_note' => null,
        ];
    }
}
