<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\PredictionAttemptGrant;
use App\Models\Student;
use App\Services\Admin\PredictionGrantService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class PredictionGrantForm extends Component
{
    use DispatchesToasts;

    public bool $show = false;

    public string $studentNumber = '';

    public string $manilaDate = '';

    public int $extraAttempts = 1;

    public string $reason = '';

    public string $matchedLabel = '';

    public function mount(): void
    {
        $this->authorize('create', PredictionAttemptGrant::class);
        $this->manilaDate = now('Asia/Manila')->toDateString();
    }

    #[On('add-prediction-grant')]
    public function startCreate(?string $studentNumber = null): void
    {
        $this->authorize('create', PredictionAttemptGrant::class);
        $this->resetForm();
        $this->manilaDate = now('Asia/Manila')->toDateString();
        if ($studentNumber) {
            $this->studentNumber = $studentNumber;
            $this->lookupStudent();
        }
        $this->show = true;
    }

    public function closeForm(): void
    {
        $this->show = false;
        $this->resetForm();
    }

    public function updatedStudentNumber(): void
    {
        $this->lookupStudent();
    }

    public function save(PredictionGrantService $grants): void
    {
        $this->authorize('create', PredictionAttemptGrant::class);
        $this->validate([
            'studentNumber' => ['required', 'string', 'max:32'],
            'manilaDate' => ['required', 'date_format:Y-m-d'],
            'extraAttempts' => ['required', 'integer', 'min:1', 'max:9'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        $student = Student::query()->where('student_number', trim($this->studentNumber))->first();
        if ($student === null) {
            $this->addError('studentNumber', 'No provisioned student uses that student number.');

            return;
        }

        try {
            $grants->grant(
                $student,
                $this->manilaDate,
                $this->extraAttempts,
                $this->reason,
                auth()->user(),
                request()->ip(),
            );
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->getMessageBag());

            return;
        }

        $message = 'Extra prediction slots were granted for '.$this->manilaDate.'.';
        $this->show = false;
        $this->resetForm();
        $this->dispatch('prediction-grants-changed');
        $this->successDialog($message);
    }

    public function render(): View
    {
        $this->authorize('create', PredictionAttemptGrant::class);

        return view('livewire.admin.prediction-grant-form');
    }

    private function lookupStudent(): void
    {
        $number = trim($this->studentNumber);
        if ($number === '') {
            $this->matchedLabel = '';

            return;
        }

        $student = Student::query()->with('user')->where('student_number', $number)->first();
        $this->matchedLabel = $student
            ? ($student->user?->name ?: $number).' · year '.$student->year_level
            : '';
    }

    private function resetForm(): void
    {
        $this->reset(['studentNumber', 'extraAttempts', 'reason', 'matchedLabel']);
        $this->extraAttempts = 1;
        $this->resetValidation();
    }
}
