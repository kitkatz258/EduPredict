<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_points_students_at_a_deletion_request_instead_of_erasing_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Request account deletion')
            ->assertSee(route('privacy').'#deletion-request', false)
            ->assertDontSee('permanently deleted', false);

        $this->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ])
            ->assertRedirect(route('privacy'))
            ->assertSessionHas('error');

        $this->assertAuthenticated();
        $this->assertNotNull($user->fresh());
    }

    public function test_staff_profile_does_not_delete_the_account(): void
    {
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('deactivated by an administrator')
            ->assertDontSee('permanently deleted', false);

        $this->actingAs($user)
            ->delete('/profile')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
