<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentContentTask;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\Website;
use App\Services\ContentWorkSelector;
use App\Services\ManualContentRequest;
use App\Services\ManualContentSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManualContentRequestController extends Controller
{
    public function index(Request $request, ManualContentSchedule $schedule): View
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);
        $accessible = Website::query()->accessibleTo($user)->select('id');
        $query = ContentRequest::query()->whereIn('website_id', $accessible)
            ->whereNull('manual_completed_at')
            ->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $query): void {
                    $query->whereNull('picked_up_at')->where('planning_status', 'queued')
                        ->whereHas('website.contentPlan', fn (Builder $plan) => $plan->where('content_engine', ContentPlan::ENGINE_MANUAL));
                })->orWhere(function (Builder $query) use ($user): void {
                    $query->where('manual_taken_by', $user->id)->whereNotNull('manual_started_at');
                });
            });
        $selected = $request->filled('task')
            ? (clone $query)->with('website')->findOrFail($request->integer('task'))
            : null;
        $tasks = $query->select(['id', 'website_id', 'instructions', 'held_at', 'hold_reason', 'manual_started_at', 'bumped_at', 'created_at'])
            ->with('website:id,name')->orderByRaw('CASE WHEN manual_started_at IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN bumped_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('bumped_at')->oldest('created_at')->oldest('id')
            ->paginate(15)->withQueryString();
        if (! $selected && $tasks->isNotEmpty()) {
            $selected = (clone $query)->select('*')->with('website')->findOrFail($tasks->first()->id);
        }

        $pauseReason = null;
        if ($selected && ! $selected->manual_started_at) {
            $plan = $selected->website->contentPlan;
            if ($selected->held_at) {
                $pauseReason = $selected->hold_reason ?: 'Review this task in the website content queue before starting.';
            } elseif ($selected->optimisations()->exists()) {
                $pauseReason = 'Review or remove the existing Pixel changes before starting manual work.';
            } elseif ($plan?->generations()->where('status', ContentGeneration::STATUS_RUNNING)->exists()) {
                $pauseReason = 'Wait for the running content task to finish before starting manual work.';
            } elseif ($plan) {
                $state = app(ContentWorkSelector::class)->queueStates($plan, includeRepository: false, copilot: false)[$selected->id] ?? null;
                if ($state && $state['state'] !== 'ready') {
                    $pauseReason = $state['reason'];
                }
            }
        }

        $dueWebsites = $schedule->forUser($user);
        $agentTask = $selected ? AgentContentTask::query()->where('content_request_id', $selected->id)->first(['status', 'pull_request_url', 'blocked_reason']) : null;

        return view('admin.content-queue', compact('tasks', 'selected', 'pauseReason', 'dueWebsites', 'agentTask'));
    }

    public function skipToday(Request $request, Website $website, ManualContentSchedule $schedule): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() && $website->isManageableBy($request->user()), 403);
        $plan = $website->contentPlan;
        abort_unless($plan && $schedule->dueAt($plan), 422, 'This website has no manual content reminder due today.');
        $plan->update(['manual_skipped_on' => now($plan->timezone)->toDateString()]);

        return redirect()->route('admin.content-queue.index')->with('status', 'Today’s content reminder skipped. Existing requests are unchanged.');
    }

    public function take(Request $request, Website $website, ContentRequest $contentRequest, ManualContentRequest $manual): RedirectResponse
    {
        $this->authorizeRequest($request, $website, $contentRequest);
        $manual->take($contentRequest, $request->user());

        if ($request->input('return_to') === 'my-queue') {
            return redirect()->route('admin.content-queue.index', ['task' => $contentRequest->id])->with('status', 'Task started. Copy the prompt into Codex.');
        }

        return redirect()->route('admin.websites.section', [$website, 'content', 'content_section' => 'queue', 'content_prompt' => $contentRequest->id])
            ->with('status', 'Request reserved for manual work. It will not be picked up by automation.');
    }

    public function release(Request $request, Website $website, ContentRequest $contentRequest, ManualContentRequest $manual): RedirectResponse
    {
        $this->authorizeRequest($request, $website, $contentRequest);
        $this->authorizeAssignee($request, $contentRequest);
        $manual->returnToQueue($contentRequest);
        if ($request->input('return_to') === 'my-queue') {
            return redirect()->route('admin.content-queue.index')->with('status', 'Task returned to the queue.');
        }

        return redirect()->route('admin.websites.section', [$website, 'content', 'content_section' => 'queue'])->with('status', 'Request returned to the content queue.');
    }

    public function complete(Request $request, Website $website, ContentRequest $contentRequest, ManualContentRequest $manual): RedirectResponse
    {
        $this->authorizeRequest($request, $website, $contentRequest);
        $this->authorizeAssignee($request, $contentRequest);
        $manual->complete($contentRequest);
        if ($request->input('return_to') === 'my-queue') {
            return redirect()->route('admin.content-queue.index')->with('status', 'Task marked done. Choose the next task.');
        }

        return redirect()->route('admin.websites.section', [$website, 'content', 'content_section' => 'activity'])->with('status', 'Manual work marked complete. This does not confirm publication or start SEO measurement.');
    }

    private function authorizeAssignee(Request $request, ContentRequest $contentRequest): void
    {
        if ($request->input('return_to') === 'my-queue') {
            abort_unless($contentRequest->manual_taken_by === $request->user()->id, 403);
        }
    }

    private function authorizeRequest(Request $request, Website $website, ContentRequest $contentRequest): void
    {
        abort_unless($request->user()?->isAdmin() && $website->isManageableBy($request->user()), 403);
        abort_unless($contentRequest->website_id === $website->id, 404);
    }
}
