<?php

return [
    'enabled' => (bool) env('COPILOT_SDK_ENABLED', false),
    'node_binary' => env('COPILOT_SDK_NODE_BINARY', 'node'),
    'timeout_seconds' => (int) env('COPILOT_SDK_TIMEOUT_SECONDS', 60),
    'max_tool_calls' => 10,
    'max_tokens' => 10000,
    'provider' => env('COPILOT_SDK_PROVIDER', 'anthropic'),
    'model' => env('COPILOT_SDK_MODEL'),
    'api_key' => env('COPILOT_SDK_API_KEY'),
];
