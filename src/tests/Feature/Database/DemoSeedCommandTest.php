<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_is_seeded_with_the_configured_password(): void
    {
        config(['edupredict.demo.password' => 'Deploy-Secret-42']);

        $this->artisan('edupredict:seed-demo-if-empty', ['--force' => true])->assertSuccessful();

        $admin = User::query()->where('email', 'admin@edupredict.test')->firstOrFail();
        $this->assertTrue(Hash::check('Deploy-Secret-42', $admin->password));
        $this->assertFalse(Hash::check(DemoUserSeeder::PASSWORD, $admin->password));
    }

    public function test_existing_users_are_left_untouched(): void
    {
        $user = User::factory()->create(['email' => 'admin@edupredict.test', 'password' => 'Changed-After-Deploy-1']);

        $this->artisan('edupredict:seed-demo-if-empty', ['--force' => true])
            ->expectsOutputToContain('Demo seed skipped')
            ->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertTrue(Hash::check('Changed-After-Deploy-1', $user->fresh()->password));
    }

    public function test_default_password_is_the_documented_development_password(): void
    {
        config(['edupredict.demo.password' => '']);

        $this->assertSame(DemoUserSeeder::PASSWORD, DemoUserSeeder::password());
    }
}
