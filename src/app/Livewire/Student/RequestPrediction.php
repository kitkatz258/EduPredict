<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Models\Prediction;
use App\Services\Prediction\PredictionBlockedException;
use App\Services\Prediction\PredictionRequester;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RequestPrediction extends Component
{
    public function mount(): void
    {
        $this->authorize('create', Prediction::class);
    }

    public function request(PredictionRequester $requester): void
    {
        $this->authorize('create', Prediction::class);
        $student = auth()->user()?->student;
        abort_unless($student, 403);

        try {
            $requester->request($student, auth()->user());
        } catch (PredictionBlockedException $exception) {
            $this->addError('request', $exception->getMessage());

            return;
        }

        session()->flash('success', 'A new prediction was saved. These results are estimates, not guarantees.');
        $this->redirect(route('student.results'));
    }

    public function render(PredictionRequester $requester): View
    {
        $student = auth()->user()?->student;
        abort_unless($student, 403);

        return view('livewire.student.request-prediction', [
            'blocked' => $requester->blockMessage($student),
        ]);
    }
}
