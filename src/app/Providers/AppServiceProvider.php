<?php

namespace App\Providers;

use App\Contracts\AiClientInterface;
use App\Contracts\PredictorInterface;
use App\Services\Ai\OpenRouterClient;
use App\Services\Prediction\HeuristicPredictor;
use App\Services\Prediction\HttpPredictor;
use App\Services\Prediction\HttpResearchInferenceClient;
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
        $this->app->singleton(HttpResearchInferenceClient::class, function (): HttpResearchInferenceClient {
            $http = config('edupredict.predictor.http');

            return new HttpResearchInferenceClient(
                baseUrl: (string) ($http['base_url'] ?? ''),
                token: (string) ($http['token'] ?? ''),
                timeoutSeconds: (int) ($http['timeout'] ?? 5),
                connectTimeoutSeconds: (int) ($http['connect_timeout'] ?? 2),
            );
        });
        $this->app->bind(PredictorInterface::class, function (): PredictorInterface {
            $driver = (string) config('edupredict.predictor.driver', 'heuristic');

            return match ($driver) {
                'heuristic' => new HeuristicPredictor,
                'http' => new HttpPredictor,
                'onnx' => throw new InvalidArgumentException('Predictor driver [onnx] is reserved until an approved ONNX model exists.'),
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
