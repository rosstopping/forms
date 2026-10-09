<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeploymentMethod;
use App\Enums\OptimisationStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentGeneration;
use App\Models\Optimisation;
use App\Models\RemediationRun;
use App\Models\SeoImpact;
use App\Models\SeoWin;
use App\Models\Website;
use App\Models\WebsiteDomain;
use App\Models\WeeklyReport;
use App\Services\AiVisibilityReport;
use App\Services\ContentQueueOverview;
use App\Services\DashboardSchedule;
use App\Services\DashboardWorkActivity;
use App\Services\SearchConsoleProgress;
use App\Services\SeoProgressTimeline;
use App\Services\WebsiteActionCenter;
use App\Support\WebsiteNavigation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function overview(Request $request, DashboardSchedule $schedule, ContentQueueOverview $contentQueue, DashboardWorkActivity $workActivity): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $websites = Website::query()
            ->accessibleTo($request->user())
            ->with(['owner', 'latestHealthReport', 'contentPlan.website.owner', 'contentPlan.website.repository', 'contentPlan.creator.githubAuthorization'])
            ->withCount(['contentRequests as pending_content_requests_count' => fn ($query) => $query->where('planning_status', 'queued')->whereNull('picked_up_at')])
            ->orderBy('name')
            ->get();

        $filters = $request->validate([
            'hub' => ['nullable', Rule::in(['priorities', 'approvals', 'results', 'wins', 'automation', 'websites'])],
            'site_id' => ['nullable', 'integer', Rule::in($websites->modelKeys())],
            'action_state' => ['nullable', Rule::in(['open', 'queued', 'measuring', 'review', 'completed'])],
            'actions_page' => ['nullable', 'integer', 'min:1'],
            'signal_category' => ['nullable', Rule::in(['win', 'alert', 'opportunity'])],
            'win_status' => ['nullable', Rule::in(['unshared', 'shared', 'dismissed'])],
            'wins_page' => ['nullable', 'integer', 'min:1'],
            'sites_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $allWebsites = $websites;
        $websites = ! empty($filters['site_id']) ? $websites->where('id', (int) $filters['site_id'])->values() : $websites;
        $hubSection = $filters['hub'] ?? 'priorities';
        $actionState = $filters['action_state'] ?? 'open';
        $actions = app(WebsiteActionCenter::class)->forWebsites($websites);
        $actionCounts = $actions->countBy('stage');
        $filteredActions = $actions->where('stage', $actionState)->values();
        $actionPage = $request->integer('actions_page', 1);
        $priorityActions = new LengthAwarePaginator($filteredActions->forPage($actionPage, 12)->values(), $filteredActions->count(), 12, $actionPage, ['path' => route('admin.overview'), 'pageName' => 'actions_page', 'query' => $request->query()]);
        $sitesPage = $request->integer('sites_page', 1);
        $websiteDirectory = new LengthAwarePaginator($websites->forPage($sitesPage, 15)->values(), $websites->count(), 15, $sitesPage, ['path' => route('admin.overview'), 'pageName' => 'sites_page', 'query' => $request->query()]);

        $optimisations = Optimisation::query()
            ->whereIn('website_id', $websites->modelKeys())
            ->whereIn('status', [OptimisationStatus::Draft, OptimisationStatus::PendingApproval])
            ->where(fn ($query) => $query
                ->where('deployment_method', '!=', DeploymentMethod::Pixel)
                ->orWhereHas('website', fn ($query) => $query->where('pixel_enabled', true)))
            ->with(['website:id,name', 'page:id,website_health_report_id'])
            ->oldest()->paginate(10, ['*'], 'changes_page');

        $contentReviews = ContentGeneration::query()
            ->whereHas('plan', fn ($query) => $query->whereIn('website_id', $websites->modelKeys()))
            ->where('status', ContentGeneration::STATUS_PULL_REQUEST_OPEN)
            ->with('plan.website:id,name')
            ->oldest()->paginate(10, ['*'], 'content_page');

        $remediationReviews = RemediationRun::query()
            ->whereHas('report', fn ($query) => $query->whereIn('website_id', $websites->modelKeys()))
            ->where('status', RemediationRun::STATUS_PULL_REQUEST_OPEN)
            ->with('report.website:id,name')
            ->oldest()->paginate(10, ['*'], 'fixes_page');

        $winStatus = $filters['win_status'] ?? 'unshared';
        $signalCategory = $filters['signal_category'] ?? 'win';
        $wins = SeoWin::whereIn('website_id', $websites->modelKeys())->where('category', $signalCategory)->with('website:id,name')
            ->when($winStatus === 'dismissed', fn ($query) => $query->whereNotNull('dismissed_at'), fn ($query) => $query->whereNull('dismissed_at'))
            ->when($winStatus === 'shared', fn ($query) => $query->whereNotNull('shared_at'))
            ->when($winStatus === 'unshared', fn ($query) => $query->whereNull('shared_at'))
            ->orderByRaw("CASE WHEN importance = 'high' THEN 0 ELSE 1 END")->latest('confirmed_at')
            ->paginate(15, ['*'], 'wins_page')->withQueryString();

        return view('admin.overview', [
            'wins' => $wins,
            'winStatus' => $winStatus,
            'signalCategory' => $signalCategory,
            'websites' => $websites,
            'allWebsites' => $allWebsites,
            'hubSection' => $hubSection,
            'actionState' => $actionState,
            'actionCounts' => $actionCounts,
            'priorityActions' => $priorityActions,
            'websiteDirectory' => $websiteDirectory,
            'impactReviews' => SeoImpact::whereIn('website_id', $websites->modelKeys())->whereNotNull('review_available_at')->whereNull('acknowledged_at')->with('website:id,name')->latest('review_available_at')->paginate(10, ['*'], 'results_page')->withQueryString(),
            'contentQueue' => $contentQueue->forWebsites($websites),
            'workActivity' => $workActivity->forWebsites($websites->modelKeys()),
            'automationSchedule' => $schedule->forWebsites($websites->filter(fn (Website $website): bool => $website->is_active && (! $website->owner || $website->owner->hasActiveMembership()))),
            'optimisations' => $optimisations,
            'contentReviews' => $contentReviews,
            'remediationReviews' => $remediationReviews,
            'approvalCount' => $optimisations->total() + $contentReviews->total() + $remediationReviews->total(),
        ]);
    }

    public function weeklyOverview(Request $request, Website $website, DashboardSchedule $schedule): View
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        $request->attributes->set('currentWebsite', $website);

        return $this->index($request, $schedule);
    }

    public function index(Request $request, DashboardSchedule $schedule): View
    {
        $user = $request->user();
        $website = $request->attributes->get('currentWebsite');

        if (! $website instanceof Website) {
            return view($user->isAdmin() ? 'admin.dashboard' : 'admin.customer-overview', [
                'website' => null,
            ]);
        }

        if (! $user->isAdmin()) {
            $website->load('searchConsoleConnection');
            $reports = WeeklyReport::where('website_id', $website->id)->whereNotNull('generated_at');
            $weeklyOverview = $request->filled('weekly_report')
                ? (clone $reports)->whereKey($request->integer('weekly_report'))->firstOrFail()
                : (clone $reports)->latest('period_end')->first();

            return view('admin.customer-overview', [
                'website' => $website,
                'progressTimeline' => app(SeoProgressTimeline::class)->forWebsite($website),
                'weeklyOverview' => $weeklyOverview,
                'weeklyHistory' => (clone $reports)->latest('period_end')->paginate(12, ['id', 'period_start', 'period_end'], 'reports_page')->withQueryString(),
                'searchProgress' => app(SearchConsoleProgress::class)->forWebsite($website, $request->only(['period', 'comparison', 'start', 'end'])),
            ]);
        }

        $website->load([
            'domains',
            'latestHealthReport.pages:id,website_health_report_id,url,checks',
            'contentPlan',
            'contentRequests' => fn (HasMany $query) => $query->latest()->limit(4),
            'searchConsoleConnection',
        ])->loadCount([
            'forms',
            'submissions',
            'contentRequests as pending_content_requests_count' => fn ($query) => $query->where('planning_status', 'queued')->whereNull('picked_up_at'),
            'optimisations as live_pixel_changes_count' => fn ($query) => $query->where('status', 'deployed')->where('deployment_method', 'pixel'),
        ]);

        $weeklyReports = WeeklyReport::query()->where('website_id', $website->id)->whereNotNull('generated_at');
        $weeklyOverview = $request->filled('weekly_report')
            ? (clone $weeklyReports)->whereKey($request->integer('weekly_report'))->firstOrFail()
            : (clone $weeklyReports)->latest('period_end')->first();
        $weeklyHistory = (clone $weeklyReports)->latest('period_end')->paginate(12, ['id', 'period_start', 'period_end'], 'reports_page')->withQueryString();

        $automationSchedule = $schedule->forWebsites(collect([$website]));
        $isTrialActive = $user?->onboarding_status === 'trial_active' && $user->onboarding_trial_ends_at?->isFuture();

        return view('admin.dashboard', [
            'website' => $website,
            'priorityActions' => ($user->isAdmin() || $website->owner?->hasMembershipFeature('growth')) ? app(WebsiteActionCenter::class)->forWebsite($website)->where('stage', 'open')->take(5) : collect(),
            'impactReviews' => ($user->isAdmin() || $website->owner?->hasMembershipFeature('growth')) ? SeoImpact::where('website_id', $website->id)->whereNotNull('review_available_at')->whereNull('acknowledged_at')->latest('review_available_at')->limit(5)->get() : collect(),
            'progressTimeline' => app(SeoProgressTimeline::class)->forWebsite($website),
            'weeklyOverview' => $weeklyOverview,
            'aiVisibility' => app(AiVisibilityReport::class)->forPeriod($website, today()->subDays(6), now()->endOfDay()),
            'weeklyHistory' => $weeklyHistory,
            'report' => $website->latestHealthReport,
            'topFindings' => $this->topFindings($website),
            'searchProgress' => app(SearchConsoleProgress::class)->forWebsite($website, $request->only(['period', 'comparison', 'start', 'end'])),
            'nextHealthRun' => $automationSchedule->firstWhere('type', 'Health report'),
            'nextContentRun' => $automationSchedule->firstWhere('type', 'Content queue'),
            'canManageWebsite' => $website->isManageableBy($user),
            'isTrialActive' => $isTrialActive,
            'onboardingChecklist' => $isTrialActive ? collect([
                ['label' => 'Verify website ownership', 'complete' => $website->domains->contains(fn ($domain): bool => $domain->is_primary && $domain->ownership_status === WebsiteDomain::OWNERSHIP_VERIFIED), 'url' => WebsiteNavigation::routeFor($website, 'search')],
                ['label' => 'Book your onboarding call', 'complete' => $user->onboarding_call_booked_at !== null, 'url' => route('admin.onboarding-call')],
                ['label' => 'Connect Google Search Console', 'complete' => filled($website->searchConsoleConnection?->property_url), 'url' => WebsiteNavigation::routeFor($website, 'search')],
                ['label' => 'Review your first health report', 'complete' => $user->onboarding_health_report_viewed_at !== null, 'url' => $website->latestHealthReport ? route('admin.website-health-reports.show', [$website, $website->latestHealthReport]) : WebsiteNavigation::routeFor($website, 'health')],
            ]) : collect(),
        ]);
    }

    /**
     * @return Collection<int, array{label: string, message: string, status: string, url: ?string}>
     */
    private function topFindings(Website $website): Collection
    {
        $report = $website->latestHealthReport;

        if (! $report) {
            return collect();
        }

        $siteFindings = collect($report->checks ?? [])->map(fn (array $check): array => [
            'label' => (string) ($check['label'] ?? 'Website finding'),
            'message' => (string) ($check['message'] ?? 'This check needs attention.'),
            'status' => (string) ($check['status'] ?? 'warning'),
            'url' => null,
        ]);

        $pageFindings = $report->pages->flatMap(fn ($page): Collection => collect($page->checks ?? [])->map(fn (array $check): array => [
            'label' => (string) ($check['label'] ?? 'Page finding'),
            'message' => (string) ($check['message'] ?? 'This page needs attention.'),
            'status' => (string) ($check['status'] ?? 'warning'),
            'url' => $page->url,
        ]));

        return $siteFindings
            ->concat($pageFindings)
            ->filter(fn (array $finding): bool => in_array($finding['status'], ['failed', 'warning'], true))
            ->sortBy(fn (array $finding): int => $finding['status'] === 'failed' ? 0 : 1)
            ->unique(fn (array $finding): string => $finding['label'].'|'.$finding['message'].'|'.$finding['url'])
            ->take(5)
            ->values();
    }
}
