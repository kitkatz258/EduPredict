<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\CollegeForm;
use App\Livewire\Admin\DepartmentForm;
use App\Livewire\Admin\InstitutionStudentForm;
use App\Livewire\Admin\InstitutionStudentImport;
use App\Livewire\Admin\InterventionForm;
use App\Livewire\Admin\ProgramForm;
use App\Livewire\Admin\PsocOccupationForm;
use App\Livewire\Admin\QuestionnaireItemForm;
use App\Livewire\Tables\InstitutionStudentsTable;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\InstitutionStudent;
use App\Models\Program;
use App\Models\User;
use App\Services\Admin\StudentAccountProvisioner;
use App\Services\Admin\StudentInitialPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class Milestone41_1Test extends TestCase
{
    use RefreshDatabase;

    public function test_initial_passwords_keep_spaces_and_surname_punctuation(): void
    {
        $this->assertSame('DELA CRUZ', StudentInitialPassword::fromLastName('Dela Cruz'));
        $this->assertSame("O'BRIEN", StudentInitialPassword::fromLastName("O'Brien"));
        $this->assertSame('DELA-CRUZ', StudentInitialPassword::fromLastName('Dela-Cruz'));
        $this->assertSame('ÑOÑO', StudentInitialPassword::fromLastName('Ñoño'));
    }

    public function test_adding_an_eligible_student_provisions_a_usable_login(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(InstitutionStudentForm::class)
            ->call('startCreate')
            ->set('studentNumber', '2024-41001')
            ->set('lastName', 'Dela Cruz')
            ->set('firstName', 'Rico')
            ->set('programId', $program->id)
            ->set('yearLevel', 2)
            ->set('email', 'rico.m411@example.com')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('show', true)
            ->assertNotDispatched('success-dialog');

        $this->assertDatabaseMissing('students', ['student_number' => '2024-41001']);

        Livewire::actingAs($admin)
            ->test(InstitutionStudentForm::class)
            ->call('startCreate')
            ->set('studentNumber', '2024-41001')
            ->set('lastName', 'Dela Cruz')
            ->set('firstName', 'Rico')
            ->set('programId', $program->id)
            ->set('yearLevel', 2)
            ->set('email', 'rico.m411@example.com')
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('success-dialog', title: 'Successfully added.');

        $user = User::query()->where('email', 'rico.m411@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('DELA CRUZ', (string) $user->password));
        $this->assertDatabaseHas('institution_students', [
            'student_number' => '2024-41001',
            'is_registered' => 1,
        ]);
        $this->assertDatabaseHas('students', ['student_number' => '2024-41001']);
    }

    public function test_csv_import_provisions_new_rows_and_skips_claimed_duplicates_without_resetting_passwords(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['code' => 'BSIS', 'is_active' => true]);
        $existing = InstitutionStudent::factory()->create([
            'student_number' => '2024-41010',
            'last_name' => 'Santos',
            'program_id' => $program->id,
            'email' => 'kept.m411@example.com',
            'is_registered' => false,
        ]);
        $provisioned = app(StudentAccountProvisioner::class)->provision($existing, $admin, 'kept.m411@example.com');
        $originalHash = (string) $provisioned['user']->password;

        $csv = UploadedFile::fake()->createWithContent(
            'students.csv',
            "student_number,last_name,first_name,program_code,year_level,email\n".
            "2024-41010,Changed,Ada,{$program->code},2,new.m411@example.com\n".
            "2024-41011,O'Brien,Pat,{$program->code},3,pat.m411@example.com\n".
            "2024-41011,O'Brien,Pat,{$program->code},3,pat.dup@example.com\n".
            "2024-41012,Cruz,No,{$program->code},9\n"
        );

        Livewire::actingAs($admin)
            ->test(InstitutionStudentImport::class)
            ->set('csv', $csv)
            ->call('import')
            ->assertSet('imported', 1)
            ->assertSet('skipped', 2)
            ->assertSee('Skipped duplicate student number 2024-41010')
            ->assertSee('Skipped duplicate student number 2024-41011')
            ->assertSee('Year level must be an integer from 1 to 4');

        $this->assertSame('Santos', $existing->fresh()->last_name);
        $this->assertSame($originalHash, (string) $provisioned['user']->fresh()->password);
        $this->assertTrue(Hash::check('SANTOS', $originalHash));

        $imported = User::query()->where('email', 'pat.m411@example.com')->first();
        $this->assertNotNull($imported);
        $this->assertTrue($imported->must_change_password);
        $this->assertTrue(Hash::check("O'BRIEN", (string) $imported->password));
        $this->assertDatabaseMissing('institution_students', ['student_number' => '2024-41012']);
        $this->assertDatabaseMissing('users', ['email' => 'new.m411@example.com']);
    }

    public function test_a_new_student_must_change_the_surname_password_and_accept_consent(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['is_active' => true]);
        $record = InstitutionStudent::factory()->create([
            'student_number' => '2024-41020',
            'last_name' => 'Reyes',
            'first_name' => 'Lina',
            'program_id' => $program->id,
            'email' => 'lina.m411@example.com',
            'is_registered' => false,
        ]);
        app(StudentAccountProvisioner::class)->provision($record, $admin, 'lina.m411@example.com');

        $this->post('/login', [
            'login' => '2024-41020',
            'password' => 'REYES',
        ])->assertRedirect(route('student.dashboard', absolute: false));

        $this->get(route('student.dashboard'))->assertRedirect(route('password.forced'));
        $this->assertTrue(auth()->user()?->must_change_password);

        $this->post(route('password.forced.update'), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'consent' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse(auth()->user()?->fresh()->must_change_password);
        $this->assertNotNull(auth()->user()?->fresh()->consented_at);
        $this->assertTrue(Hash::check('NewPassword123!', (string) auth()->user()->fresh()->password));
        $this->assertFalse(Hash::check('REYES', (string) auth()->user()->fresh()->password));

        auth()->logout();

        $this->post('/login', [
            'login' => '2024-41020',
            'password' => 'REYES',
        ])->assertSessionHasErrors('login');

        $this->post('/login', [
            'login' => '2024-41020',
            'password' => 'NewPassword123!',
        ])->assertRedirect(route('student.dashboard', absolute: false));
        $this->get(route('student.dashboard'))->assertOk();
    }

    public function test_duplicate_student_numbers_are_rejected_and_issue_login_does_not_reset_claimed_accounts(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(InstitutionStudentForm::class)
            ->call('startCreate')
            ->set('studentNumber', '2024-41030')
            ->set('lastName', 'Ramos')
            ->set('firstName', 'Liza')
            ->set('programId', $program->id)
            ->set('yearLevel', 1)
            ->set('email', 'liza.m411@example.com')
            ->call('confirmSave')
            ->assertHasNoErrors();

        $hash = (string) User::query()->where('email', 'liza.m411@example.com')->value('password');

        Livewire::actingAs($admin)
            ->test(InstitutionStudentForm::class)
            ->call('startCreate')
            ->set('studentNumber', '2024-41030')
            ->set('lastName', 'Other')
            ->set('firstName', 'Name')
            ->set('programId', $program->id)
            ->set('yearLevel', 1)
            ->call('save')
            ->assertHasErrors('studentNumber');

        $this->assertSame($hash, (string) User::query()->where('email', 'liza.m411@example.com')->value('password'));

        $claimed = InstitutionStudent::query()->where('student_number', '2024-41030')->firstOrFail();
        Livewire::actingAs($admin)
            ->test(InstitutionStudentsTable::class)
            ->call('openProvision', $claimed->id)
            ->assertSet('provisioningId', null);

        $meta = json_encode(AuditLog::query()->where('action', 'student_account_provisioned')->latest('id')->first()?->meta);
        $this->assertStringNotContainsString('RAMOS', (string) $meta);
        $this->assertStringNotContainsString('password', strtolower((string) $meta));
    }

    public function test_admin_add_and_edit_forms_share_validate_then_confirm_then_persist(): void
    {
        $admin = User::factory()->administrator()->create();
        $college = College::factory()->create();

        foreach ([CollegeForm::class, DepartmentForm::class, ProgramForm::class, InterventionForm::class, PsocOccupationForm::class, QuestionnaireItemForm::class] as $form) {
            $this->assertContains('confirmSave', get_class_methods($form));
        }

        Livewire::actingAs($admin)
            ->test(CollegeForm::class)
            ->call('startCreate')
            ->set('name', 'College of Confirmation')
            ->call('save')
            ->assertHasErrors('code')
            ->assertSet('show', true)
            ->assertNotDispatched('success-dialog');

        $this->assertDatabaseMissing('colleges', ['name' => 'College of Confirmation']);

        $component = Livewire::actingAs($admin)
            ->test(CollegeForm::class)
            ->call('startCreate')
            ->set('name', 'College of Confirmation')
            ->set('code', 'cfc')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('show', true)
            ->assertNotDispatched('success-dialog');

        $this->assertDatabaseMissing('colleges', ['code' => 'CFC']);

        $component
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('success-dialog', title: 'Successfully added.');

        $this->assertDatabaseHas('colleges', ['code' => 'CFC', 'name' => 'College of Confirmation']);
    }
}
