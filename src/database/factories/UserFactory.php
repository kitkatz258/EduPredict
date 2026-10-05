<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Student,
            'college_id' => null,
            'program_id' => null,
            'is_active' => true,
            'consented_at' => now(),
            'last_login_at' => null,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    public function faculty(?Program $program = null): static
    {
        return $this->state(function () use ($program) {
            $program ??= Program::factory()->create();

            return [
                'role' => UserRole::Faculty,
                'program_id' => $program->id,
                'college_id' => $program->college_id,
            ];
        });
    }

    public function departmentHead(?Program $program = null): static
    {
        return $this->state(function () use ($program) {
            $program ??= Program::factory()->create();

            return [
                'role' => UserRole::DepartmentHead,
                'program_id' => $program->id,
                'college_id' => $program->college_id,
            ];
        });
    }

    public function dean(?College $college = null): static
    {
        return $this->state(function () use ($college) {
            $college ??= College::factory()->create();

            return [
                'role' => UserRole::Dean,
                'college_id' => $college->id,
                'program_id' => null,
            ];
        });
    }

    public function administrator(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Administrator,
            'college_id' => null,
            'program_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
