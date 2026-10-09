<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Intervention;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function users(): View
    {
        $this->authorize('viewAny', \App\Models\User::class);

        return view('admin.users');
    }

    public function institutionStudents(): View
    {
        $this->authorize('viewAny', \App\Models\InstitutionStudent::class);

        return view('admin.institution-students');
    }

    public function psoc(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\PsocOccupation::class);

        return view('admin.psoc', [
            'occupationId' => $request->integer('occupation') ?: null,
        ]);
    }

    public function colleges(Request $request): View
    {
        $this->authorize('viewAny', College::class);

        return view('admin.colleges', [
            'collegeId' => $request->integer('college') ?: null,
            'departmentId' => $request->integer('department') ?: null,
            'programId' => $request->integer('program') ?: null,
        ]);
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
        $this->authorize('viewAny', \App\Models\AccountDeletionRequest::class);

        return view('admin.deletion-requests');
    }
}
