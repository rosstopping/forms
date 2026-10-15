<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendWebsiteGrowthPlanRequest;
use App\Jobs\GenerateWebsiteGrowthPlan;
use App\Models\Website;
use App\Models\WebsiteGrowthPlan;
use App\Services\WebsiteGrowthPlanBuilder;
use App\Services\WebsiteGrowthPlanPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WebsiteGrowthPlanController extends Controller
{
    public function __construct(private WebsiteGrowthPlanBuilder $builder, private WebsiteGrowthPlanPublisher $publisher) {}

    private function authorizeWebsite(Request $request, Website $website, ?WebsiteGrowthPlan $growthPlan = null): void
    {
        abort_unless($request->user()?->isAdmin() && $website->isManageableBy($request->user()), 403);
        abort_if($growthPlan && $growthPlan->website_id !== $website->id, 404);
        if (! $request->isMethodSafe()) {
            abort_unless($website->hasActiveService() || $this->publisher->recipients($website) !== [], 422, 'Activate the client service or attach a signed-up customer before generating their plan.');
        }
    }

    public function edit(Request $request, Website $website, ?WebsiteGrowthPlan $growthPlan = null): Response
    {
        $this->authorizeWebsite($request, $website, $growthPlan);
        $history = WebsiteGrowthPlan::query()->whereBelongsTo($website)->latest('id')->get(['id', 'website_id', 'status', 'published_at', 'created_at']);
        $growthPlan ??= $history->isNotEmpty() ? WebsiteGrowthPlan::query()->find($history->first()->id) : null;
        $response = response()->view('admin.websites.growth-plan', [
            'website' => $website, 'plan' => $growthPlan, 'history' => $history,
            'recipients' => $this->publisher->recipients($website),
            'canPreparePlan' => $website->hasActiveService() || $this->publisher->recipients($website) !== [],
        ])->header('Cache-Control', 'private, no-store');
        if ($growthPlan?->isGenerating()) {
            $response->header('Refresh', '5');
        }

        return $response;
    }

    public function store(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $plan = DB::transaction(function () use ($website, $request): WebsiteGrowthPlan {
            Website::query()->whereKey($website->id)->lockForUpdate()->firstOrFail();
            $pending = WebsiteGrowthPlan::query()->whereBelongsTo($website)->whereIn('status', [WebsiteGrowthPlan::STATUS_QUEUED, WebsiteGrowthPlan::STATUS_RUNNING])->latest('id')->first();
            if ($pending) {
                return $pending;
            }
            $context = $this->builder->snapshot($website);
            $plan = WebsiteGrowthPlan::query()->create([
                'website_id' => $website->id, 'created_by' => $request->user()->id, 'status' => WebsiteGrowthPlan::STATUS_QUEUED,
                'brief' => $context, 'report' => $context['baseline_report'],
            ]);
            GenerateWebsiteGrowthPlan::dispatch($plan)->afterCommit();

            return $plan;
        });

        return redirect()->route('admin.website-growth-plans.edit', [$website, $plan])->with('status', 'Your plan is being generated from the saved website context. This page will update when it is ready.');
    }

    public function preview(Request $request, Website $website, WebsiteGrowthPlan $growthPlan): View
    {
        $this->authorizeWebsite($request, $website, $growthPlan);
        abort_unless($growthPlan->isReady(), 404);

        return view('website-growth-plans.report', ['plan' => $growthPlan, 'preview' => true]);
    }

    public function send(SendWebsiteGrowthPlanRequest $request, Website $website, WebsiteGrowthPlan $growthPlan): RedirectResponse
    {
        $this->authorizeWebsite($request, $website, $growthPlan);
        $this->publisher->send($growthPlan, $request->validated('recipient'));

        return back()->with('status', 'The plan email is queued.');
    }
}
