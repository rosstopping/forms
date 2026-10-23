<?php

namespace App\Services;

use App\Models\AgentContentTask;
use App\Models\CompetitorOpportunity;
use App\Models\ContentGeneration;
use App\Models\ContentOpportunity;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Str;

class AgentWorkOverview
{
    public function __construct(private ManualContentSchedule $schedule, private DashboardWorkActivity $activity, private ContentWorkSelector $work) {}

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        abort_unless($user->isAdmin(), 403);
        $websiteIds = Website::query()->accessibleTo($user)->where('is_active', true)->pluck('id')->all();
        $manualIds = ContentPlan::query()->whereIn('website_id', $websiteIds)->where('content_engine', ContentPlan::ENGINE_MANUAL)->pluck('website_id');
        $query = ContentRequest::query()->whereIn('website_id', $websiteIds)->whereNull('manual_completed_at')
            ->where(function ($query) use ($user, $manualIds): void {
                $query->where(fn ($query) => $query->whereIn('website_id', $manualIds)->where('planning_status', 'queued')->whereNull('picked_up_at'))
                    ->orWhere(fn ($query) => $query->where('manual_taken_by', $user->id)->whereNotNull('manual_started_at'));
            });
        $taskCount = (clone $query)->count();
        $tasks = $query->with(['website.contentPlan', 'website.contentPlan.generations' => fn ($query) => $query
            ->whereIn('status', [ContentGeneration::STATUS_PENDING, ContentGeneration::STATUS_RUNNING])->select(['id', 'content_plan_id', 'status'])])
            ->orderByRaw('CASE WHEN manual_started_at IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN bumped_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('bumped_at')->oldest('created_at')->oldest('id')
            ->limit(100)->get(['id', 'website_id', 'instructions', 'manual_started_at', 'held_at', 'hold_reason']);
        $states = [];
        foreach ($tasks->whereNull('manual_started_at')->groupBy('website_id') as $websiteTasks) {
            $website = $websiteTasks->first()->website;
            $states[$website->id] = $this->work->queueStates($website->contentPlan, includeRepository: false, copilot: false);
        }
        $tasks = $tasks->map(function (ContentRequest $task) use ($states): array {
            $state = $states[$task->website_id][$task->id] ?? ['state' => 'ready', 'reason' => 'Eligible for manual preparation.'];
            if ($task->manual_started_at) {
                $state = ['state' => 'in_progress', 'reason' => 'Reserved for you.'];
            } elseif ($task->website->contentPlan->generations->isNotEmpty()) {
                $state = ['state' => 'blocked', 'reason' => 'Wait for the queued or running content generation to finish.'];
            }

            return ['id' => $task->id, 'website_id' => $task->website_id, 'website' => $task->website->name,
                'summary' => Str::limit($task->instructions, 500), 'state' => $state['state'], 'reason' => $state['reason'],
                'url' => route('admin.content-queue.index', ['task' => $task->id])];
        })->values();
        $suggestions = collect();
        $suggestionCount = 0;
        foreach ([ContentOpportunity::class, CompetitorOpportunity::class] as $model) {
            $opportunities = $model::query()->whereIn('website_id', $websiteIds)->where('status', 'open')->whereNull('content_request_id');
            $suggestionCount += (clone $opportunities)->count();
            $suggestions = $suggestions->concat($opportunities->with('website:id,name')->orderByDesc('priority_score')->latest('id')->limit(25)
                ->get(['id', 'website_id', 'title', 'priority_score'])->map(fn ($opportunity): array => [
                    'website' => $opportunity->website->name, 'title' => $opportunity->title, 'priority' => $opportunity->priority_score,
                    'requires_approval' => true, 'url' => route('admin.websites.section', [$opportunity->website, 'seo', 'seo_section' => 'actions']),
                ]));
        }
        $planned = ContentRequest::query()->whereIn('website_id', $websiteIds)->where('planning_status', 'planned')->whereNull('picked_up_at')->whereNull('manual_completed_at');
        $plannedCount = (clone $planned)->count();
        $planned = $planned->with('website:id,name')->orderBy('planned_for')->oldest('id')->limit(25)
            ->get(['id', 'website_id', 'instructions', 'planned_for'])->map(fn ($request): array => [
                'id' => $request->id, 'website' => $request->website->name, 'summary' => Str::limit($request->instructions, 500),
                'planned_for' => $request->planned_for?->toDateString(), 'requires_approval' => true,
                'url' => route('admin.websites.section', [$request->website, 'content', 'content_section' => 'plan']),
            ]);
        $due = $this->schedule->forUser($user);
        $attention = $this->activity->forWebsites($websiteIds)['attention']->map(fn (array $item): array => [
            'website' => $item['website'], 'type' => $item['type'], 'status' => $item['status'],
            'reason' => Str::limit($item['reason'], 500), 'url' => $item['url'],
        ]);

        return [
            'generated_at' => now()->toIso8601String(), 'read_only' => true,
            'links' => ['websites' => route('agent.websites.index')],
            'agent_tasks' => AgentContentTask::query()->whereIn('website_id', $websiteIds)->where('user_id', $user->id)
                ->whereHas('contentRequest', fn ($query) => $query->whereNull('manual_completed_at')->where('manual_taken_by', $user->id)->whereNotNull('manual_started_at'))
                ->latest('id')->limit(25)->get(['id', 'website_id', 'content_request_id', 'status', 'scheduled_for', 'pull_request_url', 'blocked_reason']),
            'summary' => ['due_today' => $due->count(), 'empty_due_today' => $due->where('waiting_count', 0)->count(),
                'approved_tasks' => $taskCount, 'ready_tasks' => $tasks->where('state', 'ready')->count(),
                'suggestions_awaiting_approval' => $suggestionCount, 'planned_awaiting_approval' => $plannedCount, 'needs_attention' => $attention->count()],
            'due_today' => $due->take(100)->values(), 'tasks' => $tasks,
            'suggestions' => $suggestions->sortByDesc('priority')->take(25)->values(), 'planned' => $planned, 'attention' => $attention,
            'truncated' => ['due_today' => $due->count() > 100, 'tasks' => $taskCount > 100, 'suggestions' => $suggestionCount > 25, 'planned' => $plannedCount > 25],
        ];
    }
}
