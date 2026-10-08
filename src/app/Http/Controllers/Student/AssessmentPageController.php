<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SocioeconomicProfile;
use Illuminate\Contracts\View\View;

class AssessmentPageController extends Controller
{
    public function index(): View
    {
        $this->authorize('create', SocioeconomicProfile::class);

        return view('student.assessment');
    }
}
