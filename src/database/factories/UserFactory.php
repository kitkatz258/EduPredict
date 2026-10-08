<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\Department;
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

    /**
     * A historical faculty account. The role grants no access.
     */
    public function legacyFaculty(?Program $program = null): static
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

    /**
     * Passing a Department gives department-wide scope; passing a Program
     * narrows the head to that one program inside its department.
     */
    public function departmentHead(Department|Program|null $scope = null): static
    {
        return $this->state(function () use ($scope) {
            $scope ??= Department::factory()->create();

            if ($scope instanceof Program) {
                return [
                    'role' => UserRole::DepartmentHead,
                    'college_id' => $scope->college_id,
                    'department_id' => $scope->department_id,
                    'program_id' => $scope->id,
                ];
            }

            return [
                'role' => UserRole::DepartmentHead,
                'college_id' => $scope->college_id,
                'department_id' => $scope->id,
                'program_id' => null,
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
