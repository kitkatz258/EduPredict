<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Prediction\PredictionPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ResultsPageController extends Controller
{
    public function index(Request $request, PredictionPresenter $presenter): View
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        $student->load(['program', 'user']);
        $latest = $student->predictions()->latest('created_at')->latest('id')->first();

        return view('student.results', [
            'student' => $student,
            'latest' => $latest,
            'summary' => $presenter->summary($latest, true),
            'charts' => $presenter->charts($student, $latest),
        ]);
    }
}
