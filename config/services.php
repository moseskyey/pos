<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
     | FastLipa mobile money (STK push). Credentials live in Settings (encrypted);
     | endpoint paths are configurable here in case the provider changes them.
     */
    'fastlipa' => [
        'initiate_path' => env('FASTLIPA_INITIATE_PATH', '/api/create-transaction'),
        'status_path' => env('FASTLIPA_STATUS_PATH', '/api/status-transaction'),
        'signature_header' => env('FASTLIPA_SIGNATURE_HEADER', 'X-FastLipa-Signature'),
        'timeout' => (int) env('FASTLIPA_TIMEOUT', 20),
        'allowed_ips' => array_filter(explode(',', (string) env('FASTLIPA_ALLOWED_IPS', ''))),
    ],

    'beem' => [
        'url' => env('BEEM_SMS_URL', 'https://apisms.beem.africa/v1/send'),
        'timeout' => (int) env('BEEM_TIMEOUT', 15),
    ],
];
