<?php

namespace App\Services;

use App\Models\CompetitorOpportunity;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\SeoImpact;
use App\Models\SeoTargetKeyword;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ContentWorkSelector
{
    public function __construct(private SeoTargetKeywordSelector $targets) {}

    /** @return array{requests: Collection, target: ?SeoTargetKeyword, snapshot: array} */
    public function select(ContentGeneration $generation, bool $repositoryPreflight = false): array
    {
        $website = $generation->plan->website;
        $active = $this->targets->active($website);
        $snapshot = $this->targets->snapshot($active);
        $history = $this->history($generation);
        $protectedKeys = app(SeoImpactTracker::class)->protectedKeys($website);
        $openKeys = $history->filter(fn (ContentGeneration $previous): bool => $previous->status === ContentGeneration::STATUS_PULL_REQUEST_OPEN
            && $previous->pull_request_state !== 'closed')->flatMap(fn (ContentGeneration $previous): array => $this->generationKeys($previous))->unique();
        $recentKeys = $history->filter(fn (ContentGeneration $previous): bool => ($previous->merged_at ?? ($previous->copilot_task_id ? $previous->started_at : null))?->greaterThan(now()->subDays(14)) === true)
            ->flatMap(fn (ContentGeneration $previous): array => $this->generationKeys($previous))->unique();
        $manualRequests = $website->contentRequests()->whereNotNull('manual_started_at')
            ->where(fn ($query) => $query->whereNull('manual_completed_at')->orWhere('manual_completed_at', '>', now()->subDays(14)))
            ->with('seoImpact')->get();
        $openKeys = $openKeys->merge($manualRequests->whereNull('manual_completed_at')
            ->flatMap(fn (ContentRequest $request): array => $this->requestKeys($request)))->unique();
        $recentKeys = $recentKeys->merge($manualRequests->whereNotNull('manual_completed_at')
            ->flatMap(fn (ContentRequest $request): array => $this->requestKeys($request)))->unique();
        $pendingRequests = $website->contentRequests()->pendingInQueueOrder()->with('seoImpact')->get();
        $automaticAuditIds = $pendingRequests->filter(fn (ContentRequest $request): bool => (bool) data_get($request->competitor_context, 'automatic'))
            ->pluck('competitor_context.audit_id')->filter();
        $eligibleAuditIds = $automaticAuditIds->isEmpty() ? collect() : app(CompetitorContentContext::class)->eligibleAudits($website)
            ->whereKey($automaticAuditIds)->pluck('id');
        $states = $this->queueStates($generation->plan, $generation, includeRepository: false);
        $openKeys = $openKeys->merge($pendingRequests->filter(fn ($request): bool => ($states[$request->id]['state'] ?? null) === 'preparing')->flatMap(fn ($request): array => $this->requestKeys($request)))->unique();
        $requests = $pendingRequests
            ->reject(fn (ContentRequest $request): bool => (data_get($request->competitor_context, 'automatic') && ($generation->plan->competitor_research_mode !== 'drafts'
                || ! $eligibleAuditIds->contains(data_get($request->competitor_context, 'audit_id'))))
                || $openKeys->intersect($this->requestKeys($request))->isNotEmpty()
                || $recentKeys->intersect($this->requestKeys($request))->isNotEmpty()
                || $protectedKeys->intersect($this->requestKeys($request))->isNotEmpty()
                || ($states[$request->id]['state'] ?? 'ready') !== 'ready');
        $preflightDeadline = microtime(true) + 35;
        $eligibleRequests = new EloquentCollection;
        foreach ($requests as $request) {
            if (app(ContentBudget::class)->pauseReason($generation->plan, $eligibleRequests->concat([$request]), except: $generation->id) !== null) {
                continue;
            }
            if (! $repositoryPreflight || app(ContentRepositoryPreflight::class)->check($request, $generation->repository, $preflightDeadline)['state'] === 'ready') {
                $eligibleRequests->push($request);
            }
            if ($eligibleRequests->count() === 2) {
                break;
            }
        }
        $requests = $eligibleRequests;
        if ($requests->isEmpty() && $generation->trigger === 'scheduled'
            && $generation->plan->competitor_research_mode === 'drafts'
            && ! app(ContentSchedule::class)->pauseReason($generation->plan)) {
            $blockedKeys = $openKeys->merge($recentKeys)->merge($protectedKeys);
            $opportunity = app(CompetitorContentContext::class)->opportunities($website)->first(function (CompetitorOpportunity $opportunity) use ($blockedKeys): bool {
                $brief = $opportunity->brief;
                $term = $brief['primary_keyword'] ?? null;
                if (! is_string($term) || $term === '') {
                    return false;
                }
                $keys = ['term:'.mb_strtolower(trim($term))];
                foreach ($brief['keywords'] ?? [] as $keyword) {
                    $keys[] = 'term:'.mb_strtolower(trim($keyword['keyword']));
                }
                if (! empty($brief['existing_page_url'])) {
                    $keys[] = $this->urlKey($brief['existing_page_url']);
                }

                return $blockedKeys->intersect($keys)->isEmpty();
            });
            if ($opportunity && app(ContentStrategy::class)->requestPauseReason($generation->plan, new ContentRequest(['work_type' => empty($opportunity->brief['existing_page_url']) ? 'new_article' : 'optimisation'])) === null) {
                $requests = new EloquentCollection([
                    app(ContentOpportunityQueuer::class)->queueCompetitor($opportunity, $generation->plan->creator, automatic: true),
                ]);
            }
        }
        $eligible = $active->filter(function (SeoTargetKeyword $keyword) use ($snapshot, $openKeys, $recentKeys, $protectedKeys): bool {
            $row = collect($snapshot)->firstWhere('id', $keyword->id);
            $keys = $this->targetKeys($keyword->id, $keyword->term, $row['intended_url'] ?? $row['ranking_url'] ?? null);

            return $openKeys->intersect($keys)->isEmpty()
                && $protectedKeys->intersect($keys)->isEmpty()
                && ($keyword->last_selected_at === null || $keyword->last_selected_at->lessThanOrEqualTo(now()->subDays(14)))
                && $recentKeys->intersect($keys)->isEmpty();
        });

        return ['requests' => $requests, 'target' => $requests->isEmpty() && ($generation->plan->content_mode ?? 'balanced') !== 'new_only' ? $this->targets->select($eligible) : null, 'snapshot' => $snapshot];
    }

    /** @return array<int, array{state: string, reason: string, eligible_at: ?string}> */
    public function queueStates(ContentPlan $plan, ?ContentGeneration $generation = null, bool $includeRepository = true, bool $copilot = true): array
    {
        $generation ??= new ContentGeneration;
        $generation->setRelation('plan', $plan);
        $history = $this->history($generation);
        $requests = $plan->website->contentRequests()->pendingInQueueOrder()->with('seoImpact')->get();
        $manual = $plan->website->contentRequests()->whereNotNull('manual_started_at')
            ->where(fn ($query) => $query->whereNull('manual_completed_at')->orWhere('manual_completed_at', '>', now()->subDays(14)))
            ->with('seoImpact')->get();
        $impacts = SeoImpact::where('website_id', $plan->website_id)->where('live_at', '>', now()->subDays(14))->get();
        $states = [];
        foreach ($requests as $request) {
            $state = ['state' => 'ready', 'reason' => 'Eligible for preparation.', 'eligible_at' => null];
            $strategyReason = app(ContentStrategy::class)->requestPauseReason($plan, $request) ?? app(ContentBudget::class)->pauseReason($plan, collect([$request]), except: $generation->id, copilot: $copilot);
            if ($strategyReason) {
                $state = ['state' => 'strategy', 'reason' => $strategyReason, 'eligible_at' => null];
            }
            if ($copilot) {
                $lock = Cache::lock('content-request-work-'.$request->id, 180);
                if (! $lock->get()) {
                    $state = ['state' => 'preparing', 'reason' => 'This request is being prepared or updated. Wait for that work to finish.', 'eligible_at' => null];
                } else {
                    $lock->release();
                }
            }
            $keys = collect($this->requestKeys($request));
            $until = null;
            $review = false;
            $blocked = collect();
            foreach ($history as $previous) {
                if ($keys->intersect($this->generationKeys($previous))->isEmpty()) {
                    continue;
                }
                $blocked = $blocked->merge($keys->intersect($this->generationKeys($previous)));
                $review = $review || ($previous->status === ContentGeneration::STATUS_PULL_REQUEST_OPEN && $previous->pull_request_state !== 'closed');
                $changed = $previous->merged_at ?? ($previous->copilot_task_id ? $previous->started_at : null);
                if ($changed && $changed->greaterThan(now()->subDays(14))) {
                    $date = $changed->copy()->addDays(14);
                    $until = ! $until || $date->greaterThan($until) ? $date : $until;
                }
            }
            foreach ($manual as $previous) {
                if ($keys->intersect($this->requestKeys($previous))->isEmpty()) {
                    continue;
                }
                $blocked = $blocked->merge($keys->intersect($this->requestKeys($previous)));
                $review = $review || $previous->manual_completed_at === null;
                if ($previous->manual_completed_at) {
                    $date = $previous->manual_completed_at->copy()->addDays(14);
                    $until = ! $until || $date->greaterThan($until) ? $date : $until;
                }
            }
            foreach ($impacts as $impact) {
                if ($keys->intersect(array_map($this->urlKey(...), $impact->target_urls))->isNotEmpty()) {
                    $blocked = $blocked->merge($keys->intersect(array_map($this->urlKey(...), $impact->target_urls)));
                    $date = $impact->live_at->copy()->addDays(14);
                    $until = ! $until || $date->greaterThan($until) ? $date : $until;
                }
            }
            $scope = $blocked->unique()->filter(fn (string $key): bool => str_starts_with($key, 'url:') || str_starts_with($key, 'file:'))->map(fn (string $key): string => substr($key, strpos($key, ':') + 1))->implode(', ');
            $detail = $scope !== '' ? ' Required scope: '.$scope : '';
            if ($until) {
                $state = ['state' => 'cooldown', 'reason' => 'A required page, file or related task changed within the last 14 days.'.$detail, 'eligible_at' => $until->toIso8601String()];
            }
            if ($review) {
                $state = ['state' => 'review', 'reason' => 'Required work overlaps an open pull request or active manual task.'.$detail, 'eligible_at' => null];
            }
            if ($includeRepository && $state['state'] === 'ready' && ($request->preflight['state'] ?? 'ready') !== 'ready') {
                $saved = $request->preflight;
                if ($saved['state'] !== 'cooldown' || now()->lessThan(Carbon::parse($saved['eligible_at']))) {
                    $state = array_intersect_key($saved, $state);
                }
            }
            if ($request->held_at) {
                $state = ['state' => 'held', 'reason' => $request->hold_reason ?: 'Manually placed on hold.', 'eligible_at' => null];
            }
            $states[$request->id] = $state;
        }

        return $states;
    }

    /** @return Collection<int, ContentGeneration> */
    public function history(ContentGeneration $generation): Collection
    {
        return $generation->plan->generations()->whereKeyNot($generation->id)
            ->where(function ($query): void {
                $query->where('status', ContentGeneration::STATUS_PULL_REQUEST_OPEN)
                    ->orWhere('started_at', '>', now()->subDays(14))
                    ->orWhere('merged_at', '>', now()->subDays(14));
            })->with('contentRequests.seoImpact')->latest('id')->get();
    }

    public function pendingRequestsBlockedByReview(ContentPlan $plan): bool
    {
        $openKeys = $plan->generations()
            ->where('status', ContentGeneration::STATUS_PULL_REQUEST_OPEN)
            ->where(fn ($query) => $query->whereNull('pull_request_state')->orWhere('pull_request_state', '!=', 'closed'))
            ->with('contentRequests.seoImpact')->get()
            ->flatMap(fn (ContentGeneration $generation): array => $this->generationKeys($generation))->unique();

        if ($openKeys->isEmpty()) {
            return false;
        }

        $requests = $plan->website->contentRequests()->pendingInQueueOrder()->with('seoImpact')->get();

        return $requests->isNotEmpty() && $requests->every(fn (ContentRequest $request): bool => $openKeys->intersect($this->requestKeys($request))->isNotEmpty());
    }

    /** @return list<string> */
    private function generationKeys(ContentGeneration $generation): array
    {
        $target = collect($generation->target_keyword_context ?? [])->firstWhere('id', $generation->seo_target_keyword_id);
        $keys = $generation->seo_target_keyword_id
            ? $this->targetKeys($generation->seo_target_keyword_id, $target['term'] ?? '', $target['ranking_url'] ?? null) : [];

        return [...$keys, ...$generation->contentRequests->flatMap(fn (ContentRequest $request): array => $this->requestKeys($request))->all()];
    }

    /** @return list<string> */
    private function targetKeys(int $id, string $term, ?string $url): array
    {
        return array_values(array_filter(['target:'.$id, $term !== '' ? 'term:'.mb_strtolower(trim($term)) : null, $url ? $this->urlKey($url) : null]));
    }

    /** @return list<string> */
    private function requestKeys(ContentRequest $request): array
    {
        preg_match_all('~https?://[^\s<>"\)]+~i', $request->instructions, $matches);
        $keys = array_map(fn (string $url): string => $this->urlKey(rtrim($url, '.,;')), $matches[0]);
        $keys[] = 'request:'.hash('sha256', SeoTargetKeyword::normalize($request->instructions));
        if ($request->seoImpact) {
            $keys = [...$keys, ...array_map(fn (string $url): string => $this->urlKey($url), $request->seoImpact->target_urls),
                ...array_map(fn (string $query): string => 'term:'.mb_strtolower(trim($query)), $request->seoImpact->target_queries)];
        }
        $existingUrl = data_get($request->competitor_context, 'existing_page_url');
        if (is_string($existingUrl) && $existingUrl !== '') {
            $keys[] = $this->urlKey($existingUrl);
        }
        foreach (data_get($request->competitor_context, 'keywords', []) as $keyword) {
            if (! empty($keyword['keyword'])) {
                $keys[] = 'term:'.mb_strtolower(trim($keyword['keyword']));
            }
        }
        $term = data_get($request->competitor_context, 'primary_keyword');
        if (is_string($term) && $term !== '') {
            $keys[] = 'term:'.mb_strtolower(trim($term));
        }

        foreach ($request->dependencies['urls'] ?? [] as $url) {
            $keys[] = $this->urlKey($url);
        }
        foreach ($request->dependencies['files'] ?? [] as $file) {
            $keys[] = 'file:'.$file;
        }

        return array_values(array_filter($keys));
    }

    private function urlKey(string $url): string
    {
        return app(SeoImpactTracker::class)->urlKey($url);
    }
}
