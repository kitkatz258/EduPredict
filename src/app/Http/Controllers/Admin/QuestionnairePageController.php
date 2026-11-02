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

        $currentVersion = (string) config('edupredict.questionnaire.current_version');
        $versionMeta = (array) config('edupredict.questionnaire.versions.'.$currentVersion, []);
        $counts = [];
        foreach (array_keys(config('edupredict.questionnaire.versions', [])) as $version) {
            $counts[$version] = [
                'total' => QuestionnaireItem::query()->where('definition_version', $version)->count(),
                'active' => QuestionnaireItem::query()->where('definition_version', $version)->where('is_active', true)->count(),
                'published' => QuestionnaireItem::query()->where('definition_version', $version)->where('is_draft', false)->count(),
            ];
        }

        return view('admin.questionnaire', [
            'itemId' => $request->integer('item') ?: null,
            'currentVersion' => $currentVersion,
            'currentMeta' => $versionMeta,
            'versionCounts' => $counts,
        ]);
    }
}
