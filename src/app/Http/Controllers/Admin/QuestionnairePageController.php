<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionnaireItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionnairePageController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', QuestionnaireItem::class);

        return view('admin.questionnaire', [
            'itemId' => $request->integer('item') ?: null,
        ]);
    }
}
