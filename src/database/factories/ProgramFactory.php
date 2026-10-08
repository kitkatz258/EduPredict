<?php

namespace Database\Factories;

use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'college_id' => College::factory(),
            'department_id' => fn (array $attributes) => Department::factory()->create([
                'college_id' => $attributes['college_id'],
            ])->id,
            'name' => 'Bachelor of Science in '.fake()->unique()->word(),
            'code' => 'BS'.strtoupper(fake()->unique()->lexify('???')),
            'is_active' => true,
        ];
    }

    public function legacy(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
