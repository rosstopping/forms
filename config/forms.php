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
];
