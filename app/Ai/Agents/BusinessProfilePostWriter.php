<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class BusinessProfilePostWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'Write a concise, accurate Google Business Profile post in British English. Never invent offers, dates, prices, awards, or claims. Follow the supplied brand guidance. Return text ready for human approval, never claim it has been published.';
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->max(1500)->required(),
            'call_to_action_type' => $schema->string()->enum(['LEARN_MORE', 'BOOK', 'ORDER', 'SIGN_UP', 'CALL', 'NONE'])->required(),
            'call_to_action_url' => $schema->string()->nullable()->required(),
        ];
    }
}
