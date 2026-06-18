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

    'food99' => [
        'base_url' => env('FOOD99_BASE_URL', 'https://openapi.99food.com'),
        'app_id' => env('FOOD99_APP_ID'),
        'app_secret' => env('FOOD99_APP_SECRET'),
        'timeout' => (int) env('FOOD99_TIMEOUT', 20),
        'order_new_sync_mode' => env('FOOD99_ORDER_NEW_SYNC_MODE', 'sync'),
        'webhook_verify_signature' => filter_var(
            env('FOOD99_WEBHOOK_VERIFY_SIGNATURE', false),
            FILTER_VALIDATE_BOOLEAN,
        ),
    ],

];
