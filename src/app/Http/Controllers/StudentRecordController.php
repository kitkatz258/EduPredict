<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Prediction\PredictionPresenter;
use Illuminate\Contracts\View\View;

class StudentRecordController extends Controller
{
    public function show(Student $student, PredictionPresenter $presenter): View
    {
        $this->authorize('view', $student);

        $student->load(['user', 'program.college', 'adviser']);
        $latest = $student->predictions()->latest('created_at')->latest('id')->first();
        $forStudent = auth()->id() === $student->user_id;

        return view('students.show', [
            'student' => $student,
            'latest' => $latest,
            'summary' => $presenter->summary($latest, $forStudent),
            'charts' => $presenter->charts($student, $latest),
            'forStudent' => $forStudent,
        ]);
    }
}
