<?php

return [
    'customer_repositories_enabled' => (bool) env('COPILOT_SDK_CUSTOMER_REPOSITORIES_ENABLED', false),
    'customer_repository_admin_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('COPILOT_SDK_CUSTOMER_REPOSITORY_ADMIN_IDS', ''))))),
    'enabled' => (bool) env('COPILOT_SDK_ENABLED', false),
    'node_binary' => env('COPILOT_SDK_NODE_BINARY', 'node'),
    'timeout_seconds' => (int) env('COPILOT_SDK_TIMEOUT_SECONDS', 60),
    'max_tool_calls' => 10,
    'max_tokens' => 10000,
    'provider' => env('COPILOT_SDK_PROVIDER', 'anthropic'),
    'model' => env('COPILOT_SDK_MODEL'),
    'api_key' => env('COPILOT_SDK_API_KEY'),
];
