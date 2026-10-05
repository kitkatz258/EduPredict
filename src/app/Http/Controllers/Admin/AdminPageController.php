<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function advisers(): View
    {
        abort_unless(auth()->user()?->isRole(\App\Enums\UserRole::Administrator), 403);

        return view('admin.advisers');
    }
}
