<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Predictor driver
    |--------------------------------------------------------------------------
    |
    | heuristic = placeholder weighted rules (this build).
    | http / onnx are reserved for the trained models in a later phase.
    |
    */
    'predictor' => [
        'driver' => env('PREDICTOR_DRIVER', 'heuristic'),
        'placeholder_version' => 'placeholder-heuristic-v0',
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openrouter'),
        'base_url' => env('AI_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('AI_API_KEY', ''),
        'model' => env('AI_MODEL', ''),
        'vision_model' => env('AI_VISION_MODEL', ''),
        'fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_FALLBACK_MODELS', '')),
        ))),
        'timeout' => (int) env('AI_TIMEOUT', 30),
        'daily_limit' => (int) env('AI_DAILY_LIMIT', 40),
        'extraction_fallback_enabled' => true,
    ],

    'grades' => [
        'passing_max' => 3.00,
        'failing' => 5.00,
        'numeric_scale' => [1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00, 5.00],
        'incomplete_tokens' => ['INC', 'INCOMPLETE'],
        'dropped_tokens' => ['DRP', 'W', 'WITHDRAWN'],
        'inc_gpa_weight' => 4.00,
        'gpa_excluded_prefixes' => ['NSTP'],
    ],

    'prediction' => [
        'cooldown_hours' => 24,
        'limited_history_semesters' => 2,
        'min_profile_completeness' => 80,
    ],

    'consent' => [
        'current_version' => 'v1',
    ],

];
