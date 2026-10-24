<?php

namespace App\Providers;

use App\Contracts\AiClientInterface;
use App\Contracts\PredictorInterface;
use App\Database\PostgresConnection;
use App\Services\Ai\OpenRouterClient;
use App\Services\Database\PostgresTestDatabaseGuard;
use App\Services\Prediction\HeuristicPredictor;
use App\Services\Prediction\HttpPredictor;
use App\Services\Prediction\HttpResearchInferenceClient;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new PostgresConnection($connection, $database, $prefix, $config);
        });

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
        app(PostgresTestDatabaseGuard::class)->assertSafe(
            (string) $this->app->environment(),
            (string) config('database.default'),
            config('database.connections.pgsql.database'),
            (string) config('edupredict.postgres.testing_database'),
            (string) config('edupredict.postgres.development_database'),
            (string) config('edupredict.postgres.defense_database'),
        );
    }
}
