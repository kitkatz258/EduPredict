<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\Grades\AcademicSummary;
use App\Services\Prediction\PredictionPresenter;
use App\Services\Profile\ProfileCompleteness;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RoleDashboardController extends Controller
{
    public function student(Request $request, AcademicSummary $summary, ProfileCompleteness $completeness, PredictionPresenter $presenter): View
    {
        $student = $request->user()->student;
        $student?->load(['program', 'skillsExperience']);
        $latest = $student?->predictions()->latest('created_at')->latest('id')->first();

        return view('dashboards.student', [
            'student' => $student,
            'academic' => $student ? $summary->for($student) : null,
            'completeness' => $student ? $completeness->for($student) : null,
            'latest' => $latest,
            'summary' => $presenter->summary($latest, true),
            'skillsLogged' => $this->skillsLogged($student),
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

    private function skillsLogged(?Student $student): int
    {
        $skills = $student?->skillsExperience;
        if ($skills === null || $skills->is_draft) {
            return 0;
        }

        return count($skills->technical_skills ?? [])
            + count($skills->certifications ?? [])
            + count($skills->internships ?? [])
            + count($skills->projects ?? [])
            + count($skills->work_experience ?? []);
    }
}
