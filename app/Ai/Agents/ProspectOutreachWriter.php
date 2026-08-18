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
        return 'Write a short outreach email using the appropriate exact wording. When a showcase video URL is supplied, use: "Hi there, I came across [company name] on Google and thought Sitewell might be useful for you. I ran your website through it and recorded a quick video showing what I found. No sales pitch — just thought it might be worth a look. Cheers, Ross". When no showcase video URL is supplied, use: "Hi [name], I came across [company name] while looking at local search results. [When verified ranking evidence is supplied: I found your website for ‘[search term]’ on page [page] of Google (position [position]).] I spotted a few opportunities that could help the website bring in more local enquiries. I’ve put together a short website audit showing some of the issues I found. I can also record a quick video explaining what I’d prioritise. Would you like me to send the video over? Cheers, Ross". Omit the ranking sentence when no verified ranking evidence is supplied. Replace placeholders with the supplied details, falling back to "there" when no contact name is available. Preserve the paragraph breaks and do not add prices, unverified website audit findings, fixes, claims, or other sales copy. Return a plain subject and plain-text body ready for human approval.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()->max(120)->required(),
            'body' => $schema->string()->max(3000)->required(),
        ];
    }
}
