<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class ProspectOutreachWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'Write a concise, respectful cold outreach email in British English using only the supplied verified website findings. Do not invent claims, imply an existing relationship, use manipulative urgency, or mention AI. Focus on at most two useful issues. Ask a simple permission-based question. Return a plain subject and plain-text body ready for human approval.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()->max(120)->required(),
            'body' => $schema->string()->max(3000)->required(),
        ];
    }
}
