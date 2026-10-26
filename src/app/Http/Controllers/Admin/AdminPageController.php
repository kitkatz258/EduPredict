<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Department;
use App\Models\InstitutionStudent;
use App\Models\Intervention;
use App\Models\PredictionAttemptGrant;
use App\Models\Program;
use App\Models\PsocOccupation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function users(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.users');
    }

    public function institutionStudents(): View
    {
        $this->authorize('viewAny', InstitutionStudent::class);

        return view('admin.institution-students');
    }

    public function psoc(Request $request): View
    {
        $this->authorize('viewAny', PsocOccupation::class);

        return view('admin.psoc', [
            'occupationId' => $request->integer('occupation') ?: null,
        ]);
    }

    public function colleges(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', College::class);

        if ($request->filled('department')) {
            return redirect()->route('admin.departments', ['department' => $request->integer('department')]);
        }
        if ($request->filled('program')) {
            return redirect()->route('admin.programs', ['program' => $request->integer('program')]);
        }

        return view('admin.colleges', [
            'collegeId' => $request->integer('college') ?: null,
        ]);
    }

    public function departments(Request $request): View
    {
        $this->authorize('viewAny', Department::class);

        return view('admin.departments', [
            'departmentId' => $request->integer('department') ?: null,
        ]);
    }

    public function programs(Request $request): View
    {
        $this->authorize('viewAny', Program::class);

        return view('admin.programs', [
            'programId' => $request->integer('program') ?: null,
        ]);
    }

    public function predictionGrants(): View
    {
        $this->authorize('viewAny', PredictionAttemptGrant::class);

        return view('admin.prediction-grants');
    }

    public function interventions(Request $request): View
    {
        $this->authorize('viewAny', Intervention::class);

        return view('admin.interventions', [
            'interventionId' => $request->integer('intervention') ?: null,
        ]);
    }

    public function audit(): View
    {
        $this->authorize('viewAny', AuditLog::class);

        return view('admin.audit');
    }

    public function deletionRequests(): View
    {
        $this->authorize('viewAny', AccountDeletionRequest::class);

        return view('admin.deletion-requests');
    }
}
