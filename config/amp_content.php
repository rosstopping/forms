<?php

return [
    /*
    | Amp content execution is off by default. A website only uses Amp after an admin
    | selects the Amp engine in its content automation settings and this server-wide
    | switch is enabled. There is no fallback to hosted Copilot.
    */
    'enabled' => (bool) env('AMP_CONTENT_ENABLED', false),
    'api_key' => env('AMP_API_KEY'),
    'mode' => env('AMP_CONTENT_MODE', 'medium'),
    'timeout_seconds' => (int) env('AMP_CONTENT_TIMEOUT_SECONDS', 3600),
    'node_binary' => env('AMP_CONTENT_NODE_BINARY', 'node'),
    'queue_connection' => 'amp-content',
    'queue' => 'amp-content',
];
