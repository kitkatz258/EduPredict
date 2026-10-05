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
        'subject_code_pattern' => '/^[A-Z]{2,8}\s?\d{1,3}[A-Z]?$/',
        'upload_max_kb' => 10240,
    ],

    'prediction' => [
        'cooldown_hours' => 24,
        'limited_history_semesters' => 2,
        'min_profile_completeness' => 80,
    ],

    'consent' => [
        'current_version' => 'v1',
    ],

    'profile' => [
        'income_brackets' => [
            'below_10k' => 'Below ₱10,000',
            '10k_20k' => '₱10,000–₱20,000',
            '20k_40k' => '₱20,001–₱40,000',
            '40k_70k' => '₱40,001–₱70,000',
            'above_70k' => 'Above ₱70,000',
            'prefer_not_to_say' => 'Prefer not to say',
        ],
        'scholarship_statuses' => [
            'none' => 'No scholarship',
            'partial' => 'Partial scholarship',
            'full' => 'Full scholarship',
            'government' => 'Government scholarship',
            'private' => 'Private scholarship',
        ],
        'employment_statuses' => [
            'unemployed' => 'Not employed',
            'part_time' => 'Part-time',
            'working_student' => 'Working student',
            'full_time' => 'Full-time',
            'self_employed' => 'Self-employed',
        ],
        'living_arrangements' => [
            'with_family' => 'With family',
            'boarding' => 'Boarding house',
            'dormitory' => 'Dormitory',
            'relative' => 'With a relative',
            'renting' => 'Renting',
        ],
        'internet_access' => [
            'yes' => 'Reliable internet',
            'unreliable' => 'Unreliable internet',
            'no' => 'No internet',
        ],
        'device_access' => [
            'yes' => 'Own device',
            'shared' => 'Shared device',
            'no' => 'No device',
        ],
        'study_space' => [
            'yes' => 'Has a study space',
            'no' => 'No study space',
        ],
    ],

    'questionnaire' => [
        'constructs' => [
            'study_habits' => 'Study habits',
            'time_management' => 'Time management',
            'motivation' => 'Motivation',
            'procrastination' => 'Procrastination',
            'engagement' => 'Engagement',
        ],
        'likert' => [
            1 => 'Strongly disagree',
            2 => 'Disagree',
            3 => 'Neutral',
            4 => 'Agree',
            5 => 'Strongly agree',
        ],
    ],

];
