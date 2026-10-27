<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Predictor driver
    |--------------------------------------------------------------------------
    |
    | heuristic = placeholder weighted rules for student predictions.
    | http = research adapter. It does not score student FeatureSets.
    | onnx remains reserved.
    |
    */
    'predictor' => [
        'driver' => env('PREDICTOR_DRIVER', 'heuristic'),
        'placeholder_version' => 'placeholder-heuristic-v1',
        'http' => [
            'base_url' => rtrim((string) env('ML_API_BASE_URL', 'http://ml:8000'), '/'),
            'token' => env('ML_API_TOKEN', ''),
            'timeout' => max(1, (int) env('ML_API_TIMEOUT', 5)),
            'connect_timeout' => max(1, (int) env('ML_API_CONNECT_TIMEOUT', 2)),
            // Student FeatureSet scoring stays off. There is no validity gate for UCC inputs.
            'student_scoring_enabled' => false,
        ],
        'employability_base' => 50,
        'dropout_base' => 0.20,
        'dropout_moderate_at' => 0.30,
        'dropout_high_at' => 0.60,
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
        'per_minute_limit' => (int) env('AI_PER_MINUTE_LIMIT', 20),
        'extraction_fallback_enabled' => true,
    ],

    'grades' => [
        'passing_max' => 3.00,
        'failing' => 5.00,
        'numeric_scale' => [1.00, 1.25, 1.50, 1.75, 2.00, 2.25, 2.50, 2.75, 3.00, 5.00],
        'incomplete_tokens' => ['INC', 'INCOMPLETE'],
        'dropped_tokens' => ['DRP', 'W', 'WITHDRAWN', 'DROPPED', 'DROP', 'UD', 'UNOFFICIAL', 'UNOFFICIALLYDROPPED'],
        'gpa_excluded_prefixes' => ['NSTP'],
        'subject_code_pattern' => '/^[A-Z]{2,8}\s?\d{1,3}[A-Z]?$/',
        'upload_max_kb' => 10240,
    ],

    'prediction' => [
        'daily_limit' => 2,
        'timezone' => 'Asia/Manila',
        'limited_history_semesters' => 2,
    ],

    /*
    | Password for seeded demo and synthetic accounts. Empty keeps the documented
    | development password; a public deployment sets DEMO_SEED_PASSWORD.
    */
    'demo' => [
        'password' => env('DEMO_SEED_PASSWORD', ''),
    ],

    /*
    | Newly provisioned students sign in with their student number. The initial
    | password is the registered last name in uppercase (spaces and punctuation
    | kept). Existing claimed accounts are never reset. The student must change
    | the password and accept consent before using the app.
    */
    'provisioning' => [
        'placeholder_email_domain' => 'students.edupredict.test',
    ],

    /*
    | Qualitative program-shift reading. Philippine scale: a higher GWA is weaker.
    | This is not a model score and must never be shown as a percentage.
    */
    'program_shift' => [
        'healthy_construct_min' => 60,
        'low_construct_max' => 40,
        'high_procrastination_min' => 70,
        'poor_gwa_min' => 2.75,
        'healthy_other_gwa_max' => 2.25,
        'min_major_units' => 6,
        'min_other_units' => 3,
        'major_gap' => 0.75,
        'broad_failed_min' => 2,
    ],

    /*
    | Recommended actions stay inside the seeded intervention list.
    | shift_rank_bonus only orders the list. It is not a displayed score.
    */
    'interventions' => [
        'max_actions' => 5,
        'shift_rank_bonus' => 0.5,
    ],

    'consent' => [
        'current_version' => 'v2',
        'versions' => [
            'v2' => [
                'effective_on' => '2026-10-09',
                'paragraphs' => [
                    'EduPredict collects academic records, socioeconomic information, skills and experience, and questionnaire answers to estimate employability and dropout risk and to suggest career matches and support options. The current pilot covers the College of Liberal Arts and Sciences (CLAS), University of Caloocan City.',
                    'Department heads see student-level results only for students in their assigned department or program, and never your raw socioeconomic answers. The dean sees aggregated college figures only, never individual students. Administrators manage accounts and the eligible-student list. Predictions are advisory and do not decide admission, academic standing, employment, or discipline.',
                    'When an optional AI service is used, only de-identified features or cleaned grade-row text are sent. Your name, student number, email, and birthdate are not sent. Grade-report uploads should not include pages showing more than your grades.',
                    'You may access and correct your data, download a copy, or request account deletion. An administrator processes a deletion request. Approval deactivates the login and keeps prediction history.',
                ],
            ],
            'v1' => [
                'effective_on' => '2026-10-05',
                'paragraphs' => [
                    'EduPredict collects academic records, socioeconomic information, skills and experience, and questionnaire answers to estimate employability and dropout risk and to suggest career matches and support options.',
                    'Faculty see only their advisees. Department heads see their program. Deans see their college. Administrators see institution-wide data. Predictions are advisory and do not decide admission, academic standing, employment, or discipline.',
                    'When an optional AI service is used, only de-identified features or cleaned grade-row text are sent. Your name, student number, email, and birthdate are not sent. Grade-report uploads should not include pages showing more than your grades.',
                    'You may access and correct your data, download a copy, or request account deletion. An administrator processes a deletion request. Approval deactivates the login and keeps prediction history.',
                ],
            ],
        ],
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
        // `ojt_internship` feeds the internship count; every other type is work experience.
        'experience_types' => [
            'ojt_internship' => 'OJT / Internship',
            'part_time' => 'Part-time job',
            'full_time' => 'Full-time job',
            'freelance' => 'Freelance or contract work',
            'student_assistant' => 'Student assistantship',
            'volunteer' => 'Volunteer or organization work',
            'other' => 'Other work experience',
        ],
    ],

    'questionnaire' => [
        'current_version' => 'ucc-panel-revised-60-v2',
        'versions' => [
            'draft-v1' => [
                'label' => 'Draft research instrument v1',
                'status' => 'draft',
                'navigation' => 'construct',
                'scoring' => 'legacy-0-100',
                'reverse_scoring' => true,
                'sections' => ['academic_behavior'],
                'source_note' => 'Items remain draft until their research source and adaptation are approved.',
            ],
            'ucc-panel-approved-v1' => [
                'label' => 'UCC panel-approved instrument v1',
                'status' => 'panel_approved',
                'navigation' => 'section',
                'scoring' => 'section-mean-1-5',
                'reverse_scoring' => false,
                'psychometrically_validated' => false,
                'sections' => ['academic', 'socioeconomic', 'behavioral', 'competence', 'psychological_capital', 'self_perceived_employability'],
                'directions' => 'Please indicate how much you agree with each statement based on your current experience as a student. Check one box for each statement, using the scale below. There are no right or wrong answers, so please answer honestly.',
                'source_note' => 'Panel-approved for the UCC capstone in October 2026. The source PDFs still describe this instrument as a draft for expert validation. It is not psychometrically validated for UCC CLAS students and is not a clinical or diagnostic assessment.',
            ],
            'ucc-panel-revised-60-v2' => [
                'label' => 'UCC revised 60-item instrument v2 (pending panel reapproval)',
                'status' => 'draft',
                'navigation' => 'section',
                'scoring' => 'section-mean-1-5',
                'reverse_scoring' => false,
                'psychometrically_validated' => false,
                'sections' => ['academic', 'socioeconomic', 'behavioral', 'competence', 'psychological_capital', 'self_perceived_employability'],
                'directions' => 'Please indicate how much you agree with each statement based on your current experience as a student. Check one box for each statement, using the scale below. There are no right or wrong answers, so please answer honestly.',
                'source_note' => 'Revised 60-item draft pending adviser and panel reapproval. It is not panel-approved and is not psychometrically validated. Existing 36-item submissions stay on ucc-panel-approved-v1.',
            ],
        ],
        'sections' => [
            'academic_behavior' => [
                'label' => 'Academic behavior',
                'description' => 'Legacy draft-v1 constructs. Kept so earlier responses stay readable under that version.',
                'constructs' => ['study_habits', 'time_management', 'motivation', 'procrastination', 'engagement'],
            ],
            'academic' => [
                'label' => 'Academic factors',
                'short' => 'A. Academic',
                'heading' => 'Section A. Academic Factors',
                'code' => 'A',
                'description' => 'Academic self-perception. Context only. Compared with grades and not a model input.',
                'constructs' => ['academic_self_perception'],
                'role' => 'context',
                'items' => 6,
            ],
            'socioeconomic' => [
                'label' => 'Socioeconomic factors',
                'short' => 'S. Socioeconomic',
                'heading' => 'Section S. Socioeconomic Factors',
                'code' => 'S',
                'description' => 'Financial and access context. Answers are encrypted. They are not copied into staff views or prediction history.',
                'constructs' => ['socioeconomic_context'],
                'role' => 'context',
                'sensitive' => true,
                'items' => 6,
            ],
            'behavioral' => [
                'label' => 'Behavioral factors',
                'short' => 'B. Behavioral',
                'heading' => 'Section B. Behavioral Factors / Student Engagement',
                'code' => 'B',
                'description' => 'Student engagement. Context for recommendations. These answers do not change the dropout probability.',
                'constructs' => ['behavioral_engagement'],
                'role' => 'context',
                'items' => 6,
            ],
            'competence' => [
                'label' => 'Competence',
                'short' => 'C. Competence',
                'heading' => 'Section C. Competence / Employability Skills',
                'code' => 'C',
                'description' => 'Employability skills. The section mean is stored for a future model and is not sent to the current predictor.',
                'constructs' => ['competence'],
                'role' => 'not_connected',
                'items' => 8,
            ],
            'psychological_capital' => [
                'label' => 'Psychological capital',
                'short' => 'P. Psych. capital',
                'heading' => 'Section P. Psychological Capital / Career Readiness',
                'code' => 'P',
                'description' => 'Career readiness. The section mean is stored for a future model and is not sent to the current predictor.',
                'constructs' => ['psychological_capital'],
                'role' => 'not_connected',
                'items' => 6,
            ],
            'self_perceived_employability' => [
                'label' => 'Self-perceived employability',
                'short' => 'E. Self-perceived',
                'heading' => 'Section E. Self-Perceived Employability / Readiness',
                'code' => 'E',
                'description' => 'Descriptive self-assessment only. Not a model input.',
                'constructs' => ['self_perceived_employability'],
                'role' => 'descriptive',
                'items' => 4,
            ],
        ],
        'constructs' => [
            'study_habits' => 'Study habits',
            'time_management' => 'Time management',
            'motivation' => 'Motivation',
            'procrastination' => 'Procrastination',
            'engagement' => 'Engagement',
            'academic_self_perception' => 'Academic factors',
            'socioeconomic_context' => 'Socioeconomic factors',
            'behavioral_engagement' => 'Behavioral factors',
            'competence' => 'Competence',
            'psychological_capital' => 'Psychological capital',
            'self_perceived_employability' => 'Self-perceived employability',
        ],
        'likert' => [
            1 => 'Strongly Disagree',
            2 => 'Disagree',
            3 => 'Neutral',
            4 => 'Agree',
            5 => 'Strongly Agree',
        ],
        'interpretation' => [
            ['max' => 1.80, 'level' => 'Very Low'],
            ['max' => 2.60, 'level' => 'Low'],
            ['max' => 3.40, 'level' => 'Moderate'],
            ['max' => 4.20, 'level' => 'High'],
            ['max' => 5.00, 'level' => 'Very High'],
        ],
        'context_flag_at_or_below' => 2.60,
    ],

    /*
    | Development, PHPUnit, and a future defense copy must stay on different
    | databases. The application does not connect to the defense database.
    */
    'postgres' => [
        'development_database' => env('PGSQL_DEVELOPMENT_DATABASE', 'edupredict'),
        'testing_database' => env('PGSQL_TESTING_DATABASE', 'edupredict_testing'),
        'defense_database' => env('PGSQL_DEFENSE_DATABASE', 'edupredict_defense'),
    ],

];
