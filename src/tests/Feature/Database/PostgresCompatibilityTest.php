<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\College;
use App\Models\User;
use App\Services\Database\MariaDbToPostgresCopier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_boolean_false_round_trips(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'must_change_password' => true,
        ]);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->is_active);
        $this->assertTrue($fresh->must_change_password);
    }

    public function test_email_uniqueness_ignores_case(): void
    {
        User::factory()->create(['email' => 'Case.User@example.com']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'case.user@example.com']);
    }

    public function test_explicit_primary_keys_leave_the_sequence_ahead_of_the_copied_id(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Sequence repair is checked when the suite runs on PostgreSQL.');
        }

        DB::table('colleges')->insert([
            'id' => 40,
            'name' => 'Sequence College',
            'code' => 'SEQ40',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(MariaDbToPostgresCopier::class)->syncSequences(DB::connection());

        $next = College::query()->create([
            'name' => 'Next College',
            'code' => 'SEQ41',
            'is_active' => false,
        ]);

        $this->assertSame(41, $next->id);
        $this->assertFalse($next->fresh()->is_active);
    }
}
