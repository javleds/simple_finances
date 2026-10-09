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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'verification_code_expiration_minutes' => env('TELEGRAM_VERIFICATION_CODE_EXPIRATION_MINUTES', 10),
    ],

    'whatsapp' => [
        'phone_number_id' => env('WA_PHONE_NUMBER_ID'),
        'access_token' => env('WA_ACCESS_TOKEN'),
        'verify_token' => env('WA_API_TOKEN'),
        'app_secret' => env('WA_APP_SECRET'),
        'graph_version' => 'v25.0',
        'auth_template_name' => env('WA_AUTH_TEMPLATE_NAME'),
        'auth_template_language' => env('WA_AUTH_TEMPLATE_LANGUAGE', 'es_MX'),
    ],

    'openai' => [
        'api_token' => env('OPENAI_API_TOKEN'),
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
        'audio_model' => env('OPENAI_AUDIO_MODEL', 'whisper-1'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 1000),
        'temperature' => env('OPENAI_TEMPERATURE', 0.3),
    ],

];
