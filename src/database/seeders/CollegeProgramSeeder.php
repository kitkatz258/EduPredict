<?php

namespace Database\Seeders;

use App\Services\Academic\ClasStructureSynchronizer;
use Illuminate\Database\Seeder;

class CollegeProgramSeeder extends Seeder
{
    /**
     * CLAS college, its departments, and the eight pilot programs.
     */
    public function run(ClasStructureSynchronizer $structure): void
    {
        $structure->sync();
    }
}
