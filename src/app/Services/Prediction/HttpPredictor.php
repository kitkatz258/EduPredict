<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Contracts\PredictorInterface;

/**
 * HTTP driver for the container. It refuses student FeatureSets.
 * Research calls go through HttpResearchInferenceClient and are not a fallback.
 */
final class HttpPredictor implements PredictorInterface
{
    public function predictEmployability(FeatureSet $features): PredictionResult
    {
        $this->refuse();
    }

    public function predictDropout(FeatureSet $features): PredictionResult
    {
        $this->refuse();
    }

    /**
     * @return never
     */
    private function refuse(): void
    {
        throw new ResearchInferenceException(
            'student_scoring_disabled',
            PredictionRequester::RESEARCH_ENGINE_BLOCK.' UCC questionnaire means and grades are not sent to the research models.',
        );
    }
}
