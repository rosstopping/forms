<?php

namespace App\Services;

use App\Enums\ProspectOutreachMessageType;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;

class ProspectOutreachContent
{
    /** @return array<string, array{label: string, subject: string, body: string}> */
    public function draftTemplates(Prospect $prospect): array
    {
        $templates = [
            'saved' => ['label' => 'Saved draft', 'subject' => (string) $prospect->outreach_subject, 'body' => (string) $prospect->outreach_body],
        ];
        $initial = app(InitialProspectOutreachGenerator::class)->generate($prospect);
        if ($initial !== null) {
            $templates['initial'] = ['label' => 'Standard initial outreach', ...$initial];
        }
        if ($prospect->isAgencyPartner()) {
            $templates['partner_follow_up'] = ['label' => 'Partner follow-up', ...$this->partnerTemplate($prospect, 'follow_up')];

            return $templates;
        }
        foreach (config('outreach.templates', []) as $key => $template) {
            if (blank($template['body'] ?? null)) {
                continue;
            }
            $templates[$key] = [
                'label' => str($key)->replace('_', ' ')->ucfirst()->toString(),
                'subject' => filled($template['subject'] ?? null) ? $this->render($template['subject'], $prospect) : (string) $prospect->outreach_subject,
                'body' => $this->render($template['body'], $prospect),
            ];
        }

        return $templates;
    }

    public function initialDelivery(Prospect $prospect): ?ProspectOutreachDelivery
    {
        $prospect->loadMissing('outreachDeliveries.links');

        return $prospect->outreachDeliveries
            ->where('message_type', ProspectOutreachMessageType::Initial)
            ->whereNotNull('sent_at')->sortBy('sent_at')->first();
    }

    public function initialIncludedVideo(Prospect $prospect, bool $preview = false): bool
    {
        $initial = $this->initialDelivery($prospect);

        if ($initial) {
            return $initial->links->contains('kind', 'showcase_video');
        }

        return $preview && $prospect->sent_at === null
            && $prospect->outreachState?->initial_email_sent_at === null
            && filled($prospect->showcase_video_url);
    }

    /** @return array{subject: string, body: string} */
    public function followUp(Prospect $prospect, ProspectOutreachMessageType $type, bool $preview = false): array
    {
        if ($prospect->isAgencyPartner()) {
            return data_get($prospect->prospecting_context, 'partner_follow_up') ?? $this->partnerTemplate($prospect, 'follow_up');
        }
        $key = $type === ProspectOutreachMessageType::ColdFollowUp && $this->initialIncludedVideo($prospect, $preview)
            ? 'cold_follow_up_with_video'
            : $type->value;
        $template = config('outreach.templates.'.$key, []);

        return [
            'subject' => filled($template['subject'] ?? null) ? $this->render($template['subject'], $prospect) : (string) $prospect->outreach_subject,
            'body' => filled($template['body'] ?? null) ? $this->render($template['body'], $prospect) : (string) $prospect->outreach_body,
        ];
    }

    /** @return array{subject: string, body: string} */
    public function partnerTemplate(Prospect $prospect, string $step): array
    {
        $template = config('outreach.partner_templates.'.$prospect->prospect_type.'.'.$step);
        $firstName = str((string) $prospect->contact_name)->squish()->before(' ')->toString();

        return [
            'subject' => $template['subject'],
            'body' => strtr($template['body'], ['{first_name}' => filled($firstName) ? $firstName : 'there', '{agency_url}' => config('outreach.agency_url')]),
        ];
    }

    private function render(string $template, Prospect $prospect): string
    {
        return strtr($template, [
            '{contact_name}' => filled($prospect->contact_name) ? $prospect->contact_name : 'there',
            '{company_name}' => $prospect->business_name,
        ]);
    }
}
