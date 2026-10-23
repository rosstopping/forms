<?php

namespace App\Services;

use App\Models\ContentPlan;
use App\Models\User;
use App\Models\Website;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ManualContentSchedule
{
    public function __construct(private ContentSchedule $schedule) {}

    public function dueAt(ContentPlan $plan): ?CarbonImmutable
    {
        if ($plan->content_engine !== ContentPlan::ENGINE_MANUAL || ! $plan->manual_reminders_enabled || ! $plan->website->is_active) {
            return null;
        }
        $today = CarbonImmutable::now($plan->timezone);
        $weekdays = $this->schedule->weekdays($plan) ?: [(int) $plan->weekday];

        return in_array($today->dayOfWeek, $weekdays, true) && $plan->manual_skipped_on?->toDateString() !== $today->toDateString()
            ? $today->startOfDay()->setTime($plan->hour, 0) : null;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user): Collection
    {
        abort_unless($user->isAdmin(), 403);

        return Website::query()->accessibleTo($user)->where('is_active', true)
            ->whereHas('contentPlan', fn ($query) => $query->where('content_engine', ContentPlan::ENGINE_MANUAL)->where('manual_reminders_enabled', true))
            ->with(['owner', 'contentPlan', 'contentRequests' => fn ($query) => $query
                ->where('manual_completed_at', '>=', now()->subDays(2))->select(['id', 'website_id', 'manual_completed_at'])])
            ->withCount([
                'contentRequests as waiting_count' => fn ($query) => $query->where('planning_status', 'queued')->whereNull('picked_up_at')->whereNull('manual_completed_at'),
                'contentRequests as planned_count' => fn ($query) => $query->where('planning_status', 'planned')->whereNull('picked_up_at')->whereNull('manual_completed_at'),
                'contentRequests as in_progress_count' => fn ($query) => $query->whereNotNull('manual_started_at')->whereNull('manual_completed_at'),
            ])->orderBy('name')->get()->map(function (Website $website): ?array {
                $plan = $website->contentPlan;
                $dueAt = $this->dueAt($plan);
                if (! $dueAt || $website->in_progress_count > 0 || $website->contentRequests->contains(
                    fn ($request): bool => $request->manual_completed_at->copy()->setTimezone($plan->timezone)->isSameDay($dueAt),
                )) {
                    return null;
                }

                return [
                    'website_id' => $website->id, 'website' => $website->name,
                    'scheduled_at' => $dueAt->toIso8601String(), 'timezone' => $plan->timezone,
                    'waiting_count' => $website->waiting_count, 'planned_count' => $website->planned_count,
                    'queue_url' => route('admin.websites.section', [$website, 'content', 'content_section' => 'queue']),
                    'plan_url' => route('admin.websites.section', [$website, 'content', 'content_section' => 'plan']),
                    'suggestions_url' => route('admin.websites.section', [$website, 'seo', 'seo_section' => 'actions']),
                ];
            })->filter()->values();
    }
}
