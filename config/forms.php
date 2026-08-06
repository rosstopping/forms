<?php

return [
    'default_recipient' => env('FORMS_DEFAULT_RECIPIENT', 'hello@example.com'),
    'from_address' => env('FORMS_FROM_ADDRESS', 'hello@example.com'),
    'from_name' => env('FORMS_FROM_NAME', 'Central Forms'),
    'auto_register_websites' => filter_var(env('FORMS_AUTO_REGISTER_WEBSITES', true), FILTER_VALIDATE_BOOL),
    'auto_register_forms' => filter_var(env('FORMS_AUTO_REGISTER_FORMS', true), FILTER_VALIDATE_BOOL),
    'rate_limit_per_minute' => (int) env('FORMS_RATE_LIMIT_PER_MINUTE', 10),
    'max_payload_kb' => (int) env('FORMS_MAX_PAYLOAD_KB', 256),
    'max_field_length' => (int) env('FORMS_MAX_FIELD_LENGTH', 10000),
    'webhook_timeout' => (int) env('FORMS_WEBHOOK_TIMEOUT', 10),
    'webhook_response_max_length' => (int) env('FORMS_WEBHOOK_RESPONSE_MAX_LENGTH', 2000),
    'health_reports' => [
        'timeout' => (int) env('FORMS_HEALTH_REPORT_TIMEOUT', 10),
        'connect_timeout' => (int) env('FORMS_HEALTH_REPORT_CONNECT_TIMEOUT', 5),
        'max_response_kb' => (int) env('FORMS_HEALTH_REPORT_MAX_RESPONSE_KB', 1024),
        'frequency_days' => (int) env('FORMS_HEALTH_REPORT_FREQUENCY_DAYS', 7),
    ],
    'spam' => [
        'threshold' => (int) env('FORMS_SPAM_THRESHOLD', 3),
        'max_links' => (int) env('FORMS_SPAM_MAX_LINKS', 3),
        'long_content_length' => (int) env('FORMS_SPAM_LONG_CONTENT_LENGTH', 4000),
        'promotional_phrases' => [
            '50% off',
            'free shipping',
            'order yours now',
            'buy now',
            'limited time offer',
        ],
    ],
];
