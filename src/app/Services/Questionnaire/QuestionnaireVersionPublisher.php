<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireResponse;
use App\Models\QuestionnaireVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class QuestionnaireVersionPublisher
{
    /**
     * @var list<string>
     */
    public const SUPPORTED_SECTIONS = [
        'academic',
        'socioeconomic',
        'behavioral',
        'competence',
        'psychological_capital',
        'self_perceived_employability',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{published: bool, errors: list<string>, version: ?QuestionnaireVersion}
     */
    public function publish(QuestionnaireVersion $version, User $actor, string $evidence, bool $acknowledge): array
    {
        return DB::transaction(function () use ($version, $actor, $evidence, $acknowledge): array {
            $locked = QuestionnaireVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== QuestionnaireVersion::STATE_DRAFT) {
                return [
                    'published' => false,
                    'errors' => ['This version is no longer a draft. It was not published again.'],
                    'version' => $locked,
                ];
            }

            $errors = $this->blockingErrors($locked);
            $evidence = trim($evidence);
            if (mb_strlen($evidence) < 20) {
                $errors[] = 'Enter new approval evidence of at least 20 characters. Panel approval of an earlier version is not inherited.';
            }
            if (! $acknowledge) {
                $errors[] = 'Confirm that activation affects new questionnaires only. Existing submissions and drafts stay on their original versions.';
            }
            if ($errors !== []) {
                return [
                    'published' => false,
                    'errors' => $errors,
                    'version' => $locked,
                ];
            }

            $previous = QuestionnaireVersion::query()->where('current_slot', 1)->lockForUpdate()->first();
            $responsePins = QuestionnaireResponse::query()
                ->select('definition_version')
                ->distinct()
                ->pluck('definition_version')
                ->all();

            QuestionnaireItem::query()
                ->where('definition_version', $locked->code)
                ->update(['is_draft' => false]);

            if ($previous !== null && $previous->id !== $locked->id) {
                $previous->update(['current_slot' => null]);
            }

            $locked->update([
                'state' => QuestionnaireVersion::STATE_PUBLISHED,
                'current_slot' => 1,
                'panel_approved' => true,
                'approval_evidence' => $evidence,
                'published_at' => now(),
            ]);

            $this->audit->record('questionnaire_version_published', $locked, [
                'code' => $locked->code,
                'previous_current' => $previous?->code,
                'counts' => $this->sectionCounts($locked->code),
                'responses_left_on' => $responsePins,
                'responses_rewritten' => 0,
            ], $actor);

            return [
                'published' => true,
                'errors' => [],
                'version' => $locked->fresh(),
            ];
        });
    }

    /**
     * @return list<string>
     */
    public function blockingErrors(QuestionnaireVersion $version): array
    {
        $errors = [];
        if ($version->contract !== QuestionnaireVersion::CONTRACT_SIX_BY_TEN) {
            $errors[] = 'This draft does not use the supported six-section, ten-item contract. Publishing another structure is blocked until the scorer, student assessment, and backend have a compatible validated contract.';
        }
        if ($version->scoring !== 'section-mean-1-5' || $version->navigation !== 'section' || $version->reverse_scoring) {
            $errors[] = 'This draft does not use section means of 1–5, section navigation, and no reverse scoring.';
        }

        $items = QuestionnaireItem::query()->where('definition_version', $version->code)->get();
        if ($items->count() !== 60) {
            $errors[] = 'The supported contract requires exactly 60 questions, 10 in each of A, S, B, C, P, and E. This draft has '.$items->count().'.';
        }

        foreach (self::SUPPORTED_SECTIONS as $section) {
            $code = (string) config('edupredict.questionnaire.sections.'.$section.'.code', $section);
            $active = $items->where('section', $section)->where('is_active', true)->values();
            if ($active->count() !== 10) {
                $errors[] = "Section {$code} must have exactly 10 active questions. It has {$active->count()}.";
            }
            $orders = $active->map(fn (QuestionnaireItem $item): int => (int) $item->sort_order)->sort()->values()->all();
            if ($active->count() === 10 && $orders !== range(1, 10)) {
                $errors[] = "Section {$code} positions must run from 1 through 10 with no gaps.";
            }
            if ($active->contains(fn (QuestionnaireItem $item): bool => $item->reverse_scored)) {
                $errors[] = "Section {$code} cannot use reverse scoring on the current contract.";
            }
            if ($active->contains(fn (QuestionnaireItem $item): bool => trim($item->text) === '')) {
                $errors[] = "Section {$code} has a question with no text.";
            }
        }

        if ($items->whereNotIn('section', self::SUPPORTED_SECTIONS)->isNotEmpty()) {
            $errors[] = 'Questions outside A, S, B, C, P, and E cannot be published on the supported contract.';
        }
        if ($items->where('is_active', false)->isNotEmpty()) {
            $errors[] = 'Deactivated questions are still in this draft. Remove them or make them active before publishing.';
        }

        return $errors;
    }

    /**
     * @return array{started_empty: bool, source_code: ?string, notes: list<string>}
     */
    public function changes(QuestionnaireVersion $version): array
    {
        if ($version->source_code === null) {
            return [
                'started_empty' => true,
                'source_code' => null,
                'notes' => ['Started with no copied questions.'],
            ];
        }

        $source = QuestionnaireItem::query()
            ->where('definition_version', $version->source_code)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('section');
        $draft = QuestionnaireItem::query()
            ->where('definition_version', $version->code)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('section');

        $notes = [];
        $sections = $source->keys()->merge($draft->keys())->unique()->values();
        foreach ($sections as $section) {
            if (! is_string($section)) {
                continue;
            }
            $before = $this->texts($source->get($section));
            $after = $this->texts($draft->get($section));
            if ($before === $after) {
                continue;
            }
            $label = (string) config('edupredict.questionnaire.sections.'.$section.'.code', $section);
            $sameTexts = $before !== [] && array_count_values($before) === array_count_values($after);
            $notes[] = $sameTexts
                ? "Section {$label} order changed."
                : "Section {$label} questions differ from the source (draft ".count($after).', source '.count($before).').';
        }

        if ($notes === []) {
            $notes[] = 'No question text or order differs from the copied source.';
        }

        return [
            'started_empty' => false,
            'source_code' => $version->source_code,
            'notes' => $notes,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function sectionCounts(string $code): array
    {
        $counts = [];
        $items = QuestionnaireItem::query()->where('definition_version', $code)->get(['section', 'is_active']);
        foreach (self::SUPPORTED_SECTIONS as $section) {
            $counts[$section] = $items->where('section', $section)->where('is_active', true)->count();
        }

        return $counts;
    }

    /**
     * @param  Collection<int, QuestionnaireItem>|null  $items
     * @return list<string>
     */
    private function texts(?Collection $items): array
    {
        if ($items === null) {
            return [];
        }

        return $items->map(fn (QuestionnaireItem $item): string => $item->text)->values()->all();
    }
}
