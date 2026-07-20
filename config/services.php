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

    'recordings' => [
    'domain' => env('RECORD_DOMAIN'),
    ],

    'scriptPath' => base_path('app/Jobs/send_telegram.py'),

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
        'leads_channel_id' => env('TELEGRAM_LEADS_CHANNEL_ID'),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'notification' => [
        'admin_emails' => env('NOTIFICATION_ADMIN_EMAILS'),
    ],

    'phonet' => [
        'verify_ssl' => env('PHONET_VERIFY_SSL', true),

    ],
];
