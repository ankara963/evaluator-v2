<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'grade_evaluator_ai' => [
        'enabled' => (bool) env('GRADE_EVALUATOR_AI_ENABLED', false),
        'api_key' => env('GRADE_EVALUATOR_AI_API_KEY') ?: env('OPENAI_API_KEY') ?: env('OPEN_AI_API_KEY'),
        'api_url' => env('GRADE_EVALUATOR_AI_API_URL') ?: env('OPENAI_API_URL', 'https://api.openai.com/v1/responses'),
        'model' => env('GRADE_EVALUATOR_AI_MODEL') ?: env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'timeout' => (int) env('GRADE_EVALUATOR_AI_TIMEOUT', 15),
    ],

];
