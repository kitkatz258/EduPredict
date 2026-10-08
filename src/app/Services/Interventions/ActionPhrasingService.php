<?php

declare(strict_types=1);

namespace App\Services\Interventions;

use App\Contracts\AiClientInterface;
use App\Models\Intervention;
use App\Models\Prediction;
use App\Services\Ai\Deidentifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * One de-identified AI request rephrases the already chosen interventions.
 * Codes outside that set are discarded. Missing codes keep the stored description.
 */
final class ActionPhrasingService
{
    public function __construct(
        private AiClientInterface $ai,
        private Deidentifier $deidentifier,
    ) {}

    /**
     * @param  Collection<int, Intervention>  $interventions
     * @return list<array{code: string, text: string, source: string}>
     */
    public function phrase(Prediction $prediction, Collection $interventions, bool $allowAi = true): array
    {
        $allowed = $interventions->pluck('code')->map(fn (mixed $code): string => (string) $code)->all();
        $parsed = $allowAi ? $this->parse($this->cachedAi($prediction, $interventions), $allowed) : [];

        $rows = [];
        foreach ($interventions as $intervention) {
            $text = $parsed[$intervention->code] ?? null;
            $rows[] = [
                'code' => $intervention->code,
                'text' => $text ?? (string) $intervention->description,
                'source' => $text === null ? 'rule_based' : 'ai',
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Intervention>  $interventions
     */
    private function cachedAi(Prediction $prediction, Collection $interventions): ?string
    {
        $payload = $this->deidentifier->scrub([
            'dropout_risk' => (string) $prediction->dropout_risk,
            'program_shift_flag' => (string) $prediction->program_shift_flag,
            'hurting_factors' => $this->hurtingLabels($prediction),
            'interventions' => $interventions->map(fn (Intervention $intervention): array => [
                'code' => $intervention->code,
                'title' => $intervention->title,
                'description' => $intervention->description,
            ])->values()->all(),
        ]);

        $hash = hash('sha256', (string) json_encode($payload));
        $cacheKey = 'action_phrasing:'.$hash;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $prompt = "Rephrase each intervention for a department head. Use only the supplied codes. Do not add interventions, names, student numbers, percentages, diagnoses, or decisions about admission, academic standing, employment, or discipline. program_shift_flag is a qualitative label, not a score. Return JSON {\"actions\":[{\"code\":\"code\",\"text\":\"one or two supportive sentences\"}]}.\n"
            .json_encode($payload);
        $response = $this->ai->complete($prompt, true);
        if (! is_string($response) || trim($response) === '') {
            return null;
        }

        Cache::put($cacheKey, $response, now()->addDays(30));

        return $response;
    }

    /**
     * @param  list<string>  $allowedCodes
     * @return array<string, string>
     */
    private function parse(?string $response, array $allowedCodes): array
    {
        if ($response === null) {
            return [];
        }

        $response = trim($response);
        if (str_starts_with($response, '```')) {
            $response = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $response) ?? $response;
        }

        $decoded = json_decode(trim($response), true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = $decoded['actions'] ?? $decoded;
        if (! is_array($items)) {
            return [];
        }

        $allowed = array_fill_keys($allowedCodes, true);
        $texts = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $code = trim((string) ($item['code'] ?? ''));
            $text = trim((string) ($item['text'] ?? ''));
            if ($code === '' || $text === '' || ! isset($allowed[$code])) {
                continue;
            }
            if (mb_strlen($text) > 1200) {
                continue;
            }
            $texts[$code] = $text;
        }

        return $texts;
    }

    /**
     * @return list<array{feature: string, label: string}>
     */
    private function hurtingLabels(Prediction $prediction): array
    {
        $factors = $prediction->factors;
        if (! is_array($factors)) {
            return [];
        }

        $groups = [];
        if (array_key_exists('dropout', $factors) || array_key_exists('employability', $factors)) {
            $groups[] = $factors['dropout'] ?? [];
            $groups[] = $factors['employability'] ?? [];
        } elseif (array_is_list($factors)) {
            $groups[] = $factors;
        }

        $labels = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group as $factor) {
                if (! is_array($factor) || ($factor['direction'] ?? '') !== '-') {
                    continue;
                }
                $feature = trim((string) ($factor['feature'] ?? ''));
                $label = trim((string) ($factor['label'] ?? $feature));
                if ($feature === '' || isset($labels[$feature])) {
                    continue;
                }
                $labels[$feature] = ['feature' => $feature, 'label' => $label];
            }
        }

        return array_values($labels);
    }
}
