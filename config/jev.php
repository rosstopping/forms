<?php

return [
    'enabled' => (bool) env('JEV_SHADOW_ENABLED', false),
    'key' => env('TYPESAFE_API_KEY'),
    'model' => env('JEV_MODEL', 'jev-latest'),
    'timeout_seconds' => 15,
    'max_request_bytes' => 40000,
    'max_batch_bytes' => 2000000,
    'max_run_seconds' => 300,
];
