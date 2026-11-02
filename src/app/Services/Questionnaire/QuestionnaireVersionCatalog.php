<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireVersion;

/**
 * Resolves questionnaire version metadata.
 * Known instruments stay on their config contract. Newer drafts live in questionnaire_versions.
 * Responses remain pinned by definition_version and are not moved here.
 */
final class QuestionnaireVersionCatalog
{
    public function current(): ?QuestionnaireVersion
    {
        return QuestionnaireVersion::query()
            ->where('state', QuestionnaireVersion::STATE_PUBLISHED)
            ->where('current_slot', 1)
            ->first();
    }

    public function currentCode(): string
    {
        return $this->current()?->code
            ?? (string) config('edupredict.questionnaire.current_version');
    }

    public function find(string $code): ?QuestionnaireVersion
    {
        if ($code === '') {
            return null;
        }

        return QuestionnaireVersion::query()->where('code', $code)->first();
    }

    public function exists(string $code): bool
    {
        return $this->find($code) !== null || $this->config($code) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function config(string $code): array
    {
        $value = config('edupredict.questionnaire.versions.'.$code, []);

        return is_array($value) ? $value : [];
    }

    public function scoring(string $code): ?string
    {
        $scoring = $this->config($code)['scoring'] ?? null;
        if (is_string($scoring) && $scoring !== '') {
            return $scoring;
        }

        return $this->find($code)?->scoring;
    }

    /**
     * @return list<string>
     */
    public function sections(string $code): array
    {
        $sections = $this->config($code)['sections'] ?? null;
        if (is_array($sections) && $sections !== []) {
            return array_values(array_filter($sections, fn (mixed $section): bool => is_string($section)));
        }

        $stored = $this->find($code)?->sections;

        return is_array($stored) ? array_values(array_filter($stored, fn (mixed $section): bool => is_string($section))) : [];
    }

    public function navigation(string $code): string
    {
        $navigation = $this->config($code)['navigation'] ?? null;
        if (is_string($navigation) && $navigation !== '') {
            return $navigation;
        }

        return $this->find($code)?->navigation ?: 'section';
    }

    public function allowsReverseScoring(string $code): bool
    {
        if (array_key_exists('reverse_scoring', $this->config($code))) {
            return (bool) $this->config($code)['reverse_scoring'];
        }

        return (bool) $this->find($code)?->reverse_scoring;
    }

    /**
     * Student-facing description. Configured instruments keep their stored copy.
     *
     * @return array<string, mixed>
     */
    public function presentation(string $code): array
    {
        $config = $this->config($code);
        if ($config !== []) {
            return $config;
        }

        $row = $this->find($code);
        if ($row === null) {
            return [];
        }

        $directions = $this->config(RevisedInstrumentCatalog::VERSION)['directions'] ?? '';

        return [
            'label' => $row->name,
            'status' => $row->panel_approved ? 'panel_approved' : ($row->isMutable() ? 'draft' : 'published'),
            'navigation' => $row->navigation,
            'scoring' => $row->scoring,
            'reverse_scoring' => $row->reverse_scoring,
            'psychometrically_validated' => false,
            'sections' => $row->sections ?? [],
            'directions' => is_string($directions) ? $directions : '',
            'source_note' => 'Not psychometrically validated and not a clinical or diagnostic assessment. Approval of an earlier version is not reused.',
        ];
    }

    public function isMutable(string $code): bool
    {
        $row = $this->find($code);
        if ($row !== null) {
            return $row->isMutable();
        }

        return ($this->config($code)['status'] ?? null) === 'draft';
    }

    public function mutationBlock(QuestionnaireItem $item): ?string
    {
        if (! $this->isMutable((string) $item->definition_version)) {
            return 'Published questionnaires cannot be edited. Create an editable copy.';
        }

        if ($item->answers()->exists()) {
            return 'This definition has student answers and is locked. Create a new version instead.';
        }

        return null;
    }
}
