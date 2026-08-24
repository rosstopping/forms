<?php

namespace App\Services;

use App\Enums\ProspectEngagementEventType;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\ProspectEngagementEvent;
use App\Models\ProspectOutreachDelivery;
use App\Models\ProspectOutreachState;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProspectOutreachDashboard
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $accessibleProspects = Prospect::query()->accessibleTo($user)->select('id');
        $today = now()->startOfDay();
        $recentRepliesQuery = Prospect::query()
            ->accessibleTo($user)
            ->whereHas('outreachState', fn (Builder $query) => $query->where('lifecycle_state', ProspectLifecycleState::Replied));

        return [
            'priorityCounts' => [
                'warm' => (clone $accessibleProspects)->where('lead_temperature', 'warm')->count(),
                'replied' => (clone $recentRepliesQuery)->where('replied_at', '>=', $today)->count(),
                'booking' => ProspectEngagementEvent::query()->whereIn('prospect_id', clone $accessibleProspects)->where('event_type', ProspectEngagementEventType::BookingPageClicked)->where('occurred_at', '>=', $today)->distinct('prospect_id')->count('prospect_id'),
                'cold_followed_up' => ProspectOutreachDelivery::query()->whereIn('prospect_id', clone $accessibleProspects)->whereIn('message_type', [ProspectOutreachMessageType::ColdFollowUp, ProspectOutreachMessageType::FinalFollowUp])->where('sent_at', '>=', $today)->where('status', 'sent')->count(),
                'nurtured' => ProspectActivity::query()->whereIn('prospect_id', clone $accessibleProspects)->where('type', 'outreach_exhausted')->where('created_at', '>=', $today)->count(),
            ],
            'warmProspects' => Prospect::query()
                ->accessibleTo($user)
                ->where('lead_temperature', 'warm')
                ->with(['outreachState', 'engagementEvents' => fn ($query) => $query->where('score_delta', '>', 0)->latest('occurred_at')->limit(3)])
                ->orderByDesc(ProspectOutreachState::query()->select('last_engagement_at')->whereColumn((new ProspectOutreachState)->qualifyColumn('prospect_id'), (new Prospect)->qualifyColumn('id'))->limit(1))
                ->limit(12)
                ->get(),
            'recentReplies' => $recentRepliesQuery->with('outreachState')->latest('replied_at')->limit(12)->get(),
        ];
    }
}
