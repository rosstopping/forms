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
        return 'Write a short outreach email using the appropriate exact wording. When a showcase video URL is supplied, use: "Hi there, I came across [company name] on Google and thought Sitewell might be useful for you. I ran your website through it and recorded a quick video showing what I found. No sales pitch — just thought it might be worth a look. Cheers, Ross". When no showcase video URL is supplied, use: "Hi [name], I’ll be upfront — this is a cold email. I’m a web developer and I’m trying to pick up a few new clients locally. I came across [company name] and had a look at your website. You’re already appearing in Google, but you’re quite a way down for some searches that could probably be bringing you work. I manage the whole lot for £149/month — website, hosting, SEO and ongoing improvements. If you’d like, I’ll send you a quick video showing what I found on yours and what I’d change. No hard sell afterwards. Cheers, Ross". Replace placeholders with the supplied details, falling back to "there" when no contact name is available. Preserve the paragraph breaks and do not add website audit findings, fixes, claims, or other sales copy. Return a plain subject and plain-text body ready for human approval.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()->max(120)->required(),
            'body' => $schema->string()->max(3000)->required(),
        ];
    }
}
