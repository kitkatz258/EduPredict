<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireVersion;
use App\Models\User;
use App\Services\Questionnaire\QuestionnaireVersionCatalog;
use App\Services\Questionnaire\QuestionnaireVersionCreator;
use App\Services\Questionnaire\QuestionnaireVersionPublisher;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class QuestionnaireVersionManager extends Component
{
    use DispatchesToasts;

    public string $selectedCode = '';

    public bool $showCreate = false;

    public string $name = '';

    public string $mode = 'copy_active';

    public string $summary = '';

    public bool $createConfirmed = false;

    public bool $createInFlight = false;

    public bool $showReview = false;

    public bool $showPreview = false;

    public string $approvalEvidence = '';

    public bool $acknowledgeActivation = false;

    public bool $publishConfirmed = false;

    public bool $publishInFlight = false;

    /** @var list<string> */
    public array $publishErrors = [];

    public function mount(QuestionnaireVersionCatalog $catalog): void
    {
        $this->authorize('viewAny', QuestionnaireVersion::class);
        $this->selectedCode = $this->rememberedCode($catalog);
    }

    public function selectVersion(string $code, QuestionnaireVersionCatalog $catalog): void
    {
        $this->authorize('viewAny', QuestionnaireVersion::class);
        if ($catalog->find($code) === null) {
            return;
        }

        $this->selectedCode = $code;
        $this->showReview = false;
        $this->showPreview = false;
        $this->publishErrors = [];
        session(['questionnaire.admin_version' => $code]);
        $this->dispatch('questionnaire-version-selected', code: $code);
    }

    public function openCreate(): void
    {
        $this->authorize('create', QuestionnaireVersion::class);
        $this->resetCreate();
        $this->showCreate = true;
    }

    public function openCopy(): void
    {
        $this->authorize('create', QuestionnaireVersion::class);
        $this->resetCreate();
        $this->mode = 'copy_selected';
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->resetCreate();
        $this->showCreate = false;
    }

    public function confirmCreate(): void
    {
        if ($this->createInFlight) {
            return;
        }

        $this->createConfirmed = true;

        try {
            app()->call([$this, 'createDraft']);
        } finally {
            $this->createConfirmed = false;
        }
    }

    public function createDraft(QuestionnaireVersionCreator $creator, QuestionnaireVersionCatalog $catalog): void
    {
        $this->authorize('create', QuestionnaireVersion::class);
        $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('questionnaire_versions', 'name')],
            'mode' => ['required', Rule::in(['copy_active', 'copy_selected', 'empty'])],
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($this->createInFlight) {
            return;
        }

        if (! $this->createConfirmed) {
            $this->js(<<<'JS'
                Promise.resolve(window.confirmAction({
                    title: 'Create this draft version?',
                    text: 'The draft is not published. Students will not see it, and it does not inherit panel approval.',
                    confirmText: 'Create draft',
                    danger: false
                })).then((ok) => { if (ok) { $wire.confirmCreate(); } });
            JS);

            return;
        }

        $this->createInFlight = true;

        try {
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);
            $version = $creator->create(
                $actor,
                trim($this->name),
                $this->mode,
                $this->summary,
                $this->selectedCode,
            );
            $this->showCreate = false;
            $this->resetCreate();
            $this->selectedCode = $version->code;
            session(['questionnaire.admin_version' => $version->code]);
            $this->dispatch('questionnaire-version-selected', code: $version->code);
            $this->successDialog('Students will not see it until it is reviewed and published.', 'Draft version created.');
        } finally {
            $this->createInFlight = false;
        }
    }

    public function openReview(QuestionnaireVersionCatalog $catalog): void
    {
        $version = $this->selected($catalog);
        $this->authorize('publish', $version);
        $this->publishErrors = [];
        $this->approvalEvidence = '';
        $this->acknowledgeActivation = false;
        $this->publishConfirmed = false;
        $this->showPreview = false;
        $this->showReview = true;
    }

    public function closeReview(): void
    {
        $this->showReview = false;
        $this->publishErrors = [];
        $this->publishConfirmed = false;
    }

    public function openPreview(): void
    {
        $this->authorize('viewAny', QuestionnaireVersion::class);
        $this->showReview = false;
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }

    public function confirmPublish(): void
    {
        if ($this->publishInFlight) {
            return;
        }

        $this->publishConfirmed = true;

        try {
            app()->call([$this, 'publish']);
        } finally {
            $this->publishConfirmed = false;
        }
    }

    public function publish(QuestionnaireVersionPublisher $publisher, QuestionnaireVersionCatalog $catalog): void
    {
        $version = $this->selected($catalog);
        $this->authorize('publish', $version);
        $this->validate([
            'approvalEvidence' => ['required', 'string', 'min:20', 'max:2000'],
            'acknowledgeActivation' => ['accepted'],
        ], [
            'approvalEvidence.required' => 'Enter new approval evidence. Panel approval of an earlier version is not inherited.',
            'approvalEvidence.min' => 'Enter new approval evidence of at least 20 characters. Panel approval of an earlier version is not inherited.',
            'acknowledgeActivation.accepted' => 'Confirm that activation affects new questionnaires only.',
        ]);

        if ($this->publishInFlight) {
            return;
        }

        if (! $this->publishConfirmed) {
            $this->js(<<<'JS'
                Promise.resolve(window.confirmAction({
                    title: 'Publish this questionnaire version?',
                    text: 'Activation affects new questionnaires only. Existing submissions and in-progress drafts stay on their original versions.',
                    confirmText: 'Publish version',
                    danger: false
                })).then((ok) => { if (ok) { $wire.confirmPublish(); } });
            JS);

            return;
        }

        $this->publishInFlight = true;

        try {
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);
            $result = $publisher->publish(
                $version,
                $actor,
                $this->approvalEvidence,
                $this->acknowledgeActivation,
            );
            $this->publishErrors = $result['errors'];
            if (! $result['published']) {
                return;
            }

            $this->showReview = false;
            $this->approvalEvidence = '';
            $this->acknowledgeActivation = false;
            $this->selectedCode = $result['version']->code;
            session(['questionnaire.admin_version' => $this->selectedCode]);
            $this->dispatch('questionnaire-version-selected', code: $this->selectedCode);
            $this->successDialog('New questionnaires use this version. Earlier submissions and drafts were left on their original versions.', 'Questionnaire published.');
        } finally {
            $this->publishInFlight = false;
        }
    }

    public function render(QuestionnaireVersionPublisher $publisher): View
    {
        $this->authorize('viewAny', QuestionnaireVersion::class);
        $versions = QuestionnaireVersion::query()->withCount('items')->get()
            ->sortBy(fn (QuestionnaireVersion $version): string => sprintf(
                '%d-%d-%05d',
                $version->isCurrent() ? 0 : ($version->isMutable() ? 1 : 2),
                $version->isMutable() ? 0 : 1,
                $version->id,
            ))
            ->values();

        $selected = $versions->firstWhere('code', $this->selectedCode) ?? $versions->firstWhere('current_slot', 1);
        if ($selected instanceof QuestionnaireVersion && $selected->code !== $this->selectedCode) {
            $this->selectedCode = $selected->code;
        }

        $counts = $selected instanceof QuestionnaireVersion ? $publisher->sectionCounts($selected->code) : [];
        $changes = $selected instanceof QuestionnaireVersion && $selected->isMutable()
            ? $publisher->changes($selected)
            : null;
        $blocking = $selected instanceof QuestionnaireVersion && $selected->isMutable()
            ? $publisher->blockingErrors($selected)
            : [];

        return view('livewire.admin.questionnaire-version-manager', [
            'versions' => $versions,
            'selected' => $selected,
            'sectionCounts' => $counts,
            'changes' => $changes,
            'blockingErrors' => $blocking,
            'previewItems' => $this->previewItems($selected),
            'sections' => config('edupredict.questionnaire.sections', []),
            'likert' => config('edupredict.questionnaire.likert', []),
        ]);
    }

    private function rememberedCode(QuestionnaireVersionCatalog $catalog): string
    {
        $stored = session('questionnaire.admin_version');
        if (is_string($stored) && $catalog->find($stored) !== null) {
            return $stored;
        }

        return $catalog->currentCode();
    }

    private function selected(QuestionnaireVersionCatalog $catalog): QuestionnaireVersion
    {
        $version = $catalog->find($this->selectedCode);
        abort_unless($version instanceof QuestionnaireVersion, 404);

        return $version;
    }

    private function resetCreate(): void
    {
        $this->name = '';
        $this->mode = 'copy_active';
        $this->summary = '';
        $this->createConfirmed = false;
        $this->createInFlight = false;
        $this->resetValidation();
    }

    /**
     * @return Collection<string, Collection<int, QuestionnaireItem>>
     */
    private function previewItems(?QuestionnaireVersion $selected)
    {
        if (! $selected instanceof QuestionnaireVersion) {
            return collect();
        }

        $order = array_flip(is_array($selected->sections) ? $selected->sections : []);

        return QuestionnaireItem::query()
            ->where('definition_version', $selected->code)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(function (QuestionnaireItem $item) use ($order): string {
                $sectionOrder = $order[$item->section] ?? 99;

                return sprintf('%03d-%05d-%05d', $sectionOrder, $item->sort_order, $item->id);
            })
            ->groupBy('section');
    }
}
