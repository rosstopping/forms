<?php

namespace App\Services;

use App\Models\AgentContentTask;
use App\Models\ContentGeneration;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteLocalProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentContentWork
{
    public function __construct(private ManualContentSchedule $schedule, private ManualContentRequest $manual, private AgentSeoEvidence $evidence) {}

    /** @param array<string, mixed> $data */
    public function create(Website $website, User $user, array $data): AgentContentTask
    {
        return DB::transaction(function () use ($website, $user, $data): AgentContentTask {
            $website = Website::query()->accessibleTo($user)->whereKey($website->id)->lockForUpdate()->firstOrFail();
            $plan = $website->contentPlan()->lockForUpdate()->first();
            abort_unless($plan, 422, 'Set up a manual content schedule first.');
            $plan->setRelation('website', $website);
            $day = now($plan->timezone)->toDateString();
            abort_unless($data['scheduled_for'] === $day, 409, 'The proposal is for a different local date. Refresh the due-work list.');
            $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = AgentContentTask::where('website_id', $website->id)->whereDate('scheduled_for', $day)->first();
            if ($existing) {
                abort_unless($existing->user_id === $user->id && hash_equals($existing->fingerprint, $fingerprint), 409, 'A task already exists for this website and day. Resume it instead of creating another.');

                return $existing;
            }
            abort_unless($this->schedule->dueAt($plan), 422, 'This website is not due manual content today.');
            abort_if($website->contentRequests()->whereNull('manual_completed_at')->where(function ($query): void {
                $query->where(fn ($query) => $query->where('planning_status', 'queued')->whereNull('picked_up_at'))
                    ->orWhereNotNull('manual_started_at');
            })->exists(), 409, 'The queue is not empty or manual work is already in progress.');
            $start = now($plan->timezone)->startOfDay()->utc();
            $end = now($plan->timezone)->addDay()->startOfDay()->utc();
            abort_if($website->contentRequests()->where('manual_completed_at', '>=', $start)->where('manual_completed_at', '<', $end)->exists(), 409, 'Manual content has already been completed today.');
            abort_if($plan->generations()->whereIn('status', [ContentGeneration::STATUS_PENDING, ContentGeneration::STATUS_RUNNING])->exists(), 409, 'Content generation is already queued or running.');
            $project = WebsiteLocalProject::where('website_id', $website->id)->where('host', $data['host'])->where('path', $data['path'])->lockForUpdate()->first();
            abort_unless($project, 422, 'Confirm and record the matching local Codex project first.');
            $repository = $website->repository?->full_name;
            abort_if($repository && strcasecmp($repository, $data['repository']) !== 0, 422, 'The repository does not match this website.');
            $scope = app(SeoImpactTracker::class)->websiteUrls($website, $data['target_urls']);
            abort_unless(count($scope) === count($data['target_urls']), 422, 'Every target URL must belong to this website.');
            foreach ($website->contentRequests()->where('planning_status', 'planned')->whereNull('picked_up_at')->with('seoImpact')->get(['id', 'instructions', 'dependencies', 'discovery_context']) as $planned) {
                $plannedUrls = [...($planned->seoImpact?->target_urls ?? []), ...($planned->dependencies['urls'] ?? [])];
                $plannedQueries = $planned->seoImpact?->target_queries ?? [];
                $tracker = app(SeoImpactTracker::class);
                $sameUrl = collect($scope)->map($tracker->urlKey(...))->intersect(collect($plannedUrls)->map($tracker->urlKey(...)))->isNotEmpty();
                $sameQuery = collect($data['target_queries'])->map(fn (string $query): string => mb_strtolower(trim($query)))
                    ->intersect(collect($plannedQueries)->map(fn (string $query): string => mb_strtolower(trim($query))))->isNotEmpty();
                abort_if($sameUrl || $sameQuery || Str::squish(mb_strtolower($planned->instructions)) === Str::squish(mb_strtolower($data['instructions'])), 409, 'Planned work already covers this scope. Review that brief instead of creating a duplicate.');
            }
            $evidence = $this->evidence->references($website, $data['evidence']);
            $request = $website->contentRequests()->create([
                'created_by' => $user->id, 'instructions' => $data['instructions'], 'work_type' => $data['work_type'],
                'planning_status' => 'queued', 'dependencies' => ['urls' => $scope, 'files' => $data['target_files']],
            ]);
            $impact = app(SeoImpactTracker::class)->forRequest($request);
            $impact->update(['target_urls' => $scope, 'target_queries' => $data['target_queries'], 'hypothesis' => $data['rationale'],
                'evidence' => ['source' => 'agent_reviewed_saved_evidence', 'references' => $data['evidence'], 'coverage_reviewed' => true, 'repository_reviewed' => true, 'open_prs_reviewed' => true]]);
            $task = AgentContentTask::create([
                'website_id' => $website->id, 'content_request_id' => $request->id, 'user_id' => $user->id,
                'scheduled_for' => $day, 'timezone' => $plan->timezone, 'fingerprint' => $fingerprint,
                'project' => $project->only(['host', 'path', 'codex_project_id']), 'repository' => $repository ?: $data['repository'],
                'evidence' => $evidence, 'rationale' => $data['rationale'],
            ]);
            $task->update(['branch' => 'codex/sitewell-'.$website->id.'-'.$day.'-'.$task->id]);
            $this->manual->take($request, $user);

            return $task->fresh();
        });
    }

    public function authorize(Website $website, AgentContentTask $task, User $user): void
    {
        abort_unless($user->isAdmin() && Website::query()->accessibleTo($user)->whereKey($website->id)->exists()
            && $task->website_id === $website->id && $task->user_id === $user->id, 404);
    }

    /** @param array<string, mixed> $data */
    public function report(AgentContentTask $task, array $data, bool $blocked): AgentContentTask
    {
        return DB::transaction(function () use ($task, $data, $blocked): AgentContentTask {
            Website::whereKey($task->website_id)->lockForUpdate()->firstOrFail();
            $task = AgentContentTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $request = $task->contentRequest()->lockForUpdate()->firstOrFail();
            abort_unless($request->manual_taken_by === $task->user_id && $request->manual_started_at && ! $request->manual_completed_at, 409, 'This manual reservation has been returned, reassigned or completed.');
            if ($blocked) {
                abort_if($task->pull_request_url, 409, 'A draft PR has already been reported. Review it before changing task state.');
                $task->update(['status' => 'blocked', 'blocked_reason' => $data['reason']]);
                $request->update(['held_at' => now(), 'hold_reason' => Str::limit($data['reason'], 500, '')]);
            } else {
                abort_if($task->status === 'blocked' || $request->held_at, 409, 'This task is blocked. Resolve it through the content queue before continuing.');
                abort_unless(str_starts_with($data['pull_request_url'], 'https://github.com/'.$task->repository.'/pull/') && $data['branch'] === $task->branch, 422, 'The draft PR must match the reserved repository and branch.');
                abort_if($task->pull_request_url && $task->pull_request_url !== $data['pull_request_url'], 409, 'A different PR is already recorded for this task.');
                $task->update(['status' => 'draft_pr_reported', 'pull_request_url' => $data['pull_request_url'],
                    'result' => [...$data, 'verification_source' => 'agent_github_cli', 'reported_at' => now()->toIso8601String()]]);
            }

            return $task->fresh();
        });
    }

    /** @return array<string, mixed> */
    public function payload(AgentContentTask $task): array
    {
        $task->loadMissing('contentRequest');

        return ['id' => $task->id, 'website_id' => $task->website_id, 'request_id' => $task->content_request_id,
            'scheduled_for' => $task->scheduled_for->toDateString(), 'status' => $task->status, 'project' => $task->project,
            'evidence' => $task->evidence, 'repository' => $task->repository, 'branch' => $task->branch, 'rationale' => $task->rationale,
            'prompt' => $task->contentRequest->manual_prompt, 'pull_request_url' => $task->pull_request_url,
            'result' => $task->result, 'blocked_reason' => $task->blocked_reason,
            'reservation_active' => $task->contentRequest->manual_taken_by === $task->user_id && $task->contentRequest->manual_started_at !== null && $task->contentRequest->manual_completed_at === null,
            'url' => route('admin.content-queue.index', ['task' => $task->content_request_id]),
            'execution' => ['draft_pr_required' => true, 'merge_allowed' => false, 'publish_allowed' => false],
            'note' => 'PR state is reported from the agent’s GitHub CLI check. The API does not independently verify GitHub or confirm publication. Keep the reservation until human review; do not automatically mark work complete.'];
    }
}
