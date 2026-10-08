<?php

namespace Database\Factories;

use App\Models\College;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'college_id' => College::factory(),
            'name' => 'Department of '.ucfirst(fake()->unique()->word()),
            'code' => 'D-'.strtoupper(fake()->unique()->lexify('????')),
            'is_active' => true,
        ];
    }
}
