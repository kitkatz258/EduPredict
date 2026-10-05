<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Audit\AuditLogger;
use App\Services\Prediction\PredictionPresenter;
use Illuminate\Contracts\View\View;

class StudentRecordController extends Controller
{
    public function show(Student $student, PredictionPresenter $presenter, AuditLogger $audit): View
    {
        $this->authorize('view', $student);

        $student->load(['user', 'program.college', 'adviser']);
        $latest = $student->predictions()->latest('created_at')->latest('id')->first();
        $forStudent = auth()->id() === $student->user_id;

        if (! $forStudent) {
            $audit->record('student_record_viewed', $student, [
                'role' => auth()->user()?->role?->value,
            ]);
        }

        return view('students.show', [
            'student' => $student,
            'latest' => $latest,
            'summary' => $presenter->summary($latest, $forStudent),
            'charts' => $presenter->charts($student, $latest),
            'forStudent' => $forStudent,
        ]);
    }
}
