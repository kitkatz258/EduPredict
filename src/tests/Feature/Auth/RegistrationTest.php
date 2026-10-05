<?php

namespace Tests\Feature\Auth;

use App\Models\InstitutionStudent;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk()->assertSee('Informed consent');
    }

    public function test_listed_student_can_register_with_consent(): void
    {
        $program = Program::factory()->create();
        InstitutionStudent::factory()->create([
            'student_number' => '2024-77777',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $program->id,
            'is_registered' => false,
        ]);

        $this->post('/register', [
            'student_number' => '2024-77777',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $program->id,
            'email' => 'lina@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'consent' => '1',
        ])->assertRedirect(route('student.dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('institution_students', [
            'student_number' => '2024-77777',
            'is_registered' => 1,
        ]);
        $this->assertDatabaseHas('consents', [
            'version' => 'v1',
        ]);
    }

    public function test_unknown_student_number_cannot_register(): void
    {
        $program = Program::factory()->create();

        $this->post('/register', [
            'student_number' => 'NOPE-000',
            'last_name' => 'Ghost',
            'first_name' => 'Noone',
            'program_id' => $program->id,
            'email' => 'ghost@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'consent' => '1',
        ])->assertSessionHasErrors('student_number');

        $this->assertGuest();
    }

    public function test_registration_requires_consent(): void
    {
        $program = Program::factory()->create();
        InstitutionStudent::factory()->create([
            'student_number' => '2024-77778',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $program->id,
        ]);

        $this->post('/register', [
            'student_number' => '2024-77778',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $program->id,
            'email' => 'lina2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('consent');
    }

    public function test_already_registered_student_number_cannot_register_again(): void
    {
        $program = Program::factory()->create();
        InstitutionStudent::factory()->create([
            'student_number' => '2024-77779',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $program->id,
            'is_registered' => true,
        ]);

        $this->post('/register', [
            'student_number' => '2024-77779',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $program->id,
            'email' => 'lina3@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'consent' => '1',
        ])->assertSessionHasErrors('student_number');

        $this->assertGuest();
    }

    public function test_name_or_program_mismatch_cannot_register(): void
    {
        $program = Program::factory()->create();
        $other = Program::factory()->create();
        InstitutionStudent::factory()->create([
            'student_number' => '2024-77780',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $program->id,
        ]);

        $this->post('/register', [
            'student_number' => '2024-77780',
            'last_name' => 'Santos',
            'first_name' => 'Lina',
            'program_id' => $program->id,
            'email' => 'lina4@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'consent' => '1',
        ])->assertSessionHasErrors('last_name');

        $this->post('/register', [
            'student_number' => '2024-77780',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $other->id,
            'email' => 'lina5@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'consent' => '1',
        ])->assertSessionHasErrors('program_id');

        $this->assertGuest();
    }
}
