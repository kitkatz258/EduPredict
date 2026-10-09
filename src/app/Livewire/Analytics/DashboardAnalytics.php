<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Models\Program;
use App\Models\Student;
use App\Services\Analytics\CohortAnalytics;
use Illuminate\View\View;
use Livewire\Component;

class DashboardAnalytics extends Component
{
    public string $yearLevel = '';

    public string $programId = '';

    public string $departmentId = '';

    public string $period = '';

    public function mount(): void
    {
        $this->authorizeStaff();
    }

    public function updatedYearLevel(): void
    {
        if (! in_array($this->yearLevel, ['', '1', '2', '3', '4'], true)) {
            $this->yearLevel = '';
        }

        $this->publishCharts();
    }

    public function updatedProgramId(): void
    {
        if ($this->programId !== '' && ! ctype_digit($this->programId)) {
            $this->programId = '';
        }

        $this->publishCharts();
    }

    public function updatedDepartmentId(): void
    {
        if ($this->departmentId !== '' && ! ctype_digit($this->departmentId)) {
            $this->departmentId = '';
        }

        if ($this->programId !== '' && $this->departmentId !== '') {
            $belongs = Program::query()
                ->whereKey((int) $this->programId)
                ->where('department_id', (int) $this->departmentId)
                ->exists();

            if (! $belongs) {
                $this->programId = '';
            }
        }

        $this->publishCharts();
    }

    public function updatedPeriod(): void
    {
        if (! in_array($this->period, ['', 'this_year', 'last_12_months'], true)) {
            $this->period = '';
        }

        $this->publishCharts();
    }

    public function render(CohortAnalytics $analytics): View
    {
        $this->authorizeStaff();
        $user = auth()->user();
        $stats = $analytics->forUser($user, $this->year(), $this->program(), $this->department(), $this->period());

        return view('livewire.analytics.dashboard-analytics', [
            'stats' => $stats,
            'programs' => $analytics->programsFor($user),
            'departmentOptions' => $analytics->departmentsFor($user),
            'charts' => $stats['charts'],
        ]);
    }

    private function publishCharts(): void
    {
        $this->authorizeStaff();
        $stats = app(CohortAnalytics::class)->forUser(auth()->user(), $this->year(), $this->program(), $this->department(), $this->period());
        $this->dispatch('analytics-updated', charts: $stats['charts']);
    }

    private function year(): ?int
    {
        return in_array($this->yearLevel, ['1', '2', '3', '4'], true) ? (int) $this->yearLevel : null;
    }

    private function program(): ?int
    {
        return ctype_digit($this->programId) ? (int) $this->programId : null;
    }

    private function department(): ?int
    {
        return ctype_digit($this->departmentId) ? (int) $this->departmentId : null;
    }

    private function period(): ?string
    {
        return in_array($this->period, ['this_year', 'last_12_months'], true) ? $this->period : null;
    }

    private function authorizeStaff(): void
    {
        abort_unless(auth()->user()?->can('viewAggregates', Student::class) === true, 403);
    }
}
