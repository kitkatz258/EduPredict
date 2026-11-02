<?php

declare(strict_types=1);

namespace App\Services\Questionnaire;

use App\Models\QuestionnaireItem;
use App\Models\QuestionnaireVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class QuestionnaireVersionCreator
{
    public function __construct(
        private readonly QuestionnaireVersionCatalog $catalog,
        private readonly AuditLogger $audit,
    ) {}

    public function create(User $actor, string $name, string $mode, ?string $summary, ?string $selectedCode = null): QuestionnaireVersion
    {
        if (! in_array($mode, ['copy_active', 'copy_selected', 'empty'], true)) {
            throw ValidationException::withMessages([
                'mode' => 'Choose to copy a questionnaire or start empty.',
            ]);
        }

        return DB::transaction(function () use ($actor, $name, $mode, $summary, $selectedCode): QuestionnaireVersion {
            $source = $this->source($mode, $selectedCode);
            $code = $this->uniqueCode();
            $sections = $source !== null
                ? $this->catalog->sections($source->code)
                : QuestionnaireVersionPublisher::SUPPORTED_SECTIONS;

            $version = QuestionnaireVersion::query()->create([
                'code' => $code,
                'name' => $name,
                'state' => QuestionnaireVersion::STATE_DRAFT,
                'current_slot' => null,
                'source_code' => $source?->code,
                'change_summary' => $summary !== null && trim($summary) !== '' ? trim($summary) : null,
                'panel_approved' => false,
                'approval_evidence' => null,
                'scoring' => $source !== null ? ($this->catalog->scoring($source->code) ?? 'section-mean-1-5') : 'section-mean-1-5',
                'reverse_scoring' => $source !== null ? $this->catalog->allowsReverseScoring($source->code) : false,
                'navigation' => $source !== null ? $this->catalog->navigation($source->code) : 'section',
                'sections' => $sections,
                'contract' => $this->contractFor($source),
                'published_at' => null,
                'created_by' => $actor->id,
            ]);

            $copied = 0;
            if ($source !== null) {
                $items = QuestionnaireItem::query()
                    ->where('definition_version', $source->code)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($items as $item) {
                    QuestionnaireItem::query()->create([
                        'section' => $item->section,
                        'definition_version' => $code,
                        'construct' => $item->construct,
                        'text' => $item->text,
                        'reverse_scored' => $item->reverse_scored,
                        'is_active' => true,
                        'is_draft' => true,
                        'sort_order' => $item->sort_order,
                    ]);
                    $copied++;
                }
            }

            $this->audit->record('questionnaire_version_created', $version, [
                'code' => $version->code,
                'name' => $version->name,
                'mode' => $mode,
                'source_code' => $source?->code,
                'copied_items' => $copied,
                'panel_approved' => false,
                'published' => false,
            ], $actor);

            return $version;
        });
    }

    private function source(string $mode, ?string $selectedCode): ?QuestionnaireVersion
    {
        if ($mode === 'empty') {
            return null;
        }

        if ($mode === 'copy_active') {
            $source = QuestionnaireVersion::query()->where('current_slot', 1)->lockForUpdate()->first();
            if ($source === null) {
                throw ValidationException::withMessages([
                    'mode' => 'There is no active questionnaire to copy.',
                ]);
            }

            return $source;
        }

        $source = is_string($selectedCode) ? $this->catalog->find($selectedCode) : null;
        if ($source === null) {
            throw ValidationException::withMessages([
                'mode' => 'Choose a questionnaire version to copy.',
            ]);
        }

        QuestionnaireVersion::query()->whereKey($source->id)->lockForUpdate()->first();

        return $source;
    }

    private function contractFor(?QuestionnaireVersion $source): string
    {
        if ($source === null || $source->contract === QuestionnaireVersion::CONTRACT_SIX_BY_TEN) {
            return QuestionnaireVersion::CONTRACT_SIX_BY_TEN;
        }

        return $source->contract;
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'uq'.Str::lower((string) Str::ulid());
        } while (
            strlen($code) > 40
            || QuestionnaireVersion::query()->where('code', $code)->exists()
            || QuestionnaireItem::query()->where('definition_version', $code)->exists()
        );

        return $code;
    }
}
