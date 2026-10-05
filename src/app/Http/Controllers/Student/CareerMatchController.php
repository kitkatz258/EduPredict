<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Career\CareerMatchBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CareerMatchController extends Controller
{
    public function index(Request $request, CareerMatchBuilder $matches): View
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        $student->load('program');
        $latest = $student->predictions()->latest('created_at')->latest('id')->first();
        $rows = $latest ? $matches->ensure($latest) : collect();

        return view('student.careers', [
            'student' => $student,
            'latest' => $latest,
            'matches' => $rows,
            'usesTemplate' => $rows->contains(fn ($match): bool => $match->explanation_source === 'template'),
        ]);
    }
}
