<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\InstitutionStudentImport;
use App\Livewire\Tables\AuditLogsTable;
use App\Livewire\Tables\DeletionRequestsTable;
use App\Livewire\Tables\InterventionsTable;
use App\Livewire\Tables\PsocOccupationsTable;
use App\Livewire\Tables\QuestionnaireItemsTable;
use App\Livewire\Tables\UsersTable;
use App\Models\AccountDeletionRequest;
use App\Models\AuditLog;
use App\Models\InstitutionStudent;
use App\Models\Intervention;
use App\Models\PsocOccupation;
use App\Models\QuestionnaireItem;
use App\Models\SocioeconomicProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUxPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_row_actions_use_icon_buttons(): void
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->departmentHead()->create(['name' => 'Reviewable Head']);
        QuestionnaireItem::factory()->create();
        PsocOccupation::factory()->create();
        Intervention::factory()->create();

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->assertSee('ri-pencil-line', false)
            ->assertSee('ri-forbid-2-line', false)
            ->assertSee('Deactivate')
            ->assertSee('data-confirm-title="Deactivate this account?"', false)
            ->assertDontSee('underline');

        Livewire::actingAs($admin)
            ->test(QuestionnaireItemsTable::class)
            ->assertSee('Category')
            ->assertDontSee('>Construct<', false)
            ->assertSee('ri-pencil-line', false)
            ->assertSee('Deactivate');

        Livewire::actingAs($admin)
            ->test(PsocOccupationsTable::class)
            ->assertSee('ri-eye-line', false)
            ->assertSee('View')
            ->assertSee('Edit');

        Livewire::actingAs($admin)
            ->test(InterventionsTable::class)
            ->assertSee('ri-eye-line', false)
            ->assertSee('View');
    }

    public function test_eligible_student_import_opens_from_a_modal(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.institution-students'))
            ->assertOk()
            ->assertSee('Import CSV')
            ->assertDontSee('Drop a CSV file here')
            ->assertDontSee('id="eligible-csv"', false);

        Livewire::actingAs($admin)
            ->test(InstitutionStudentImport::class)
            ->assertSet('open', false)
            ->call('openImport')
            ->assertSee('Drop a CSV file here')
            ->assertSee('or click to browse')
            ->assertSee('student_number, last_name, first_name, program_code, year_level')
            ->assertSee('dialog-panel', false);
    }

    public function test_activity_log_uses_readable_headings_and_record_labels(): void
    {
        $admin = User::factory()->administrator()->create(['name' => 'Ada Admin']);
        $profile = SocioeconomicProfile::factory()->create([
            'household_size' => 'SECRET-HOUSEHOLD-77',
        ]);
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'login',
            'subject_type' => User::class,
            'subject_id' => $admin->id,
            'meta' => [],
            'ip' => '10.1.1.1',
        ]);
        AuditLog::query()->create([
            'user_id' => null,
            'action' => 'institution_students_imported',
            'subject_type' => InstitutionStudent::class,
            'subject_id' => null,
            'meta' => ['imported' => 0],
            'ip' => null,
        ]);
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'profile_saved',
            'subject_type' => SocioeconomicProfile::class,
            'subject_id' => $profile->id,
            'meta' => [],
            'ip' => '10.1.1.1',
        ]);
        AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'missing_subject',
            'subject_type' => User::class,
            'subject_id' => 999999,
            'meta' => [],
            'ip' => '10.1.1.1',
        ]);

        Livewire::actingAs($admin)
            ->test(AuditLogsTable::class)
            ->assertSee('Date & time')
            ->assertSee('Performed by')
            ->assertSee('Affected record')
            ->assertSee('IP address')
            ->assertDontSee('>When<', false)
            ->assertDontSee('>Actor<', false)
            ->assertDontSee('>Subject<', false)
            ->assertSee('Ada Admin')
            ->assertSee('Eligible student (no specific record)')
            ->assertSee('Socioeconomic profile')
            ->assertSee('User (no longer available)')
            ->assertSee('System')
            ->assertDontSee('User 999999')
            ->assertDontSee('SECRET-HOUSEHOLD-77')
            ->assertDontSee('InstitutionStudent');
    }

    public function test_deletion_requests_use_a_sliding_status_switch_and_review_modal(): void
    {
        $admin = User::factory()->administrator()->create();
        $request = AccountDeletionRequest::factory()->create();

        Livewire::actingAs($admin)
            ->test(DeletionRequestsTable::class)
            ->assertSee('segment-indicator', false)
            ->assertSee('Pending')
            ->assertSee('Approved')
            ->assertSee('Rejected')
            ->assertSee('All')
            ->assertSee('ri-file-search-line', false)
            ->call('openReview', $request->id)
            ->assertSee('dialog-panel', false)
            ->assertSee('Review account deletion request')
            ->assertSee('data-dialog-close="closeReview"', false);
    }
}
