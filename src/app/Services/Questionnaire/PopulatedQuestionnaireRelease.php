<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\QuestionnaireVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent update for a database that already has students.
 * It adds or publishes the approved 60-item catalog and leaves every
 * submission pinned to the version it already has.
 */
final class PopulatedQuestionnaireRelease
{
    public function __construct(private readonly RevisedInstrumentActivator $activator) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     created: int,
     *     updated: int,
     *     published_locked: int,
     *     skipped_unchanged: int,
     *     total: int,
     *     responses: int,
     *     answers: int,
     *     current: string
     * }
     */
    public function prepare(bool $execute): array
    {
        $blockers = $this->blockers();
        if ($blockers !== []) {
            throw new QuestionnaireReleaseBlocked($blockers);
        }

        $plan = $this->activator->activate(true);
        if (! $execute) {
            return $this->report(true, $plan);
        }

        $written = DB::transaction(function (): array {
            QuestionnaireVersion::query()
                ->whereIn('code', $this->protectedCodes())
                ->lockForUpdate()
                ->get();

            $blockers = $this->blockers();
            if ($blockers !== []) {
                throw new QuestionnaireReleaseBlocked($blockers);
            }

            $before = $this->snapshot();
            $written = $this->activator->activate(false);
            $this->ensureRevisedVersionIsCurrent();
            $after = $this->snapshot();
            $problems = $this->postCheck($before, $after);
            if ($problems !== []) {
                throw new QuestionnaireReleaseBlocked($problems);
            }

            return $written;
        });

        return $this->report(false, $written ?? $plan);
    }

    /**
     * @return list<string>
     */
    public function blockers(): array
    {
        $reasons = [];
        if (! Schema::hasTable('questionnaire_versions')) {
            return ['questionnaire_versions is missing. Run migrations before this update.'];
        }

        $versions = QuestionnaireVersion::query()->get();
        foreach ($this->protectedCodes() as $code) {
            $rows = $versions->where('code', $code);
            if ($rows->count() !== 1) {
                $reasons[] = "Expected exactly one {$code} version row. Found ".$rows->count().'.';
            }
        }

        $current = $versions->where('current_slot', 1);
        if ($current->count() > 1) {
            $reasons[] = 'More than one questionnaire version is marked current.';
        }
        $currentCode = $current->first()?->code;
        if ($currentCode !== null && $currentCode !== RevisedInstrumentCatalog::VERSION) {
            $reasons[] = "The current questionnaire is {$currentCode}. This update will not replace it.";
        }

        $revised = $versions->firstWhere('code', RevisedInstrumentCatalog::VERSION);
        if ($revised instanceof QuestionnaireVersion) {
            if ($revised->state !== QuestionnaireVersion::STATE_PUBLISHED || ! $revised->panel_approved) {
                $reasons[] = 'The 60-item version row is not an already published, panel-approved catalog row.';
            }
            if ($revised->contract !== QuestionnaireVersion::CONTRACT_SIX_BY_TEN) {
                $reasons[] = 'The 60-item version row does not use the six-by-ten contract.';
            }
        }

        $historical = $versions->firstWhere('code', ApprovedInstrumentCatalog::VERSION);
        if ($historical instanceof QuestionnaireVersion && ($historical->isCurrent() || $historical->state !== QuestionnaireVersion::STATE_PUBLISHED)) {
            $reasons[] = 'The 36-item version row is no longer a non-current published historical catalog.';
        }

        $draft = $versions->firstWhere('code', 'draft-v1');
        if ($draft instanceof QuestionnaireVersion && ($draft->isCurrent() || $draft->state !== QuestionnaireVersion::STATE_DRAFT)) {
            $reasons[] = 'draft-v1 is no longer an unpublished, non-current version.';
        }

        $reasons = array_merge($reasons, $this->historicalItemBlockers(), $this->revisedItemBlockers());

        $known = $versions->pluck('code')->all();
        $unpinned = QuestionnaireResponse::query()->whereNull('definition_version')->count();
        if ($unpinned > 0) {
            $reasons[] = $unpinned.' submissions have no questionnaire version pin.';
        }
        $unknownResponses = QuestionnaireResponse::query()
            ->whereNotNull('definition_version')
            ->whereNotIn('definition_version', $known)
            ->distinct()
            ->pluck('definition_version');
        foreach ($unknownResponses as $code) {
            $reasons[] = 'A submission is pinned to '.$this->label($code).', which has no version row.';
        }

        return $reasons;
    }

    /**
     * @return list<string>
     */
    private function protectedCodes(): array
    {
        return ['draft-v1', ApprovedInstrumentCatalog::VERSION, RevisedInstrumentCatalog::VERSION];
    }

    /**
     * @return list<string>
     */
    private function historicalItemBlockers(): array
    {
        $reasons = [];
        $count = QuestionnaireItem::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->count();
        $responses = QuestionnaireResponse::query()->where('definition_version', ApprovedInstrumentCatalog::VERSION)->count();
        if ($count !== 0 && $count !== 36) {
            $reasons[] = "The 36-item catalog has {$count} questions. Expected 36, or none when that instrument was never installed.";
        }
        if ($responses > 0 && $count !== 36) {
            $reasons[] = 'Historical 36-item submissions exist, but the stored 36-item catalog is not intact.';
        }

        return $reasons;
    }

    /**
     * @return list<string>
     */
    private function revisedItemBlockers(): array
    {
        $reasons = [];
        $catalog = [];
        foreach (RevisedInstrumentCatalog::items() as $item) {
            $catalog[$item['section'].'|'.$item['sort_order']] = $item;
        }

        $existing = QuestionnaireItem::query()
            ->where('definition_version', RevisedInstrumentCatalog::VERSION)
            ->get();
        if ($existing->count() > 60) {
            $reasons[] = 'The 60-item version already has '.$existing->count().' questions. Refusing to continue.';
        }

        $seen = [];
        foreach ($existing as $item) {
            $key = $item->section.'|'.$item->sort_order;
            if (isset($seen[$key])) {
                $reasons[] = "Duplicate 60-item question at {$item->section} position {$item->sort_order}.";

                continue;
            }
            $seen[$key] = true;
            $expected = $catalog[$key] ?? null;
            if ($expected === null) {
                $reasons[] = "Unexpected 60-item question at {$item->section} position {$item->sort_order}.";

                continue;
            }
            if (! $item->answers()->exists()) {
                continue;
            }
            if ($item->text !== $expected['text'] || $item->construct !== $expected['construct']) {
                $reasons[] = "Question {$item->id} already has answers and its wording differs from the approved catalog. It was not rewritten.";
            }
        }

        return $reasons;
    }

    private function ensureRevisedVersionIsCurrent(): void
    {
        $current = QuestionnaireVersion::query()->where('current_slot', 1)->first();
        if ($current instanceof QuestionnaireVersion) {
            return;
        }

        QuestionnaireVersion::query()
            ->where('code', RevisedInstrumentCatalog::VERSION)
            ->update(['current_slot' => 1]);
    }

    /**
     * @param  array{created: int, updated: int, published_locked: int, skipped_unchanged: int, total: int}  $plan
     * @return array{dry_run: bool, created: int, updated: int, published_locked: int, skipped_unchanged: int, total: int, responses: int, answers: int, current: string}
     */
    private function report(bool $dryRun, array $plan): array
    {
        return [
            'dry_run' => $dryRun,
            'created' => $plan['created'],
            'updated' => $plan['updated'],
            'published_locked' => $plan['published_locked'],
            'skipped_unchanged' => $plan['skipped_unchanged'],
            'total' => $plan['total'],
            'responses' => QuestionnaireResponse::query()->count(),
            'answers' => (int) DB::table('questionnaire_answers')->count(),
            'current' => (string) (QuestionnaireVersion::query()->where('current_slot', 1)->value('code') ?? ''),
        ];
    }

    /**
     * @return array{versions: list<string>, responses: array<int, string>, answers: int, historical: string, draft: string}
     */
    private function snapshot(): array
    {
        return [
            'versions' => QuestionnaireVersion::query()->orderBy('code')->pluck('code')->all(),
            'responses' => QuestionnaireResponse::query()->orderBy('id')->pluck('definition_version', 'id')->map(fn (mixed $version): string => (string) $version)->all(),
            'answers' => (int) DB::table('questionnaire_answers')->count(),
            'historical' => $this->fingerprint(ApprovedInstrumentCatalog::VERSION),
            'draft' => $this->fingerprint('draft-v1'),
        ];
    }

    private function fingerprint(string $version): string
    {
        $rows = QuestionnaireItem::query()
            ->where('definition_version', $version)
            ->orderBy('id')
            ->get(['id', 'section', 'construct', 'text', 'reverse_scored', 'is_active', 'is_draft', 'sort_order']);

        return hash('sha256', $rows->map(fn (QuestionnaireItem $item): string => implode('|', [
            $item->id,
            $item->section,
            $item->construct,
            $item->text,
            $item->reverse_scored ? '1' : '0',
            $item->is_active ? '1' : '0',
            $item->is_draft ? '1' : '0',
            $item->sort_order,
        ]))->implode("\n"));
    }

    /**
     * @param  array{versions: list<string>, responses: array<int, string>, answers: int, historical: string, draft: string}  $before
     * @param  array{versions: list<string>, responses: array<int, string>, answers: int, historical: string, draft: string}  $after
     * @return list<string>
     */
    private function postCheck(array $before, array $after): array
    {
        $reasons = [];
        if ($before['versions'] !== $after['versions']) {
            $reasons[] = 'Version rows changed. This update does not insert or remove questionnaire versions.';
        }
        if ($before['responses'] !== $after['responses']) {
            $reasons[] = 'A submission was added, removed, or moved to another questionnaire version.';
        }
        if ($before['answers'] !== $after['answers']) {
            $reasons[] = 'Answer rows changed. This update does not write student answers.';
        }
        if ($before['historical'] !== $after['historical']) {
            $reasons[] = 'The historical 36-item catalog changed.';
        }
        if ($before['draft'] !== $after['draft']) {
            $reasons[] = 'The draft-v1 catalog changed.';
        }

        $revised = QuestionnaireItem::query()->where('definition_version', RevisedInstrumentCatalog::VERSION)->get();
        if ($revised->count() !== 60 || $revised->where('is_active', true)->where('is_draft', false)->count() !== 60) {
            $reasons[] = 'The approved catalog does not have 60 active published questions after the update.';
        }
        foreach (['academic', 'socioeconomic', 'behavioral', 'competence', 'psychological_capital', 'self_perceived_employability'] as $section) {
            if ($revised->where('section', $section)->count() !== 10) {
                $reasons[] = "Section {$section} does not have 10 questions after the update.";
            }
        }

        $current = QuestionnaireVersion::query()->where('current_slot', 1)->pluck('code')->values()->all();
        if ($current !== [RevisedInstrumentCatalog::VERSION]) {
            $reasons[] = 'The current questionnaire is not exactly ucc-panel-revised-60-v2.';
        }

        return $reasons;
    }

    private function label(mixed $code): string
    {
        if ($code === null || $code === '') {
            return '(blank)';
        }

        return (string) $code;
    }
}
