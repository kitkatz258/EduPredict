<?php

namespace App\Services\Ai;

use App\Contracts\AiClientInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class OpenRouterClient implements AiClientInterface
{
    public function complete(string $prompt, bool $json = false): ?string
    {
        $key = (string) config('edupredict.ai.api_key');
        if ($key === '') {
            return null;
        }

        $date = now('Asia/Manila')->toDateString();
        $cacheKey = 'ai_daily_calls:'.$date;
        $limit = (int) config('edupredict.ai.daily_limit', 40);
        if ((int) Cache::get($cacheKey, 0) >= $limit) {
            return null;
        }

        $minuteKey = 'ai_minute:'.now('Asia/Manila')->format('Y-m-d-H-i');
        $perMinute = (int) config('edupredict.ai.per_minute_limit', 20);
        if ($perMinute < 1 || RateLimiter::tooManyAttempts($minuteKey, $perMinute)) {
            return null;
        }

        $models = array_values(array_filter([
            (string) config('edupredict.ai.model'),
            ...config('edupredict.ai.fallback_models', []),
        ]));

        if ($models === []) {
            return null;
        }

        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0,
        ];
        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        RateLimiter::hit($minuteKey, 60);

        foreach ($models as $model) {
            try {
                $response = Http::baseUrl(rtrim((string) config('edupredict.ai.base_url'), '/'))
                    ->withToken($key)
                    ->timeout((int) config('edupredict.ai.timeout', 30))
                    ->acceptJson()
                    ->post('/chat/completions', ['model' => $model] + $payload);

                if ($response->failed()) {
                    if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                        continue;
                    }

                    return null;
                }

                $content = $response->json('choices.0.message.content');
                if (! is_string($content) || trim($content) === '') {
                    continue;
                }

                Cache::increment($cacheKey);
                Cache::put($cacheKey, (int) Cache::get($cacheKey, 0), now('Asia/Manila')->endOfDay());

                return $content;
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
