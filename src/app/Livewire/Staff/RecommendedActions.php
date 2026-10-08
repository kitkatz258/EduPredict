<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Enums\UserRole;
use App\Models\RecommendedAction;
use App\Models\Student;
use App\Services\Interventions\RecommendedActionBuilder;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RecommendedActions extends Component
{
    #[Locked]
    public int $studentId;

    /** @var array<int, string> */
    public array $notes = [];

    public function mount(int $studentId, RecommendedActionBuilder $builder): void
    {
        $this->studentId = $studentId;
        $student = $this->scopedStudent();
        $latest = $student->predictions()->latest('created_at')->latest('id')->first();
        if ($latest === null) {
            return;
        }

        foreach ($builder->ensure($latest) as $action) {
            $this->notes[$action->id] = (string) ($action->reviewer_note ?? '');
        }
    }

    public function markReviewed(int $actionId): void
    {
        $student = $this->scopedStudent();
        $action = RecommendedAction::query()->with('prediction')->findOrFail($actionId);
        abort_unless((int) $action->prediction?->student_id === $student->id, 403);
        $this->authorize('review', $action);

        $note = trim((string) ($this->notes[$actionId] ?? $this->notes[(string) $actionId] ?? ''));
        Validator::make(
            ['note' => $note],
            ['note' => ['nullable', 'string', 'max:1000']],
        )->validate();

        $action->update([
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'reviewer_note' => $note !== '' ? $note : null,
        ]);
    }

    public function render(): View
    {
        $student = $this->scopedStudent();
        $latest = $student->predictions()->latest('created_at')->latest('id')->first();
        $actions = RecommendedAction::query()
            ->where('prediction_id', $latest?->id ?? 0)
            ->with(['intervention', 'reviewer'])
            ->orderBy('id')
            ->get();

        $user = auth()->user();

        return view('livewire.staff.recommended-actions', [
            'actions' => $actions,
            'canReview' => $user?->isRole(UserRole::DepartmentHead) === true,
            'usesFallback' => $actions->contains(fn (RecommendedAction $action): bool => $action->phrasing_source === 'rule_based'),
            'hasPrediction' => $latest !== null,
            'risk' => $latest?->dropout_risk,
        ]);
    }

    private function scopedStudent(): Student
    {
        $student = Student::query()->findOrFail($this->studentId);
        $user = auth()->user();
        abort_unless($user !== null && ! $user->isRole(UserRole::Student), 403);
        $this->authorize('view', $student);

        return $student;
    }
}
