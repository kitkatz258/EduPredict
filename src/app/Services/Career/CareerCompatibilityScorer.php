<?php

declare(strict_types=1);

namespace App\Services\Career;

/**
 * Deterministic 0–100 compatibility. Not a trained model.
 *
 * Program relevance is 45 when the program code is listed, otherwise 0.
 * Skill overlap is 40 times the share of the occupation's tags the student has.
 * Academic strength is 0–15 from GWA on the Philippine scale (lower is stronger).
 */
final class CareerCompatibilityScorer
{
    /**
     * @param  list<string>  $studentTags
     * @param  list<string>  $occupationTags
     * @param  list<string>  $relatedProgramCodes
     * @return array{score: int, matched: list<string>, missing: list<string>}
     */
    public function score(
        string $programCode,
        array $studentTags,
        ?float $gwa,
        array $occupationTags,
        array $relatedProgramCodes,
    ): array {
        $program = strtoupper(trim($programCode));
        $student = $this->normalize($studentTags);
        $occupation = $this->normalize($occupationTags);
        $programs = array_map(fn (string $code): string => strtoupper(trim($code)), $relatedProgramCodes);

        $programPoints = in_array($program, $programs, true) ? 45.0 : 0.0;
        $matched = array_values(array_intersect($occupation, $student));
        $missing = array_values(array_diff($occupation, $student));
        sort($matched);
        sort($missing);
        $skillPoints = $occupation === [] ? 0.0 : 40.0 * (count($matched) / count($occupation));

        $score = (int) round(max(0, min(100, $programPoints + $skillPoints + $this->academicPoints($gwa))));

        return [
            'score' => $score,
            'matched' => $matched,
            'missing' => $missing,
        ];
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    public function normalize(array $tags): array
    {
        $clean = [];
        foreach ($tags as $tag) {
            $tag = strtolower(trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $tag)) ?? ''));
            if ($tag !== '') {
                $clean[$tag] = $tag;
            }
        }

        $values = array_values($clean);
        sort($values);

        return $values;
    }

    private function academicPoints(?float $gwa): float
    {
        if ($gwa === null) {
            return 5.0;
        }

        return match (true) {
            $gwa <= 1.75 => 15.0,
            $gwa <= 2.25 => 12.0,
            $gwa <= 2.75 => 8.0,
            $gwa <= 3.00 => 4.0,
            default => 0.0,
        };
    }
}
