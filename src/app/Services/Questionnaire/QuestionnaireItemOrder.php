<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reorders questions inside one draft section. Stable question IDs are not rewritten.
 */
final class QuestionnaireItemOrder
{
    public function nextPosition(string $version, string $section): int
    {
        $max = QuestionnaireItem::query()
            ->where('definition_version', $version)
            ->where('section', $section)
            ->lockForUpdate()
            ->max('sort_order');

        return ((int) $max) + 1;
    }

    public function move(QuestionnaireItem $item, int $direction): void
    {
        if (! in_array($direction, [-1, 1], true)) {
            return;
        }

        DB::transaction(function () use ($item, $direction): void {
            $this->compact($item->definition_version, $item->section);
            $item->refresh();
            $items = $this->lockedSection($item->definition_version, $item->section);
            $index = $items->search(fn (QuestionnaireItem $row): bool => $row->id === $item->id);
            $target = is_int($index) ? $index + $direction : -1;
            if (! is_int($index) || $target < 0 || $target >= $items->count()) {
                return;
            }

            $other = $items[$target];
            $from = (int) $item->sort_order;
            $to = (int) $other->sort_order;
            $item->update(['sort_order' => $to]);
            $other->update(['sort_order' => $from]);
        });
    }

    public function relocate(QuestionnaireItem $item, string $section): void
    {
        if ($item->section === $section) {
            return;
        }

        $previous = $item->section;
        $version = $item->definition_version;
        $item->update([
            'section' => $section,
            'sort_order' => $this->nextPosition($version, $section),
        ]);
        $this->compact($version, $previous);
    }

    public function compact(string $version, string $section): void
    {
        $position = 1;
        foreach ($this->lockedSection($version, $section) as $row) {
            if ((int) $row->sort_order !== $position) {
                $row->update(['sort_order' => $position]);
            }
            $position++;
        }
    }

    /**
     * @return Collection<int, QuestionnaireItem>
     */
    private function lockedSection(string $version, string $section)
    {
        return QuestionnaireItem::query()
            ->where('definition_version', $version)
            ->where('section', $section)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->values();
    }
}
