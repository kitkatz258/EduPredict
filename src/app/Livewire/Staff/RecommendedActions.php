<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Enums\UserRole;
use App\Livewire\Concerns\DispatchesToasts;
use App\Models\Prediction;
use App\Models\RecommendedAction;
use App\Models\Student;
use App\Services\Audit\AuditLogger;
use App\Services\Interventions\RecommendedActionBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class RecommendedActions extends Component
{
    use DispatchesToasts;

    #[Locked]
    public int $studentId;

    #[Locked]
    public ?int $predictionId = null;

    /** @var array<int|string, string> */
    public array $notes = [];

    /** @var list<int> */
    public array $selectedIds = [];

    public ?int $expandedId = null;

    public function mount(int $studentId, RecommendedActionBuilder $builder): void
    {
        $this->studentId = $studentId;
        $student = $this->scopedStudent();
        $latest = $student->predictions()->latest('created_at')->latest('id')->first();
        $this->predictionId = $latest?->id;
        if ($latest === null) {
            return;
        }

        foreach ($builder->ensure($latest) as $action) {
            $this->notes[$action->id] = (string) ($action->reviewer_note ?? '');
        }
    }

    public function toggleSelected(int $actionId): void
    {
        $action = $this->scopedAction($actionId);
        if ($action->reviewed_at !== null) {
            return;
        }

        $selected = array_map('intval', $this->selectedIds);
        if (in_array($actionId, $selected, true)) {
            $this->selectedIds = array_values(array_filter(
                $selected,
                fn (int $id): bool => $id !== $actionId,
            ));

            return;
        }

        $this->selectedIds = [...$selected, $actionId];
    }

    public function toggleExpanded(int $actionId): void
    {
        $this->scopedAction($actionId);
        $this->expandedId = $this->expandedId === $actionId ? null : $actionId;
    }

    public function markReviewed(int $actionId): void
    {
        $this->persistReviews([$actionId]);
    }

    #[On('mark-selected-reviews')]
    public function markSelectedReviewed(): void
    {
        $this->persistReviews($this->selectedIds);
    }

    public function render(): View
    {
        $student = $this->scopedStudent();
        $latest = $this->currentPrediction($student);
        $actions = RecommendedAction::query()
            ->where('prediction_id', $latest?->id ?? 0)
            ->with(['intervention', 'reviewer'])
            ->orderBy('id')
            ->get();

        $user = auth()->user();
        $unreviewed = $actions->filter(fn (RecommendedAction $action): bool => $action->reviewed_at === null);

        return view('livewire.staff.recommended-actions', [
            'actions' => $actions,
            'canReview' => $user?->isRole(UserRole::DepartmentHead) === true,
            'usesFallback' => $actions->contains(fn (RecommendedAction $action): bool => $action->phrasing_source === 'rule_based'),
            'hasPrediction' => $latest !== null,
            'risk' => $latest?->dropout_risk,
            'unreviewedCount' => $unreviewed->count(),
        ]);
    }

    /**
     * @param  list<int>  $actionIds
     */
    private function persistReviews(array $actionIds): void
    {
        $student = $this->scopedStudent();
        $prediction = $this->currentPrediction($student);
        abort_unless($prediction !== null, 404);

        $ids = array_values(array_unique(array_map('intval', $actionIds)));
        if ($ids === []) {
            $this->toast('Select at least one recommendation to mark reviewed.', 'warning');

            return;
        }

        $notes = [];
        foreach ($ids as $id) {
            $note = trim((string) ($this->notes[$id] ?? $this->notes[(string) $id] ?? ''));
            Validator::make(
                ['note' => $note],
                ['note' => ['nullable', 'string', 'max:1000']],
            )->validate();
            $notes[$id] = $note;
        }

        $reviewed = 0;
        $codes = [];

        DB::transaction(function () use ($ids, $notes, $student, $prediction, &$reviewed, &$codes): void {
            foreach ($ids as $id) {
                $action = RecommendedAction::query()
                    ->with(['intervention', 'prediction'])
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless((int) $action->prediction?->student_id === $student->id, 403);
                abort_unless((int) $action->prediction_id === (int) $prediction->id, 403);
                $this->authorize('review', $action);

                if ($action->reviewed_at !== null) {
                    continue;
                }

                $note = $notes[$id] ?? '';
                $action->update([
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'reviewer_note' => $note !== '' ? $note : null,
                ]);
                $reviewed++;
                $codes[] = (string) ($action->intervention?->code ?? $action->intervention_id);
            }
        });

        if ($reviewed === 0) {
            $this->toast('Those recommendations were already reviewed.', 'info');

            return;
        }

        app(AuditLogger::class)->record('intervention_reviewed', $prediction, [
            'student_id' => $student->id,
            'prediction_id' => $prediction->id,
            'reviewed_count' => $reviewed,
            'intervention_codes' => $codes,
        ]);

        $this->selectedIds = [];
        $this->toast($reviewed === 1
            ? 'Recommendation marked as reviewed.'
            : $reviewed.' recommendations marked as reviewed.');
        $this->dispatch('intervention-reviewed', studentId: $student->id);
    }

    private function scopedStudent(): Student
    {
        $student = Student::query()->findOrFail($this->studentId);
        $user = auth()->user();
        abort_unless($user !== null && ! $user->isRole(UserRole::Student), 403);
        $this->authorize('view', $student);

        return $student;
    }

    private function scopedAction(int $actionId): RecommendedAction
    {
        $student = $this->scopedStudent();
        $action = RecommendedAction::query()->with('prediction')->findOrFail($actionId);
        abort_unless((int) $action->prediction?->student_id === $student->id, 403);
        abort_unless($this->predictionId === null || (int) $action->prediction_id === $this->predictionId, 403);
        $this->authorize('view', $action);

        return $action;
    }

    private function currentPrediction(Student $student): ?Prediction
    {
        $query = $student->predictions()->latest('created_at')->latest('id');
        if ($this->predictionId !== null) {
            $locked = $student->predictions()->whereKey($this->predictionId)->first();
            if ($locked !== null) {
                return $locked;
            }
        }

        return $query->first();
    }
}
