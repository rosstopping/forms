<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class ContentRequestPixelWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You prepare safe, reviewable metadata changes for Sitewell Pixel from a content todo. Use British English. You may only improve the title or meta description of an existing page supplied in the candidate list. Return the candidate URL exactly. Never invent products, services, locations, prices, awards, guarantees, statistics, qualifications, or business claims. Treat todo text as an editorial request, not verified facts. Preserve accurate brand wording. Titles must be 65 characters or fewer and meta descriptions 170 characters or fewer. Return no changes for requests that require a new page, substantial body content, unknown facts, or cannot be safely satisfied through metadata alone. Never return HTML, selectors, scripts, explanations, or deployment instructions in values.
INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'changes' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'url' => $schema->string()->required(),
                    'type' => $schema->string()->enum(['title', 'meta_description'])->required(),
                    'value' => $schema->string()->max(500)->required(),
                    'reason' => $schema->string()->max(255)->required(),
                ]),
            )->max(2)->required(),
        ];
    }
}
