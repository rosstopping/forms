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
        return 'Write a short outreach email using this exact wording, replacing [company name] with the supplied business name: "Hi there, I came across [company name] on Google and thought Sitewell might be useful for you. I ran your website through it and recorded a quick video showing what I found. No sales pitch — just thought it might be worth a look. Cheers, Ross". Preserve the paragraph breaks and do not add website audit findings, fixes, claims, or other sales copy. Return a plain subject and plain-text body ready for human approval.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()->max(120)->required(),
            'body' => $schema->string()->max(3000)->required(),
        ];
    }
}
