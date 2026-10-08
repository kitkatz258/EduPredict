<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Contracts\AiClientInterface;
use App\Enums\UserRole;
use App\Livewire\Admin\CollegeForm;
use App\Livewire\Admin\InterventionForm;
use App\Livewire\Admin\ProgramForm;
use App\Livewire\Admin\PsocOccupationForm;
use App\Livewire\Privacy\DeletionRequestForm;
use App\Livewire\Student\GradeReportForm;
use App\Livewire\Tables\AuditLogsTable;
use App\Livewire\Tables\DeletionRequestsTable;
use App\Models\AccountDeletionRequest;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_open_catalog_and_audit_screens(): void
    {
        $admin = User::factory()->administrator()->create();
        $student = Student::factory()->create();

        foreach (['admin.colleges', 'admin.interventions', 'admin.audit', 'admin.deletion-requests'] as $route) {
            $this->actingAs($student->user)->get(route($route))->assertForbidden();
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        Livewire::actingAs($student->user)->test(AuditLogsTable::class)->assertForbidden();
        Livewire::actingAs($admin)->test(AuditLogsTable::class)->assertOk();
    }

    public function test_an_administrator_can_add_a_college_program_occupation_and_intervention(): void
    {
        $admin = User::factory()->administrator()->create();
        $college = College::factory()->create();

        Livewire::actingAs($admin)->test(CollegeForm::class)
            ->set('name', 'College of Testing')
            ->set('code', 'cot')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('College added.');

        $this->assertDatabaseHas('colleges', ['code' => 'COT']);

        Livewire::actingAs($admin)->test(ProgramForm::class)
            ->set('collegeId', $college->id)
            ->set('name', 'Bachelor of Testing')
            ->set('code', 'bst')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('programs', ['code' => 'BST', 'college_id' => $college->id]);

        Livewire::actingAs($admin)->test(PsocOccupationForm::class)
            ->set('psocCode', '9999')
            ->set('title', 'Test analyst')
            ->set('majorGroup', 'Testing')
            ->set('description', 'A starter category.')
            ->set('skillTags', 'analysis, writing')
            ->set('programCodes', 'BST, BSIS')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('psoc_occupations', ['psoc_code' => '9999']);

        Livewire::actingAs($admin)->test(InterventionForm::class)
            ->set('code', 'library_hours')
            ->set('title', 'Library hours')
            ->set('description', 'Offer a scheduled study hour.')
            ->set('targets', 'study habits, gwa')
            ->set('minRiskLevel', 'moderate')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['study_habits', 'gwa'], \App\Models\Intervention::query()->where('code', 'library_hours')->firstOrFail()->targets_factor);
        $this->assertSame(4, AuditLog::query()->whereIn('action', [
            'college_saved',
            'program_saved',
            'psoc_saved',
            'intervention_saved',
        ])->count());
    }

    public function test_staff_views_of_a_student_record_are_audited_and_self_views_are_not(): void
    {
        $program = Program::factory()->create();
        $head = User::factory()->departmentHead($program)->create();
        $student = Student::factory()->create([
            'program_id' => $program->id,
        ]);

        $this->actingAs($head)->get(route('students.show', $student))->assertOk();
        $log = AuditLog::query()->where('action', 'student_record_viewed')->sole();
        $this->assertSame($student->id, $log->subject_id);
        $this->assertSame('department_head', $log->meta['role']);
        $this->assertArrayNotHasKey('household_income_bracket', $log->meta);

        $this->actingAs($student->user)->get(route('students.show', $student))->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'student_record_viewed')->count());
    }

    public function test_a_student_can_download_only_their_own_data_and_request_deletion(): void
    {
        $student = Student::factory()->create();
        $other = Student::factory()->create();
        Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'model_version' => 'placeholder-heuristic-v0',
        ]);
        $head = User::factory()->departmentHead()->create();

        $this->actingAs($head)->get(route('privacy.download.json'))->assertForbidden();

        $json = $this->actingAs($student->user)->get(route('privacy.download.json'));
        $json->assertOk();
        $body = $json->streamedContent();
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($student->student_number, $payload['student']['student_number']);
        $this->assertSame('placeholder-heuristic-v0', $payload['predictions'][0]['model_version']);
        $this->assertArrayNotHasKey('password', $payload['account']);
        $this->assertArrayNotHasKey('recommended_actions', $payload);
        $this->assertStringNotContainsString($other->student_number, $body);

        $pdf = $this->actingAs($student->user)->get(route('privacy.download.pdf'));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());
        $this->assertStringContainsString($student->student_number, $pdf->streamedContent());

        Livewire::actingAs($student->user)->test(DeletionRequestForm::class)
            ->set('reason', 'Please close my account.')
            ->call('submit')
            ->assertHasNoErrors()
            ->call('submit')
            ->assertHasErrors('reason');

        $request = AccountDeletionRequest::query()->where('user_id', $student->user_id)->sole();
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($head)->test(DeletionRequestsTable::class)->assertForbidden();
        Livewire::actingAs($admin)->test(DeletionRequestsTable::class)
            ->call('approve', $request->id);

        $this->assertFalse($student->user->fresh()->is_active);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertNotNull(Prediction::query()->where('student_id', $student->id)->first());
        $this->assertTrue(AuditLog::query()->where('action', 'account_deletion_processed')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'account_deactivated')->exists());
    }

    public function test_socioeconomic_columns_are_encrypted_at_rest(): void
    {
        $profile = SocioeconomicProfile::factory()->create([
            'household_income_bracket' => 'below_10k',
        ]);

        $raw = DB::table('socioeconomic_profiles')->where('id', $profile->id)->value('household_income_bracket');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('below_10k', $raw);
        $this->assertSame('below_10k', $profile->fresh()->household_income_bracket);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $user = User::factory()->role(UserRole::Administrator)->create([
            'email' => 'limit@edupredict.test',
            'password' => 'Password123!',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'login' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('login');
        }

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'Password123!',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_ai_calls_stop_at_the_per_minute_limit_and_never_send_when_the_key_is_empty(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => 'standard text']]],
            ]),
        ]);

        config([
            'edupredict.ai.api_key' => '',
            'edupredict.ai.model' => 'test-model:free',
        ]);
        $this->assertNull(app(AiClientInterface::class)->complete('Student name should not leave the app'));
        Http::assertNothingSent();

        config([
            'edupredict.ai.api_key' => 'test-key',
            'edupredict.ai.model' => 'test-model:free',
            'edupredict.ai.per_minute_limit' => 1,
            'edupredict.ai.daily_limit' => 40,
        ]);

        $client = app(AiClientInterface::class);
        $this->assertSame('standard text', $client->complete('de-identified payload'));
        $this->assertNull($client->complete('de-identified payload again'));
        Http::assertSentCount(1);
    }

    public function test_security_headers_csrf_and_session_timeout_are_in_place(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('name="_token"', false);

        $web = json_encode(app('router')->getMiddlewareGroups()['web']);
        $this->assertIsString($web);
        $this->assertStringContainsString('ValidateCsrfToken', $web);
        $this->assertSame(30, (int) config('session.lifetime'));
    }

    public function test_grade_uploads_reject_the_wrong_type_and_an_oversized_file(): void
    {
        $student = Student::factory()->create();

        Livewire::actingAs($student->user)->test(GradeReportForm::class)
            ->set('upload', UploadedFile::fake()->create('shell.php', 12, 'application/x-php'))
            ->call('parseUpload')
            ->assertHasErrors(['upload']);

        Livewire::actingAs($student->user)->test(GradeReportForm::class)
            ->set('upload', UploadedFile::fake()->create('grades.png', 11000, 'image/png'))
            ->call('parseUpload')
            ->assertHasErrors(['upload']);
    }

    public function test_the_privacy_page_shows_the_current_consent_version(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Current consent (v2)')
            ->assertSee('Your name, student number, email, and birthdate are not sent.')
            ->assertSee('The dean sees aggregated college figures only, never individual students.')
            ->assertDontSee('Faculty see only their advisees.');

        $head = User::factory()->departmentHead()->create();
        $this->actingAs($head)->get(route('privacy'))
            ->assertOk()
            ->assertDontSee('Download JSON');

        $student = Student::factory()->create();
        $this->actingAs($student->user)->get(route('privacy'))
            ->assertOk()
            ->assertSee('Download JSON');
    }
}
