<?php

return [
    'max_active_prompts' => (int) env('AI_VISIBILITY_MAX_ACTIVE_PROMPTS', 20),
    'checks_per_minute' => (int) env('AI_VISIBILITY_CHECKS_PER_MINUTE', 10),
    'max_response_characters' => 30000,
    'providers' => [
        'openai' => ['enabled' => (bool) env('AI_VISIBILITY_OPENAI_ENABLED', true), 'model' => env('AI_VISIBILITY_OPENAI_MODEL')],
        'gemini' => ['enabled' => (bool) env('AI_VISIBILITY_GEMINI_ENABLED', false), 'model' => env('AI_VISIBILITY_GEMINI_MODEL')],
        'perplexity' => ['enabled' => (bool) env('AI_VISIBILITY_PERPLEXITY_ENABLED', false), 'model' => env('AI_VISIBILITY_PERPLEXITY_MODEL', 'sonar'), 'key' => env('PERPLEXITY_API_KEY'), 'url' => 'https://api.perplexity.ai/chat/completions'],
    ],
];
