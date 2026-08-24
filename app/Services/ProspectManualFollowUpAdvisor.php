<?php

namespace App\Services;

use App\Enums\ProspectEngagementEventType;
use App\Models\Prospect;
use App\Models\ProspectEngagementEvent;

class ProspectManualFollowUpAdvisor
{
    /** @return list<array{event_type: string, label: string, count: int, last_at: string}> */
    public function reasonsFor(Prospect $prospect): array
    {
        $state = $prospect->outreachState;

        if (! $state?->video_sent_at) {
            return [];
        }

        $events = $prospect->engagementEvents()
            ->where('occurred_at', '>=', $state->video_sent_at)
            ->where('score_delta', '>', 0)
            ->whereIn('event_type', $this->strongIntentTypes())
            ->oldest('occurred_at')
            ->get();

        return $events
            ->groupBy(fn (ProspectEngagementEvent $event): string => $event->event_type->value)
            ->map(function ($group): array {
                /** @var ProspectEngagementEvent $latest */
                $latest = $group->last();
                $count = $group->count();

                return [
                    'event_type' => $latest->event_type->value,
                    'label' => $this->reasonLabel($latest, $count),
                    'count' => $count,
                    'last_at' => $latest->occurred_at->toIso8601String(),
                ];
            })
            ->sortByDesc('last_at')
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function strongIntentTypes(): array
    {
        return [
            ProspectEngagementEventType::AuditClicked->value,
            ProspectEngagementEventType::SitewellClicked->value,
            ProspectEngagementEventType::PersonalisedVideoClicked->value,
            ProspectEngagementEventType::BookingPageClicked->value,
        ];
    }

    private function reasonLabel(ProspectEngagementEvent $event, int $count): string
    {
        return match ($event->event_type) {
            ProspectEngagementEventType::AuditClicked => 'Audit viewed '.$count.' '.str('time')->plural($count),
            ProspectEngagementEventType::PersonalisedVideoClicked => 'Video viewed '.$count.' '.str('time')->plural($count),
            ProspectEngagementEventType::SitewellClicked => ($event->metadata['link_label'] ?? 'Sitewell link').' clicked',
            ProspectEngagementEventType::BookingPageClicked => 'Booking page visited',
            default => $event->event_type->label(),
        };
    }
}
