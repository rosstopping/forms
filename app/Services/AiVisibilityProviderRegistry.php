<?php

namespace App\Services;

use App\Contracts\AiVisibilityProvider;
use InvalidArgumentException;

class AiVisibilityProviderRegistry
{
    /** @var array<string, AiVisibilityProvider> */
    private array $providers;

    public function __construct(OpenAiVisibilityProvider $openai, GeminiVisibilityProvider $gemini, PerplexityVisibilityProvider $perplexity)
    {
        $this->providers = ['openai' => $openai, 'gemini' => $gemini, 'perplexity' => $perplexity];
    }

    public function get(string $key): AiVisibilityProvider
    {
        return $this->providers[$key] ?? throw new InvalidArgumentException('Unknown AI visibility provider.');
    }

    /** @return array<string, bool> */
    public function availability(): array
    {
        return array_map(fn (AiVisibilityProvider $provider): bool => $provider->available(), $this->providers);
    }
}
