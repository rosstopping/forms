<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;

#[MaxTokens(2500)]
class AiVisibilityObserver implements Agent, HasProviderOptions, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'Answer the user’s business recommendation question using public evidence and web search where useful. Do not invent businesses or unsupported endorsements. If recommending businesses, start each item with its business name in bold. Use numbered recommendations only when presenting an ordered list. Keep the answer under 1000 words. Cite sources where supported. If there is insufficient evidence, say so.';
    }

    public function tools(): iterable
    {
        return [new WebSearch];
    }

    /** @return array<string, mixed> */
    public function providerOptions(Lab|string $provider): array
    {
        $provider = $provider instanceof Lab ? $provider->value : $provider;

        return $provider === 'openai' ? ['store' => false, 'max_tool_calls' => 2] : [];
    }
}
