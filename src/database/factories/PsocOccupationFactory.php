<?php

namespace Database\Factories;

use App\Models\PsocOccupation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PsocOccupation>
 */
class PsocOccupationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'psoc_code' => fake()->unique()->numerify('####'),
            'title' => fake()->jobTitle(),
            'major_group' => 'Information and communications technology',
            'description' => 'Starter occupational category for development data.',
            'skill_tags' => ['programming', 'sql'],
            'related_program_codes' => ['BSIS', 'BSIT', 'BSCS'],
        ];
    }
}
