<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HistoryPageController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user()->student;
        abort_unless($student, 403);

        return view('student.history', ['student' => $student]);
    }
}
