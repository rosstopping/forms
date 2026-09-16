<?php

namespace App\Services;

class GeminiVisibilityProvider extends OpenAiVisibilityProvider
{
    public function key(): string
    {
        return 'gemini';
    }
}
