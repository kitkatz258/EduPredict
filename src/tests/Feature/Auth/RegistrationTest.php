<?php

namespace Tests\Feature\Auth;

use App\Livewire\Tables\InstitutionStudentsTable;
use App\Models\AuditLog;
use App\Models\InstitutionStudent;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertRedirect(route('login'));
        $this->post('/register', [
            'student_number' => '2024-77777',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => 1,
            'email' => 'lina@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'consent' => '1',
        ])->assertNotFound();
    }

    public function test_an_administrator_can_issue_a_student_login_with_a_temporary_password(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['is_active' => true]);
        $record = InstitutionStudent::factory()->create([
            'student_number' => '2024-77777',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $program->id,
            'email' => 'lina@example.com',
            'is_registered' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(InstitutionStudentsTable::class)
            ->call('openProvision', $record->id)
            ->set('provisionEmail', 'lina@example.com')
            ->call('issueLogin')
            ->assertHasNoErrors()
            ->assertSet('provisioningId', null);

        $user = User::query()->where('email', 'lina@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('REYES', (string) $user->password));
        $this->assertFalse(Hash::check('Reyes', (string) $user->password));
        $this->assertDatabaseHas('institution_students', [
            'student_number' => '2024-77777',
            'is_registered' => 1,
        ]);
        $this->assertDatabaseHas('students', ['student_number' => '2024-77777']);
        $provisionLog = AuditLog::query()->where('action', 'student_account_provisioned')->latest('id')->first();
        $this->assertNotNull($provisionLog);
        $this->assertStringNotContainsString('REYES', json_encode($provisionLog->meta));
        $this->assertStringNotContainsString('password', strtolower((string) json_encode($provisionLog->meta)));

        auth()->logout();

        $this->post('/login', [
            'login' => '2024-77777',
            'password' => 'REYES',
        ])->assertRedirect(route('student.dashboard', absolute: false));

        $this->get(route('student.dashboard'))->assertRedirect(route('password.forced'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->must_change_password);
    }

    public function test_issuing_a_login_twice_does_not_create_a_duplicate_account(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['is_active' => true]);
        $record = InstitutionStudent::factory()->create([
            'student_number' => '2024-88888',
            'program_id' => $program->id,
            'email' => 'once@example.com',
            'is_registered' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(InstitutionStudentsTable::class)
            ->call('openProvision', $record->id)
            ->set('provisionEmail', 'once@example.com')
            ->call('issueLogin')
            ->assertHasNoErrors();

        $users = User::query()->count();
        $students = Student::query()->count();

        Livewire::actingAs($admin)
            ->test(InstitutionStudentsTable::class)
            ->call('openProvision', $record->fresh()->id)
            ->assertSet('provisioningId', null);

        $this->assertSame($users, User::query()->count());
        $this->assertSame($students, Student::query()->count());
    }
}
