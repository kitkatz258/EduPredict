<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Livewire\Analytics\DashboardAnalytics;
use App\Livewire\Tables\ScopedStudentsTable;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Intervention;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\RecommendedAction;
use App\Models\Student;
use App\Models\User;
use App\Services\Analytics\CohortAnalytics;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboards_use_the_latest_prediction_and_stay_inside_role_scope(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');

        $college = College::factory()->create();
        $otherCollege = College::factory()->create();
        $programA = Program::factory()->create(['college_id' => $college->id, 'code' => 'PROG-A', 'name' => 'Program A']);
        $programB = Program::factory()->create(['college_id' => $college->id, 'code' => 'PROG-B', 'name' => 'Program B']);
        $programC = Program::factory()->create(['college_id' => $otherCollege->id, 'code' => 'PROG-C', 'name' => 'Program C']);

        $head = User::factory()->departmentHead($programA)->create();
        $dean = User::factory()->dean($college)->create();
        $admin = User::factory()->administrator()->create();

        $low = $this->student($programA, 'S-LOW', 2);
        $high = $this->student($programA, 'S-HIGH', 3);
        $moderate = $this->student($programB, 'S-MOD', 2);
        $outside = $this->student($programC, 'S-OUT', 4);

        $this->prediction($low, '2024-01-15 09:00:00', 10, 'high', 'none');
        $this->prediction($low, '2026-01-15 09:00:00', 80, 'low', 'none');
        $this->prediction($high, '2026-02-01 09:00:00', 15, 'moderate', 'none');
        $latestHigh = $this->prediction($high, '2026-05-15 09:00:00', 40, 'high', 'program_fit');
        $this->prediction($moderate, '2026-03-10 09:00:00', 70, 'moderate', 'none');
        $this->prediction($outside, '2026-06-01 09:00:00', 99, 'high', 'none');

        RecommendedAction::factory()->create([
            'prediction_id' => $latestHigh->id,
            'intervention_id' => Intervention::factory()->create()->id,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);

        $analytics = app(CohortAnalytics::class);
        $programStats = $analytics->forUser($head);
        $this->assertSame(2, $programStats['students']);
        $this->assertSame(60.0, $programStats['average_employability']);
        $this->assertSame(1, $programStats['high_risk']);
        $this->assertSame(1, $programStats['risk']['low']);
        $this->assertSame(0, $programStats['risk']['moderate']);
        $this->assertSame(1, $programStats['program_shift']['program_fit']);
        $this->assertSame(3, $programStats['assessments_this_year']);
        $this->assertSame(['2026-01', '2026-05'], array_column($programStats['trend'], 'label'));
        $this->assertSame(['PROG-A'], array_column($programStats['programs'], 'code'));
        $this->assertSame(1, $programStats['unreviewed_high']);
        $this->assertSame(1, $programStats['program_concern']);

        $collegeStats = $analytics->forUser($dean);
        $this->assertSame(3, $collegeStats['students']);
        $this->assertSame(63.3, $collegeStats['average_employability']);
        $this->assertSame(4, $collegeStats['assessments_this_year']);
        $this->assertEqualsCanonicalizing(['PROG-A', 'PROG-B'], array_column($collegeStats['programs'], 'code'));

        $institution = $analytics->forUser($admin);
        $this->assertSame(4, $institution['students']);
        $this->assertSame(72.3, $institution['average_employability']);
        $this->assertSame(5, $institution['assessments_this_year']);
        $this->assertSame(2, $institution['high_risk']);

        $this->actingAs($head)
            ->get(route('department.dashboard'))
            ->assertOk()
            ->assertSee('Average employability is 60.0')
            ->assertSee('Total students: 2')
            ->assertSee('High-risk students: 1')
            ->assertSee('Assessments this year: 3')
            ->assertSee('Program-fit concern: 1')
            ->assertSee('2026-01')
            ->assertSee('2026-05')
            ->assertSee('PROG-A')
            ->assertDontSee('2024-01')
            ->assertDontSee('2026-02')
            ->assertDontSee('PROG-C')
            ->assertDontSee('S-OUT')
            ->assertDontSee('S-HIGH')
            ->assertDontSee('S-LOW')
            ->assertDontSee('S-MOD')
            ->assertDontSee('99.0');

        $this->actingAs($dean)
            ->get(route('dean.dashboard'))
            ->assertOk()
            ->assertSee('Average employability is 63.3')
            ->assertSee('PROG-A')
            ->assertSee('PROG-B')
            ->assertDontSee('PROG-C')
            ->assertDontSee('S-OUT')
            ->assertDontSee('S-LOW')
            ->assertDontSee('S-HIGH')
            ->assertDontSee('S-MOD')
            ->assertDontSee($high->user->name);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Average employability is 72.3')
            ->assertSee('PROG-C')
            ->assertDontSee('S-OUT')
            ->assertDontSee('S-HIGH')
            ->assertDontSee('S-LOW')
            ->assertDontSee('S-MOD');

        $this->actingAs($head)
            ->get(route('department.students'))
            ->assertOk()
            ->assertSee('S-HIGH')
            ->assertSee('S-LOW')
            ->assertDontSee('S-MOD')
            ->assertSee('1st Year')
            ->assertDontSee('Faculty Adviser');

        Livewire::actingAs($head)
            ->test(DashboardAnalytics::class)
            ->set('yearLevel', '3')
            ->assertSee('Average employability is 40.0')
            ->assertSee('Total students: 1')
            ->assertDontSee('PROG-C');

        Livewire::actingAs($dean)
            ->test(DashboardAnalytics::class)
            ->set('programId', (string) $programB->id)
            ->assertSee('Average employability is 70.0')
            ->assertSee('PROG-B')
            ->assertDontSee('PROG-C');

        Livewire::actingAs($head)
            ->test(DashboardAnalytics::class)
            ->set('programId', (string) $programC->id)
            ->assertSee('Average employability is 60.0')
            ->assertDontSee('PROG-C');

        Livewire::actingAs($low->user)
            ->test(DashboardAnalytics::class)
            ->assertForbidden();

        $latestHigh->recommendedActions()->update([
            'reviewed_by' => $head->id,
            'reviewed_at' => now(),
        ]);

        $this->assertSame(0, $analytics->forUser($head->fresh())['unreviewed_high']);
    }

    public function test_csv_export_is_the_current_scoped_view_and_is_audited(): void
    {
        $college = College::factory()->create();
        $program = Program::factory()->create(['college_id' => $college->id, 'code' => 'PROG-A']);
        $other = Program::factory()->create(['code' => 'PROG-C']);
        $head = User::factory()->departmentHead($program)->create();
        $dean = User::factory()->dean($college)->create();
        $low = $this->student($program, 'S-LOW', 2);
        $high = $this->student($program, 'S-HIGH', 3);
        $outside = $this->student($other, 'S-OUT', 4);
        $this->prediction($low, '2026-01-15 09:00:00', 80, 'low', 'none');
        $this->prediction($high, '2026-05-15 09:00:00', 40, 'high', 'program_fit');
        $this->prediction($outside, '2026-06-01 09:00:00', 99, 'high', 'none');

        $exported = Livewire::actingAs($head)
            ->test(ScopedStudentsTable::class)
            ->set('filters.dropout_risk', 'high')
            ->call('export')
            ->assertFileDownloaded('edupredict-students.csv');

        $content = base64_decode((string) data_get($exported->effects, 'download.content'));
        $this->assertStringContainsString('S-HIGH', $content);
        $this->assertStringContainsString('program_fit', $content);
        $this->assertStringNotContainsString('S-LOW', $content);
        $this->assertStringNotContainsString('S-OUT', $content);
        $this->assertStringNotContainsString($high->user->name, (string) AuditLog::query()->sole()->meta['search'] ?? '');

        $log = AuditLog::query()->sole();
        $this->assertSame('students.export', $log->action);
        $this->assertSame($head->id, $log->user_id);
        $this->assertSame(1, $log->meta['rows']);
        $this->assertSame('high', $log->meta['dropout_risk']);
        $this->assertArrayNotHasKey('name', $log->meta);
        $this->assertStringNotContainsString('S-HIGH', json_encode($log->meta));

        Livewire::actingAs($low->user)
            ->test(ScopedStudentsTable::class)
            ->assertForbidden();

        Livewire::actingAs($dean)
            ->test(ScopedStudentsTable::class)
            ->assertForbidden();
        $this->assertSame(1, AuditLog::query()->count(), 'a dean cannot export student rows');
    }

    public function test_seeded_dashboards_match_the_latest_prediction_rows(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@edupredict.test')->firstOrFail();
        $dean = User::query()->where('email', 'dean@edupredict.test')->firstOrFail();
        $head = User::query()->where('email', 'depthead@edupredict.test')->firstOrFail();
        $analytics = app(CohortAnalytics::class);

        $adminStats = $analytics->forUser($admin);
        $this->assertSame(Student::query()->count(), $adminStats['students']);
        $this->assertSame($this->counted($admin, 'high'), $adminStats['high_risk']);
        $this->assertSame($this->counted($admin, 'moderate'), $adminStats['risk']['moderate']);
        $this->assertSame($this->counted($admin, 'low'), $adminStats['risk']['low']);
        $this->assertSame(12, $adminStats['high_risk']);
        $this->assertSame(12, $adminStats['risk']['moderate']);
        $this->assertSame(36, $adminStats['risk']['low']);
        $this->assertSame(12, $adminStats['program_shift']['disengagement']);
        $this->assertSame(12, $adminStats['program_shift']['program_fit']);
        $this->assertSame(12, $adminStats['program_shift']['mixed']);
        $this->assertSame(24, $adminStats['program_shift']['none']);
        $this->assertSame(
            $adminStats['risk']['low'] + $adminStats['risk']['moderate'] + $adminStats['risk']['high'],
            $adminStats['with_prediction'],
        );

        $deanStats = $analytics->forUser($dean);
        $this->assertSame(Student::query()->count(), $deanStats['students'], 'every seeded student is in CLAS');
        $this->assertSame($this->counted($dean, 'high'), $deanStats['high_risk']);
        $this->assertCount(8, $deanStats['programs']);

        $headStudents = Student::query()->visibleTo($head)->count();
        $this->assertGreaterThan(0, $headStudents);
        $this->assertLessThan(Student::query()->count(), $headStudents);
        $this->assertSame($headStudents, $analytics->forUser($head)['students']);
        $this->assertSame($this->counted($head, 'high'), $analytics->forUser($head)['high_risk']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Total students: '.$adminStats['students']);
        $this->actingAs($dean)->get(route('dean.dashboard'))->assertOk()
            ->assertSee('BSIS')
            ->assertSee('BSPSYCH')
            ->assertDontSee('SYN-0001')
            ->assertDontSee('2024-00001')
            ->assertDontSee('Sam Student');
        $this->actingAs($head)->get(route('department.dashboard'))->assertOk()
            ->assertSee('Computer Studies')
            ->assertSee('BSIS')
            ->assertDontSee('BSPSYCH');

        $this->assertGreaterThan(1, count($adminStats['trend']));
    }

    private function student(Program $program, string $number, int $year): Student
    {
        $user = User::factory()->create([
            'name' => 'Student '.$number,
            'role' => \App\Enums\UserRole::Student,
        ]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'student_number' => $number,
            'year_level' => $year,
        ]);
    }

    private function prediction(Student $student, string $at, float $score, string $risk, string $flag): Prediction
    {
        return Prediction::factory()->create([
            'student_id' => $student->id,
            'requested_by' => $student->user_id,
            'employability_score' => $score,
            'dropout_risk' => $risk,
            'dropout_probability' => match ($risk) {
                'high' => 0.72,
                'moderate' => 0.41,
                default => 0.12,
            },
            'program_shift_flag' => $flag,
            'created_at' => $at,
        ]);
    }

    private function counted(User $user, string $risk): int
    {
        $count = 0;
        $students = Student::query()->aggregatableBy($user)->with('predictions')->get();
        foreach ($students as $student) {
            $latest = $student->predictions
                ->sortBy(fn (Prediction $prediction): string => sprintf('%010d-%010d', $prediction->created_at?->getTimestamp() ?? 0, $prediction->id))
                ->last();
            if ($latest?->dropout_risk === $risk) {
                $count++;
            }
        }

        return $count;
    }
}
