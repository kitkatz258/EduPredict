<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireItem;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the existing 60-item catalog in place. It does not create a third
 * version, rewrite answered wording, or touch ucc-panel-approved-v1 / draft-v1.
 */
final class RevisedInstrumentActivator
{
    /**
     * @return array{created: int, updated: int, published_locked: int, skipped_unchanged: int, total: int}
     */
    public function activate(bool $dryRun = false): array
    {
        $created = 0;
        $updated = 0;
        $publishedLocked = 0;
        $skippedUnchanged = 0;

        $apply = function () use (&$created, &$updated, &$publishedLocked, &$skippedUnchanged, $dryRun): void {
            foreach (RevisedInstrumentCatalog::items() as $item) {
                $existing = QuestionnaireItem::query()->where([
                    'definition_version' => RevisedInstrumentCatalog::VERSION,
                    'section' => $item['section'],
                    'sort_order' => $item['sort_order'],
                ])->first();

                if ($existing === null) {
                    $created++;
                    if (! $dryRun) {
                        QuestionnaireItem::query()->create($this->payload($item));
                    }

                    continue;
                }

                $answered = $existing->answers()->exists();
                if ($answered) {
                    $dirty = ! $existing->is_active || $existing->is_draft;
                    if ($dirty) {
                        $publishedLocked++;
                        if (! $dryRun) {
                            $existing->update([
                                'is_active' => true,
                                'is_draft' => false,
                            ]);
                        }
                    } else {
                        $skippedUnchanged++;
                    }

                    continue;
                }

                $payload = $this->payload($item);
                if ($this->matches($existing, $payload)) {
                    $skippedUnchanged++;

                    continue;
                }

                $updated++;
                if (! $dryRun) {
                    $existing->update($payload);
                }
            }
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'published_locked' => $publishedLocked,
            'skipped_unchanged' => $skippedUnchanged,
            'total' => count(RevisedInstrumentCatalog::items()),
        ];
    }

    /**
     * @param  array{code: string, section: string, construct: string, text: string, sort_order: int}  $item
     * @return array{definition_version: string, section: string, construct: string, text: string, reverse_scored: bool, is_active: bool, is_draft: bool, sort_order: int}
     */
    private function payload(array $item): array
    {
        return [
            'definition_version' => RevisedInstrumentCatalog::VERSION,
            'section' => $item['section'],
            'construct' => $item['construct'],
            'text' => $item['text'],
            'reverse_scored' => false,
            'is_active' => true,
            'is_draft' => false,
            'sort_order' => $item['sort_order'],
        ];
    }

    /**
     * @param  array{definition_version: string, section: string, construct: string, text: string, reverse_scored: bool, is_active: bool, is_draft: bool, sort_order: int}  $payload
     */
    private function matches(QuestionnaireItem $existing, array $payload): bool
    {
        return $existing->construct === $payload['construct']
            && $existing->text === $payload['text']
            && $existing->reverse_scored === $payload['reverse_scored']
            && $existing->is_active === $payload['is_active']
            && $existing->is_draft === $payload['is_draft']
            && (int) $existing->sort_order === (int) $payload['sort_order'];
    }
}
