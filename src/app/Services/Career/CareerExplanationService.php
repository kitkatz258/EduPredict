<?php

declare(strict_types=1);

namespace App\Services\Career;

use App\Contracts\AiClientInterface;
use App\Services\Ai\Deidentifier;
use Illuminate\Support\Facades\Cache;

/**
 * One de-identified AI request for all matches, with a template fallback.
 */
final class CareerExplanationService
{
    public function __construct(
        private AiClientInterface $ai,
        private Deidentifier $deidentifier,
    ) {}

    /**
     * @param  list<array{psoc_code: string, title: string, score: int, matched: list<string>, missing: list<string>}>  $matches
     * @return list<array{psoc_code: string, text: string, source: string}>
     */
    public function explain(string $programCode, ?float $gwa, array $matches): array
    {
        $payload = $this->deidentifier->scrub([
            'program_code' => strtoupper(trim($programCode)),
            'gwa_band' => $this->gwaBand($gwa),
            'matches' => array_map(fn (array $match): array => [
                'psoc_code' => $match['psoc_code'],
                'title' => $match['title'],
                'compatibility_score' => $match['score'],
                'matched_skills' => $match['matched'],
                'missing_skills' => $match['missing'],
            ], $matches),
        ]);

        $aiText = $this->cachedAi($payload);
        $byCode = $this->parse($aiText, array_column($matches, 'psoc_code'));

        $rows = [];
        foreach ($matches as $match) {
            $text = $byCode[$match['psoc_code']] ?? null;
            $rows[] = [
                'psoc_code' => $match['psoc_code'],
                'text' => $text ?? $this->template($programCode, $match),
                'source' => $text === null ? 'template' : 'ai',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cachedAi(array $payload): ?string
    {
        $hash = hash('sha256', (string) json_encode($payload));
        $cacheKey = 'career_explanation:'.$hash;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $prompt = "Write a 2-3 sentence explanation for each career match. Use only the JSON facts. Do not invent employers, job offers, names, or student numbers. Return JSON {\"explanations\":[{\"psoc_code\":\"code\",\"text\":\"...\"}]}.\n"
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

        $decoded = json_decode($response, true);
        if (! is_array($decoded)) {
            return [];
        }

        $items = $decoded['explanations'] ?? $decoded;
        if (! is_array($items)) {
            return [];
        }

        $allowed = array_fill_keys($allowedCodes, true);
        $texts = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $code = (string) ($item['psoc_code'] ?? '');
            $text = trim((string) ($item['text'] ?? ''));
            if ($code === '' || $text === '' || ! isset($allowed[$code])) {
                continue;
            }
            $texts[$code] = $text;
        }

        return $texts;
    }

    /**
     * @param  array{psoc_code: string, title: string, score: int, matched: list<string>, missing: list<string>}  $match
     */
    private function template(string $programCode, array $match): string
    {
        $program = strtoupper(trim($programCode));
        $matched = $match['matched'] === []
            ? 'The program link is the main overlap in this estimate.'
            : 'Shared skill tags: '.implode(', ', $match['matched']).'.';
        $missing = $match['missing'] === []
            ? 'The listed skill tags are already on the profile.'
            : 'Skill tags not yet on the profile: '.implode(', ', $match['missing']).'.';

        return "{$match['title']} (PSOC {$match['psoc_code']}) is a broad occupational category, not a job offer. "
            ."The {$program} program and a compatibility score of {$match['score']} out of 100 are the basis of this match. "
            ."{$matched} {$missing}";
    }

    private function gwaBand(?float $gwa): string
    {
        if ($gwa === null) {
            return 'unknown';
        }

        return match (true) {
            $gwa <= 1.75 => '1.75 or better',
            $gwa <= 2.25 => '1.76-2.25',
            $gwa <= 2.75 => '2.26-2.75',
            $gwa <= 3.00 => '2.76-3.00',
            default => 'below passing',
        };
    }
}
