<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Program;
use Illuminate\Database\Seeder;

class CollegeProgramSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'College of Computer Studies',
                'code' => 'CCS',
                'programs' => [
                    ['name' => 'Bachelor of Science in Information Technology', 'code' => 'BSIT'],
                    ['name' => 'Bachelor of Science in Computer Science', 'code' => 'BSCS'],
                    ['name' => 'Bachelor of Science in Information Systems', 'code' => 'BSIS'],
                ],
            ],
            [
                'name' => 'College of Business Administration',
                'code' => 'CBA',
                'programs' => [
                    ['name' => 'Bachelor of Science in Business Administration', 'code' => 'BSBA'],
                    ['name' => 'Bachelor of Science in Accountancy', 'code' => 'BSA'],
                ],
            ],
            [
                'name' => 'College of Education',
                'code' => 'CED',
                'programs' => [
                    ['name' => 'Bachelor of Secondary Education', 'code' => 'BSED'],
                    ['name' => 'Bachelor of Elementary Education', 'code' => 'BEED'],
                ],
            ],
            [
                'name' => 'College of Engineering',
                'code' => 'COE',
                'programs' => [
                    ['name' => 'Bachelor of Science in Computer Engineering', 'code' => 'BSCpE'],
                ],
            ],
            [
                'name' => 'College of Liberal Arts',
                'code' => 'CLA',
                'programs' => [
                    ['name' => 'Bachelor of Arts in Communication', 'code' => 'ABCOMM'],
                ],
            ],
        ];

        foreach ($catalog as $collegeData) {
            $college = College::query()->updateOrCreate(
                ['code' => $collegeData['code']],
                ['name' => $collegeData['name']],
            );

            foreach ($collegeData['programs'] as $programData) {
                Program::query()->updateOrCreate(
                    ['code' => $programData['code']],
                    [
                        'college_id' => $college->id,
                        'name' => $programData['name'],
                    ],
                );
            }
        }
    }
}
