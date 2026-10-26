<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Carbon;

trait ParsesReportFilters
{
    public string $yearLevel = '';

    public string $programId = '';

    public string $departmentId = '';

    public string $dropoutRisk = '';

    public string $period = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function updatedYearLevel(): void
    {
        if (! in_array($this->yearLevel, ['', '1', '2', '3', '4'], true)) {
            $this->yearLevel = '';
        }
    }

    public function updatedProgramId(): void
    {
        if ($this->programId !== '' && ! ctype_digit($this->programId)) {
            $this->programId = '';
        }
    }

    public function updatedDepartmentId(): void
    {
        if ($this->departmentId !== '' && ! ctype_digit($this->departmentId)) {
            $this->departmentId = '';
        }
    }

    public function updatedDropoutRisk(): void
    {
        if (! in_array($this->dropoutRisk, ['', 'low', 'moderate', 'high'], true)) {
            $this->dropoutRisk = '';
        }
    }

    public function updatedPeriod(): void
    {
        if (! in_array($this->period, ['', 'this_year', 'last_12_months'], true)) {
            $this->period = '';
        }
    }

    public function updatedDateFrom(): void
    {
        $this->dateFrom = $this->normalizeDate($this->dateFrom);
        $this->orderDates();
    }

    public function updatedDateTo(): void
    {
        $this->dateTo = $this->normalizeDate($this->dateTo);
        $this->orderDates();
    }

    protected function year(): ?int
    {
        return in_array($this->yearLevel, ['1', '2', '3', '4'], true) ? (int) $this->yearLevel : null;
    }

    protected function program(): ?int
    {
        return ctype_digit($this->programId) ? (int) $this->programId : null;
    }

    protected function department(): ?int
    {
        return ctype_digit($this->departmentId) ? (int) $this->departmentId : null;
    }

    protected function period(): ?string
    {
        return in_array($this->period, ['this_year', 'last_12_months'], true) ? $this->period : null;
    }

    protected function fromDate(): ?Carbon
    {
        return $this->bound($this->dateFrom, false);
    }

    protected function toDate(): ?Carbon
    {
        return $this->bound($this->dateTo, true);
    }

    /**
     * @return array<string, string>
     */
    protected function filterLabels(): array
    {
        return [
            'department' => $this->departmentId,
            'program' => $this->programId,
            'year' => $this->yearLevel,
            'risk' => $this->dropoutRisk,
            'period' => $this->period,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value, (string) config('app.timezone'))->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    private function orderDates(): void
    {
        if ($this->dateFrom === '' || $this->dateTo === '') {
            return;
        }

        if ($this->dateFrom > $this->dateTo) {
            [$this->dateFrom, $this->dateTo] = [$this->dateTo, $this->dateFrom];
        }
    }

    private function bound(string $value, bool $endOfDay): ?Carbon
    {
        $value = $this->normalizeDate($value);
        if ($value === '') {
            return null;
        }

        $date = Carbon::parse($value, (string) config('app.timezone'));

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
