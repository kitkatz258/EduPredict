<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_asks_for_a_student_number(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Student number')
            ->assertSee('name="login"', false)
            ->assertSee('Forgot password?')
            ->assertSee('Create Account')
            ->assertSee('Remember me')
            ->assertSee('Show password')
            ->assertSee('RA 10173')
            ->assertDontSee('UCC Email Address')
            ->assertDontSee('Faculty');
    }

    public function test_guests_opening_the_root_go_straight_to_sign_in(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_signed_in_users_opening_the_root_go_to_their_dashboard(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($student->user)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_students_sign_in_with_their_student_number(): void
    {
        $student = Student::factory()->create(['student_number' => '2024-55555']);

        $response = $this->post('/login', [
            'login' => '2024-55555',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($student->user);
        $response->assertRedirect(route('student.dashboard', absolute: false));
    }

    public function test_students_cannot_sign_in_with_their_email(): void
    {
        $student = Student::factory()->create(['student_number' => '2024-55556']);

        $this->post('/login', [
            'login' => $student->user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_staff_sign_in_with_their_email_in_any_letter_case(): void
    {
        $head = User::factory()->role(UserRole::DepartmentHead)->create(['email' => 'head@staff.test']);

        $this->post('/login', [
            'login' => 'Head@Staff.test',
            'password' => 'password',
        ])->assertRedirect(route('department.dashboard', absolute: false));

        $this->assertAuthenticatedAs($head);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        Student::factory()->create(['student_number' => '2024-55557']);

        $this->post('/login', [
            'login' => '2024-55557',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_unknown_student_numbers_get_the_generic_failure(): void
    {
        $this->post('/login', [
            'login' => '2099-00000',
            'password' => 'password',
        ])->assertSessionHasErrors(['login' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_inactive_students_cannot_sign_in(): void
    {
        $student = Student::factory()->create(['student_number' => '2024-55558']);
        $student->user->update(['is_active' => false]);

        $this->post('/login', [
            'login' => '2024-55558',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
