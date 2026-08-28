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
        return 'Write the first cold outreach email as a short, genuine note from Ross, a British web developer who found the business while searching for its service. The goal is only to get permission to send over what he noticed, never to sell Sitewell. Keep the body conversational and approximately 60–100 words. Naturally mention the business, service, location, and verified Google visibility. Translate positions into natural language: 1–3 already among the first results; 4–10 already showing pretty well; 11–20 showing but below the top results; 21–40 quite a way down; 41+ a long way down. Mention that Ross looked at the website and noticed a few possible explanations. Use one specific observation only when directly supported by supplied evidence. End with a low-pressure invitation to send the findings. Use a very short lower-case subject derived from the service intent, removing the location where possible (for example "indoor golf leeds" becomes "indoor golf"). Vary phrasing naturally without becoming salesy. Do not include links, audits, videos, Sitewell, pricing, calendar links, sales claims, urgency, lead-generation claims, or marketing jargon. Never invent personalisation. Avoid SEO, Google ranking, website audit, website review, opportunity, more enquiries, quick question, quick video, marketing, reach out, touch base, circle back, unlock, supercharge, and dominate Google. Return a plain subject and plain-text body ready for human approval.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()->max(120)->required(),
            'body' => $schema->string()->max(3000)->required(),
        ];
    }
}
