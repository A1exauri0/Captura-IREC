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

    'stellum_bridge' => [
        'url'              => env('STELLUM_BRIDGE_URL', ''),
        'token'            => env('STELLUM_BRIDGE_TOKEN', ''),
        'pdf_cache_dir'    => env('PDF_CACHE_DIR', storage_path('app/pdf-cache')),
        'pdf_cache_max_mb' => (int) env('PDF_CACHE_MAX_MB', 20480),
        'pdf_cache_ttl_h'  => (int) env('PDF_CACHE_TTL_HOURS', 12),
        'pdf_cache_xaccel' => (bool) env('PDF_CACHE_XACCEL', false),
        'pdf_prefetch_buf' => (int) env('PDF_PREFETCH_BUFFER', 60),
    ],

    'biometrico' => [
        'token'              => env('BIOMETRICO_API_TOKEN', ''),
        'antirebote_minutos' => (int) env('BIOMETRICO_ANTIREBOTE_MINUTOS', 5),
        'tolerancia_default' => (int) env('BIOMETRICO_TOLERANCIA_MINUTOS', 10),
    ],

];
