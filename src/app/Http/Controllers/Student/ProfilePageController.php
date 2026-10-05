<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SocioeconomicProfile;
use Illuminate\View\View;

class ProfilePageController extends Controller
{
    public function index(): View
    {
        $this->authorize('create', SocioeconomicProfile::class);

        return view('student.profile');
    }
}
