<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CollegeForm;
use App\Livewire\Admin\InstitutionStudentForm;
use App\Livewire\Admin\InstitutionStudentImport;
use App\Livewire\Tables\UsersTable;
use App\Models\College;
use App\Models\InstitutionStudent;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireActionReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dialog_close_uses_the_host_method_instead_of_the_clicked_element(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::actingAs($admin)
            ->test(CollegeForm::class)
            ->call('startCreate')
            ->assertSee('closeMethod()', false)
            ->assertSee('$wire.$call(method)', false)
            ->assertSee('data-dialog-close="closeForm"', false)
            ->assertDontSee('this.$el.dataset.dialogClose', false)
            ->assertDontSee('this.$wire.call(', false);
    }

    public function test_college_save_persists_and_an_out_of_scope_user_cannot_save(): void
    {
        $admin = User::factory()->administrator()->create();
        $head = User::factory()->departmentHead()->create();

        Livewire::actingAs($admin)
            ->test(CollegeForm::class)
            ->call('startCreate')
            ->set('name', 'College of Temporary Studies')
            ->set('code', 'cts')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'College added.');

        $this->assertDatabaseHas('colleges', [
            'name' => 'College of Temporary Studies',
            'code' => 'CTS',
        ]);

        Livewire::actingAs($head)
            ->test(CollegeForm::class)
            ->assertForbidden();
    }

    public function test_account_activation_persists(): void
    {
        $admin = User::factory()->administrator()->create();
        $head = User::factory()->departmentHead()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->call('toggleActive', $head->id)
            ->assertHasNoErrors();

        $this->assertFalse($head->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(UsersTable::class)
            ->call('toggleActive', $head->id);

        $this->assertTrue($head->fresh()->is_active);
    }

    public function test_csv_import_reports_counts_and_rejects_an_invalid_file_without_writing_rows(): void
    {
        Storage::fake('local');
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['code' => 'BSIS']);
        InstitutionStudent::factory()->create([
            'student_number' => '2024-55555',
            'program_id' => $program->id,
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'students.csv',
            "\xEF\xBB\xBFstudent_number,last_name,first_name,program_code,year_level\n".
            "2024-55555,Cruz,Ana,BSIS,2\n".
            "2024-66666,Reyes,Lina,BSIS,3\n".
            "2024-66667,Reyes,No,BSIS,9\n"
        );

        Livewire::actingAs($admin)
            ->test(InstitutionStudentImport::class)
            ->set('csv', $csv)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('examined', 3)
            ->assertSet('imported', 1)
            ->assertSet('skipped', 1)
            ->assertSee('Skipped duplicate student number 2024-55555')
            ->assertSee('Year level must be between 1 and 6');

        $this->assertDatabaseHas('institution_students', [
            'student_number' => '2024-66666',
            'program_id' => $program->id,
            'is_registered' => 0,
        ]);

        $invalid = UploadedFile::fake()->create('notes.pdf', 8, 'application/pdf');
        $before = InstitutionStudent::query()->count();

        Livewire::actingAs($admin)
            ->test(InstitutionStudentImport::class)
            ->call('openImport')
            ->set('csv', $invalid)
            ->call('import')
            ->assertHasErrors('csv')
            ->assertSet('imported', 0);

        $this->assertSame($before, InstitutionStudent::query()->count());
    }

    public function test_manual_eligibility_does_not_create_an_account_and_unknown_numbers_still_cannot_register(): void
    {
        $admin = User::factory()->administrator()->create();
        $program = Program::factory()->create(['code' => 'BSIT', 'is_active' => true]);
        $legacy = Program::factory()->legacy()->create(['code' => 'BSBA']);
        $accounts = User::query()->count();

        Livewire::actingAs($admin)
            ->test(InstitutionStudentForm::class)
            ->call('startCreate')
            ->set('studentNumber', '2024-42424')
            ->set('lastName', 'Dizon')
            ->set('firstName', 'Mara')
            ->set('programId', $program->id)
            ->set('yearLevel', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($accounts, User::query()->count());
        $this->assertDatabaseHas('institution_students', [
            'student_number' => '2024-42424',
            'last_name' => 'Dizon',
            'first_name' => 'Mara',
            'program_id' => $program->id,
            'is_registered' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(InstitutionStudentForm::class)
            ->call('startCreate')
            ->set('studentNumber', '2024-42424')
            ->set('lastName', 'Dizon')
            ->set('firstName', 'Mara')
            ->set('programId', $program->id)
            ->set('yearLevel', 2)
            ->call('save')
            ->assertHasErrors('studentNumber');

        Livewire::actingAs($admin)
            ->test(InstitutionStudentForm::class)
            ->call('startCreate')
            ->set('studentNumber', '2024-42425')
            ->set('lastName', 'Dizon')
            ->set('firstName', 'Mara')
            ->set('programId', $legacy->id)
            ->set('yearLevel', 2)
            ->call('save')
            ->assertHasErrors('programId');

        $this->assertDatabaseMissing('institution_students', ['student_number' => '2024-42425']);

        auth()->logout();

        $this->post('/register', [
            'student_number' => '2024-99991',
            'last_name' => 'Ghost',
            'first_name' => 'Noone',
            'program_id' => $program->id,
            'email' => 'ghost-m28@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'consent' => '1',
        ])->assertSessionHasErrors('student_number');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ghost-m28@example.com']);

        Livewire::actingAs(User::factory()->departmentHead()->create())
            ->test(InstitutionStudentForm::class)
            ->assertForbidden();
    }

    public function test_college_rows_are_not_removed_by_a_save(): void
    {
        $admin = User::factory()->administrator()->create();
        $college = College::factory()->create(['name' => 'Kept College', 'code' => 'KEEP']);

        Livewire::actingAs($admin)
            ->test(CollegeForm::class)
            ->call('startEdit', $college->id)
            ->set('name', 'Kept College Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('colleges', [
            'id' => $college->id,
            'code' => 'KEEP',
            'name' => 'Kept College Renamed',
        ]);
        $this->assertSame(1, College::query()->whereKey($college->id)->count());
    }
}
