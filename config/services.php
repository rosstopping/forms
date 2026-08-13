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

    'github' => [
        'app_id' => env('GITHUB_APP_ID'),
        'app_slug' => env('GITHUB_APP_SLUG'),
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'api_url' => env('GITHUB_API_URL', 'https://api.github.com'),
        'oauth_url' => env('GITHUB_OAUTH_URL', 'https://github.com/login/oauth'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'oauth_url' => env('GOOGLE_OAUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
        'token_url' => env('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'search_console_url' => env('GOOGLE_SEARCH_CONSOLE_URL', 'https://www.googleapis.com/webmasters/v3'),
        'business_profile_account_url' => env('GOOGLE_BUSINESS_PROFILE_ACCOUNT_URL', 'https://mybusinessaccountmanagement.googleapis.com/v1'),
        'business_profile_information_url' => env('GOOGLE_BUSINESS_PROFILE_INFORMATION_URL', 'https://mybusinessbusinessinformation.googleapis.com/v1'),
        'business_profile_v4_url' => env('GOOGLE_BUSINESS_PROFILE_V4_URL', 'https://mybusiness.googleapis.com/v4'),
    ],

    'dataforseo' => [
        'login' => env('DATAFORSEO_LOGIN'),
        'password' => env('DATAFORSEO_PASSWORD'),
        'api_url' => env('DATAFORSEO_API_URL', 'https://api.dataforseo.com/v3'),
        'connect_timeout' => (int) env('DATAFORSEO_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('DATAFORSEO_TIMEOUT', 30),
        'ranked_keywords_limit' => (int) env('DATAFORSEO_RANKED_KEYWORDS_LIMIT', 500),
        'referring_domains_limit' => (int) env('DATAFORSEO_REFERRING_DOMAINS_LIMIT', 250),
        'competitors_limit' => (int) env('DATAFORSEO_COMPETITORS_LIMIT', 25),
        'refresh_days' => (int) env('DATAFORSEO_REFRESH_DAYS', 7),
        'pending_timeout_minutes' => (int) env('DATAFORSEO_PENDING_TIMEOUT_MINUTES', 30),
        'location_code' => (int) env('DATAFORSEO_LOCATION_CODE', 2826),
        'language_code' => env('DATAFORSEO_LANGUAGE_CODE', 'en'),
        'opportunities' => [
            'high_volume_minimum' => (int) env('DATAFORSEO_HIGH_VOLUME_MINIMUM', 100),
            'commercial_volume_minimum' => (int) env('DATAFORSEO_COMMERCIAL_VOLUME_MINIMUM', 20),
            'movement_minimum' => (int) env('DATAFORSEO_MOVEMENT_MINIMUM', 3),
            'per_type_limit' => (int) env('DATAFORSEO_OPPORTUNITIES_PER_TYPE_LIMIT', 10),
            'maximum_results' => (int) env('DATAFORSEO_OPPORTUNITIES_LIMIT', 50),
        ],
    ],

];
