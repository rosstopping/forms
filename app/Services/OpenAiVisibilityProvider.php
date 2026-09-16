<?php

namespace App\Services;

use App\Ai\Agents\AiVisibilityObserver;
use App\Contracts\AiVisibilityProvider;
use Laravel\Ai\Ai;
use Laravel\Ai\Responses\Data\UrlCitation;
use RuntimeException;

class OpenAiVisibilityProvider implements AiVisibilityProvider
{
    public function __construct(private AiVisibilityObserver $observer) {}

    public function key(): string
    {
        return 'openai';
    }

    public function available(): bool
    {
        return config('ai_visibility.providers.'.$this->key().'.enabled') && filled(config('ai.providers.'.$this->key().'.key'));
    }

    public function model(): string
    {
        return config('ai_visibility.providers.'.$this->key().'.model') ?: Ai::textProvider($this->key())->defaultTextModel();
    }

    public function check(string $prompt, string $model): array
    {
        if (! $this->available()) {
            throw new RuntimeException('This AI visibility provider is not configured.');
        }
        $response = $this->observer->prompt($prompt, provider: $this->key(), model: $model, timeout: 90);
        $status = $response->raw?->json('status');
        if (in_array($status, ['failed', 'incomplete', 'cancelled'], true) || $response->hasPendingApprovals()) {
            throw new RuntimeException('The provider did not return a complete response.');
        }
        $finish = $response->raw?->json('candidates.0.finishReason');
        if ($finish !== null && $finish !== 'STOP') {
            throw new RuntimeException('The provider response was interrupted.');
        }

        return ['text' => $response->text, 'citations' => $response->meta->citations->filter(fn ($citation) => $citation instanceof UrlCitation)->map(fn (UrlCitation $citation) => $citation->toArray())->values()->all(), 'usage' => $response->usage->toArray(), 'metadata' => ['provider' => $this->key(), 'model' => $model, 'returned_model' => $response->meta->model ?: $model, 'instructions' => $this->observer->instructions(), 'response_id' => $response->raw?->json('id'), 'web_search_enabled' => true]];
    }
}
