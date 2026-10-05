<?php

namespace App\Providers;

use App\Contracts\AiClientInterface;
use App\Contracts\PredictorInterface;
use App\Services\Ai\OpenRouterClient;
use App\Services\Prediction\HeuristicPredictor;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiClientInterface::class, OpenRouterClient::class);
        $this->app->bind(PredictorInterface::class, function (): PredictorInterface {
            $driver = (string) config('edupredict.predictor.driver', 'heuristic');

            return match ($driver) {
                'heuristic' => new HeuristicPredictor,
                'http', 'onnx' => throw new InvalidArgumentException("Predictor driver [{$driver}] is reserved for a later phase."),
                default => throw new InvalidArgumentException("Unknown predictor driver [{$driver}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
