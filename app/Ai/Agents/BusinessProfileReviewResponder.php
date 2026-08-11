<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class BusinessProfileReviewResponder implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'Draft a warm, concise response to a customer review in British English. Do not admit liability, disclose private information, offer compensation, argue, or invent facts. For criticism, acknowledge the experience and invite an offline conversation. The reply always requires human approval.';
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reply' => $schema->string()->max(1200)->required(),
        ];
    }
}
