<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Contracts\View\View;

class StudentRecordController extends Controller
{
    public function show(Student $student): View
    {
        $this->authorize('view', $student);

        $student->load(['user', 'program.college', 'latestPrediction', 'adviser']);

        return view('students.show', [
            'student' => $student,
        ]);
    }
}
