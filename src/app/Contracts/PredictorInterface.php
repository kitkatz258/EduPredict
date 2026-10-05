<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Prediction\FeatureSet;
use App\Services\Prediction\PredictionResult;

interface PredictorInterface
{
    public function predictEmployability(FeatureSet $features): PredictionResult;

    public function predictDropout(FeatureSet $features): PredictionResult;
}
