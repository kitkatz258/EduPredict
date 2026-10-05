<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_states_the_placeholder_and_qualitative_limits(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('placeholder-heuristic-v0')
            ->assertSee('not a final trained machine-learning model')
            ->assertSee('no percentage')
            ->assertSee('not a clinical or diagnostic assessment')
            ->assertSee('AI may only phrase');
    }

    public function test_missing_and_forbidden_pages_use_the_branded_errors(): void
    {
        config(['app.debug' => false]);

        $this->get('/not-a-page')
            ->assertNotFound()
            ->assertSee('That page is not in EduPredict');

        $student = Student::factory()->create();
        $this->actingAs($student->user)
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('You do not have access');

        $this->view('errors.500')->assertSee('Something went wrong');
    }
}
