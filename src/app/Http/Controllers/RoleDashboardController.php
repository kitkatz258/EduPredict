<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Grades\AcademicSummary;
use App\Services\Prediction\AttemptSnapshot;
use App\Services\Prediction\PredictionPresenter;
use App\Services\Profile\AssessmentProgress;
use App\Services\Profile\SkillsExperienceRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RoleDashboardController extends Controller
{
    /**
     * The student Dashboard is also the results overview for the latest prediction.
     */
    public function student(Request $request, AcademicSummary $summary, AssessmentProgress $progress, PredictionPresenter $presenter, SkillsExperienceRecords $records, AttemptSnapshot $snapshots): View
    {
        $student = $request->user()->student;
        $student?->load('program');
        $latest = $student?->predictions()->latest('created_at')->latest('id')->first();

        return view('dashboards.student', [
            'student' => $student,
            'academic' => $student ? $summary->for($student) : null,
            'progress' => $student ? $progress->for($student) : null,
            'latest' => $latest,
            'summary' => $presenter->summary($latest, true),
            'charts' => $student && $latest ? $presenter->charts($student, $latest) : null,
            'contributors' => $presenter->topContributors($latest),
            'constructs' => $latest ? $snapshots->constructSummary($latest) : null,
            'skillsLogged' => $this->skillsLogged($student, $records),
            'gradesConfirmedAt' => $student?->gradeReports()->current()->max('confirmed_at'),
        ]);
    }

    public function department(Request $request): View
    {
        $user = $request->user()->loadMissing(['department', 'program']);

        return view('dashboards.department', [
            'department' => $user->department,
            'program' => $user->program,
        ]);
    }

    public function departmentStudents(Request $request): View
    {
        $user = $request->user()->loadMissing(['department', 'program']);
        $open = $request->integer('student');

        return view('department.students', [
            'department' => $user->department,
            'program' => $user->program,
            'openStudentId' => $open > 0 ? $open : null,
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

    private function skillsLogged(?Student $student, SkillsExperienceRecords $records): int
    {
        return $student === null ? 0 : array_sum($records->counts($student));
    }
}
