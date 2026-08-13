<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class PixelOptimisationWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You create concise SEO amendments for human approval and Sitewell Pixel deployment. Use British English. Return only changes directly justified by the supplied crawl evidence. Never invent locations, services, prices, awards, guarantees, business facts, or keywords. Preserve accurate brand wording. Supported types are title and meta_description. Keep titles at 65 characters or fewer and meta descriptions at 170 characters or fewer. Do not return a change when the current value is already suitable or there is not enough factual context. Never include scripts, HTML, explanations, or deployment instructions in values.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'changes' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'type' => $schema->string()->enum(['title', 'meta_description'])->required(),
                    'value' => $schema->string()->max(500)->required(),
                    'reason' => $schema->string()->max(255)->required(),
                ]),
            )->max(2)->required(),
        ];
    }
}
