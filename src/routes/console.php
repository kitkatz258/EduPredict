<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Seeders use updateOrCreate and would reset changed passwords, so a deployment
// seeds only an empty database and never re-runs over live accounts.
Artisan::command('edupredict:seed-demo-if-empty {--force : Allow seeding in production}', function () {
    if (User::query()->exists()) {
        $this->info('Users already exist. Demo seed skipped.');

        return 0;
    }

    return $this->call('db:seed', ['--force' => (bool) $this->option('force')]);
})->purpose('Seed the synthetic demo data only when the database has no users');
