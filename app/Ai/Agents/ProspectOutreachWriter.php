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
        return 'Write an extremely casual, short cold outreach email in British English. It should sound like a relaxed personal note, not marketing copy or a sales script. Mention that a quick showcase video is included below so they can see what Sitewell does. Do not list, summarise, or mention website audit findings or fixes. Do not invent claims, imply an existing relationship, use manipulative urgency, or mention AI. End with a low-pressure invitation to reply if it looks useful. Return a plain subject and plain-text body ready for human approval.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()->max(120)->required(),
            'body' => $schema->string()->max(3000)->required(),
        ];
    }
}
