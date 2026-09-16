<?php

namespace App\Services;

use App\Ai\Agents\AiVisibilityObserver;
use App\Contracts\AiVisibilityProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PerplexityVisibilityProvider implements AiVisibilityProvider
{
    public function key(): string
    {
        return 'perplexity';
    }

    public function available(): bool
    {
        return config('ai_visibility.providers.perplexity.enabled') && filled(config('ai_visibility.providers.perplexity.key'));
    }

    public function model(): string
    {
        return (string) config('ai_visibility.providers.perplexity.model');
    }

    public function check(string $prompt, string $model): array
    {
        if (! $this->available()) {
            throw new RuntimeException('Perplexity is not configured.');
        }
        $response = Http::withToken(config('ai_visibility.providers.perplexity.key'))->acceptJson()->connectTimeout(5)->timeout(90)
            ->post(config('ai_visibility.providers.perplexity.url'), ['model' => $model, 'max_tokens' => 2500, 'messages' => [
                ['role' => 'system', 'content' => (new AiVisibilityObserver)->instructions()],
                ['role' => 'user', 'content' => $prompt],
            ]])->throw()->json();
        if (data_get($response, 'choices.0.finish_reason') !== 'stop') {
            throw new RuntimeException('Perplexity did not return a complete response.');
        }

        return ['text' => (string) data_get($response, 'choices.0.message.content', ''), 'citations' => collect($response['citations'] ?? [])->map(fn (string $url): array => ['url' => $url])->all(), 'usage' => $response['usage'] ?? [], 'metadata' => ['provider' => $this->key(), 'model' => $model, 'returned_model' => $response['model'] ?? $model, 'instructions' => (new AiVisibilityObserver)->instructions(), 'response_id' => $response['id'] ?? null, 'web_search_enabled' => true]];
    }
}
