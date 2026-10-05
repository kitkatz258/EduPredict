<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RoleDashboardController extends Controller
{
    public function student(Request $request): View
    {
        return view('dashboards.student', [
            'student' => $request->user()->student,
        ]);
    }

    public function faculty(Request $request): View
    {
        $advisees = Student::query()
            ->visibleTo($request->user())
            ->with(['user', 'program', 'latestPrediction'])
            ->orderBy('student_number')
            ->limit(10)
            ->get();

        return view('dashboards.faculty', [
            'advisees' => $advisees,
        ]);
    }

    public function department(Request $request): View
    {
        return view('dashboards.department', [
            'program' => $request->user()->program,
        ]);
    }

    public function dean(Request $request): View
    {
        return view('dashboards.dean', [
            'college' => $request->user()->college,
        ]);
    }

    public function admin(): View
    {
        return view('dashboards.admin');
    }
}
