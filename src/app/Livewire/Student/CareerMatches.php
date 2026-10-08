<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Models\CareerMatch;
use App\Models\Prediction;
use App\Models\Student;
use App\Services\Career\CareerMatchBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Career matches saved with the signed-in student's latest prediction.
 */
class CareerMatches extends Component
{
    public ?int $viewingId = null;

    public function mount(): void
    {
        $this->authorize('view', $this->student());
    }

    public function openDetails(int $id): void
    {
        $latest = $this->latestPrediction();
        abort_unless($latest, 404);

        $this->viewingId = CareerMatch::query()->where('prediction_id', $latest->id)->findOrFail($id)->id;
    }

    public function closeDetails(): void
    {
        $this->viewingId = null;
    }

    public function render(CareerMatchBuilder $builder): View
    {
        $latest = $this->latestPrediction();
        $matches = $latest ? $builder->ensure($latest) : collect();

        return view('livewire.student.career-matches', [
            'latest' => $latest,
            'matches' => $matches,
            'usesTemplate' => $matches->contains(fn (CareerMatch $match): bool => $match->explanation_source === 'template'),
            'viewing' => $this->viewingId === null ? null : $matches->firstWhere('id', $this->viewingId),
        ]);
    }

    public static function band(float $score): string
    {
        return match (true) {
            $score >= 80 => 'Strong match',
            $score >= 65 => 'Good match',
            $score >= 50 => 'Possible with growth',
            default => 'Longer pathway',
        };
    }

    private function student(): Student
    {
        $student = auth()->user()?->student;
        abort_unless($student, 403);

        return $student;
    }

    private function latestPrediction(): ?Prediction
    {
        $student = $this->student();
        $this->authorize('view', $student);

        return $student->predictions()->latest('created_at')->latest('id')->first();
    }
}
