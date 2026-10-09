<?php

namespace App\Services;

use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContentBudget
{
    /** @return array{articles: int, optimisations: int, copilot: int} */
    public function usage(ContentPlan $plan, ?int $except = null): array
    {
        $start = now($plan->timezone)->startOfMonth()->utc();
        $end = now($plan->timezone)->startOfMonth()->addMonth()->utc();
        $rows = $plan->generations()->when($except, fn ($query) => $query->whereKeyNot($except))
            ->where(fn ($query) => $query->where(fn ($reserved) => $reserved->where('budget_reserved_at', '>=', $start)->where('budget_reserved_at', '<', $end))
                ->orWhere(fn ($legacy) => $legacy->whereNull('budget_reserved_at')->where('started_at', '>=', $start)->where('started_at', '<', $end)))
            ->where('status', '!=', ContentGeneration::STATUS_SKIPPED)
            ->where(fn ($query) => $query->whereNotNull('work_units')->orWhereNotNull('copilot_task_id'))
            ->with('contentRequests:id,content_generation_id,work_type,budget_reserved_at')->get();
        $usage = ['articles' => 0, 'optimisations' => 0, 'copilot' => $rows->count()];
        foreach ($rows as $row) {
            $units = $row->work_units ?? $this->units($row->contentRequests, $row->seo_target_keyword_id !== null);
            $usage['articles'] += $units['articles'];
            $usage['optimisations'] += $units['optimisations'];
        }

        $reservations = ContentRequest::where('website_id', $plan->website_id)
            ->where('budget_reserved_at', '>=', $start)->where('budget_reserved_at', '<', $end)->get(['budget_work_type']);
        $usage['articles'] += $reservations->where('budget_work_type', 'new_article')->count();
        $usage['optimisations'] += $reservations->where('budget_work_type', 'optimisation')->count();

        return $usage;
    }

    public function reserveRequest(ContentRequest $request): ?string
    {
        return DB::transaction(function () use ($request): ?string {
            Website::whereKey($request->website_id)->lockForUpdate()->firstOrFail();
            $plan = ContentPlan::where('website_id', $request->website_id)->lockForUpdate()->first();
            $request->refresh();
            if (! $plan) {
                return null;
            }
            if ($plan->generations()->where('status', ContentGeneration::STATUS_RUNNING)->exists()) {
                return 'Wait for the running Copilot content task to finish.';
            }
            if ($request->picked_up_at || $request->held_at || $request->planning_status !== 'queued') {
                return 'This request is held, planned or already picked up.';
            }
            if ($reason = app(ContentStrategy::class)->requestPauseReason($plan, $request)) {
                return $reason;
            }
            if ($reason = $this->pauseReason($plan, collect([$request]), copilot: false)) {
                return $reason;
            }
            if (! $request->budget_reserved_at) {
                $request->update(['budget_reserved_at' => now(), 'budget_work_type' => $request->work_type]);
            }

            return null;
        });
    }

    /** @return array{articles: int, optimisations: int} */
    public function units(Collection $requests, bool $target = false): array
    {
        return ['articles' => $requests->whereNull('budget_reserved_at')->where('work_type', 'new_article')->count(),
            'optimisations' => $requests->whereNull('budget_reserved_at')->where('work_type', 'optimisation')->count() + (int) $target];
    }

    public function pauseReason(ContentPlan $plan, Collection $requests, bool $target = false, ?int $except = null, bool $copilot = true): ?string
    {
        if ($plan->monthly_article_limit === null && $plan->monthly_optimisation_limit === null && $plan->monthly_copilot_limit === null) {
            return null;
        }
        if (($plan->monthly_article_limit !== null || $plan->monthly_optimisation_limit !== null)
            && $requests->contains(fn ($request): bool => ($request->work_type ?? 'unspecified') === 'unspecified')) {
            return 'Classify work before preparation so monthly content limits can be enforced.';
        }
        if (($plan->monthly_article_limit !== null || $plan->monthly_optimisation_limit !== null) && $requests->isEmpty() && ! $target) {
            return 'Add classified content work before using monthly content limits.';
        }
        $usage = $this->usage($plan, $except);
        $units = [...$this->units($requests, $target), 'copilot' => (int) $copilot];
        foreach (['articles' => 'monthly_article_limit', 'optimisations' => 'monthly_optimisation_limit', 'copilot' => 'monthly_copilot_limit'] as $type => $field) {
            if ($type === 'copilot' && ! $copilot) {
                continue;
            }
            if ($plan->$field !== null && $usage[$type] + $units[$type] > $plan->$field) {
                return 'The monthly '.$type.' maximum has been reached. Work waits until the next month or a limit change.';
            }
        }

        return null;
    }
}
