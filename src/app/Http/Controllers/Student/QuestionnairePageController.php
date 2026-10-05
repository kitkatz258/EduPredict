<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\QuestionnaireResponse;
use Illuminate\View\View;

class QuestionnairePageController extends Controller
{
    public function index(): View
    {
        $this->authorize('create', QuestionnaireResponse::class);

        return view('student.questionnaire');
    }
}
