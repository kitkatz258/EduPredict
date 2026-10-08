<?php

declare(strict_types=1);

namespace App\Services\Career;

use App\Models\CareerMatch;
use App\Models\Prediction;
use App\Models\PsocOccupation;
use App\Models\Student;
use App\Services\Profile\SkillsExperienceRecords;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stores the top 5 PSOC matches for one prediction. Rows are inserted once.
 */
final class CareerMatchBuilder
{
    public function __construct(
        private CareerCompatibilityScorer $scorer,
        private CareerExplanationService $explanations,
        private SkillsExperienceRecords $skillRecords,
    ) {}

    /**
     * @return Collection<int, CareerMatch>
     */
    public function ensure(Prediction $prediction): Collection
    {
        $existing = $prediction->careerMatches()->with('occupation')->get()
            ->sortByDesc(fn (CareerMatch $match): float => (float) $match->compatibility_score)
            ->values();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $prediction->loadMissing('student.program');
        $student = $prediction->student;
        if ($student === null) {
            return collect();
        }

        $occupations = PsocOccupation::query()->orderBy('psoc_code')->get();
        if ($occupations->isEmpty()) {
            return collect();
        }

        $programCode = (string) ($student->program?->code ?? '');
        $gwa = isset($prediction->feature_snapshot['gwa']) && is_numeric($prediction->feature_snapshot['gwa'])
            ? (float) $prediction->feature_snapshot['gwa']
            : null;
        $tags = $this->studentTags($prediction, $student);

        $ranked = [];
        foreach ($occupations as $occupation) {
            $result = $this->scorer->score(
                $programCode,
                $tags,
                $gwa,
                array_map('strval', $occupation->skill_tags ?? []),
                array_map('strval', $occupation->related_program_codes ?? []),
            );
            $ranked[] = [
                'occupation' => $occupation,
                'psoc_code' => $occupation->psoc_code,
                'title' => $occupation->title,
                'score' => $result['score'],
                'matched' => $result['matched'],
                'missing' => $result['missing'],
            ];
        }

        usort($ranked, function (array $left, array $right): int {
            return $right['score'] <=> $left['score'] ?: $left['psoc_code'] <=> $right['psoc_code'];
        });
        $top = array_slice($ranked, 0, 5);
        $explained = $this->explanations->explain($programCode, $gwa, $top);
        $textByCode = [];
        foreach ($explained as $row) {
            $textByCode[$row['psoc_code']] = $row;
        }

        DB::transaction(function () use ($prediction, $top, $textByCode): void {
            foreach ($top as $row) {
                /** @var PsocOccupation $occupation */
                $occupation = $row['occupation'];
                $copy = $textByCode[$row['psoc_code']] ?? null;
                CareerMatch::query()->create([
                    'prediction_id' => $prediction->id,
                    'psoc_occupation_id' => $occupation->id,
                    'compatibility_score' => $row['score'],
                    'explanation' => $copy['text'] ?? '',
                    'explanation_source' => $copy['source'] ?? 'template',
                    'matched_skills' => $row['matched'],
                    'missing_skills' => $row['missing'],
                ]);
            }
        });

        return $prediction->careerMatches()->with('occupation')->get()
            ->sortByDesc(fn (CareerMatch $match): float => (float) $match->compatibility_score)
            ->values();
    }

    /**
     * Predictions with a stored assessment snapshot use the entries they were built
     * from; older rows fall back to the current saved entries.
     *
     * @return list<string>
     */
    private function studentTags(Prediction $prediction, Student $student): array
    {
        $snapshot = $prediction->assessment_snapshot['skills_experience'] ?? null;

        return $this->skillRecords->tags(is_array($snapshot) ? $snapshot : $this->skillRecords->snapshot($student));
    }
}
