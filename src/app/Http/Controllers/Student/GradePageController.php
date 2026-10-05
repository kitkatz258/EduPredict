<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class GradePageController extends Controller
{
    public function index(): View
    {
        $this->authorize('create', \App\Models\GradeReport::class);

        return view('student.grades');
    }
}
