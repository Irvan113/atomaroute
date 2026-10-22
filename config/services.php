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

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'primary_model' => env('GEMINI_PRIMARY_MODEL', 'gemini-3.5-flash-lite'),
        'models' => [
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-3-flash',
            'gemini-2.5-flash',
        ],
    ],

    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
        'redirect' => env('SPOTIFY_REDIRECT_URI', 'https://atoma.test/spotify/callback'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'https://atomaroute.com') . '/auth/google/callback'),
    ],

    'subscription' => [
        'admin_whatsapp' => env('ADMIN_WHATSAPP_NUMBER', '6285263307849'),
        'admin_whatsapp_display' => env('ADMIN_WHATSAPP_DISPLAY', '0852-6330-7849'),
        'bca_number' => env('PAYMENT_BCA_NUMBER', '8730-1234-56'),
        'bca_name' => env('PAYMENT_BCA_NAME', 'ATOMA Productivity'),
        'mandiri_number' => env('PAYMENT_MANDIRI_NUMBER', '137-00-1234567-8'),
        'mandiri_name' => env('PAYMENT_MANDIRI_NAME', 'ATOMA Productivity'),
        'qris_number' => env('PAYMENT_QRIS_NUMBER', '0812-3456-7890'),
    ],

];
