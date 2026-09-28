<?php

namespace App\Services;

use App\Enums\ProspectOutreachMessageType;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;

class ProspectOutreachContent
{
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
        $key = $type === ProspectOutreachMessageType::ColdFollowUp && $this->initialIncludedVideo($prospect, $preview)
            ? 'cold_follow_up_with_video'
            : $type->value;
        $template = config('outreach.templates.'.$key, []);

        return [
            'subject' => filled($template['subject'] ?? null) ? $this->render($template['subject'], $prospect) : (string) $prospect->outreach_subject,
            'body' => filled($template['body'] ?? null) ? $this->render($template['body'], $prospect) : (string) $prospect->outreach_body,
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
