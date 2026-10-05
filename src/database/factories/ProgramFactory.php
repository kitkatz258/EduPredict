<?php

namespace Database\Factories;

use App\Models\College;
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
            'name' => 'Bachelor of Science in '.fake()->unique()->word(),
            'code' => 'BS'.strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
