<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeploymentMethod;
use App\Enums\OptimisationStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentGeneration;
use App\Models\Optimisation;
use App\Models\RemediationRun;
use App\Models\SearchConsoleMetric;
use App\Models\Website;
use App\Models\WebsiteDomain;
use App\Services\ContentQueueOverview;
use App\Services\DashboardSchedule;
use App\Services\DashboardWorkActivity;
use App\Support\WebsiteNavigation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function overview(Request $request, DashboardSchedule $schedule, ContentQueueOverview $contentQueue, DashboardWorkActivity $workActivity): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $websites = Website::query()
            ->accessibleTo($request->user())
            ->with(['owner', 'latestHealthReport', 'contentPlan.website.owner', 'contentPlan.website.repository', 'contentPlan.creator.githubAuthorization'])
            ->withCount(['contentRequests as pending_content_requests_count' => fn ($query) => $query->whereNull('picked_up_at')])
            ->orderBy('name')
            ->get();

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

        return view('admin.overview', [
            'websites' => $websites,
            'contentQueue' => $contentQueue->forWebsites($websites),
            'workActivity' => $workActivity->forWebsites($websites->modelKeys()),
            'automationSchedule' => $schedule->forWebsites($websites->filter(fn (Website $website): bool => $website->is_active && (! $website->owner || $website->owner->hasActiveMembership()))),
            'optimisations' => $optimisations,
            'contentReviews' => $contentReviews,
            'remediationReviews' => $remediationReviews,
            'approvalCount' => $optimisations->total() + $contentReviews->total() + $remediationReviews->total(),
        ]);
    }

    public function index(Request $request, DashboardSchedule $schedule): View
    {
        $user = $request->user();
        $website = $request->attributes->get('currentWebsite');

        if (! $website instanceof Website) {
            return view('admin.dashboard', [
                'website' => null,
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
            'contentRequests as pending_content_requests_count' => fn ($query) => $query->whereNull('picked_up_at'),
            'optimisations as live_pixel_changes_count' => fn ($query) => $query->where('status', 'deployed')->where('deployment_method', 'pixel'),
        ]);

        $currentSearchMonth = today()->startOfMonth();
        $searchMonths = [$currentSearchMonth, $currentSearchMonth->copy()->subMonth()];
        $searchMetrics = $website->searchConsoleConnection
            ? SearchConsoleMetric::query()
                ->whereBelongsTo($website)
                ->where('search_console_connection_id', $website->searchConsoleConnection->id)
                ->where('dimension_key', SearchConsoleMetric::SITE_DIMENSION_KEY)
                ->where('property_hash', hash('sha256', $website->searchConsoleConnection->property_url ?? ''))
                ->whereIn('month', $searchMonths)
                ->get()
                ->keyBy(fn (SearchConsoleMetric $metric): string => $metric->month->toDateString())
            : collect();

        $automationSchedule = $schedule->forWebsites(collect([$website]));
        $isTrialActive = $user?->onboarding_status === 'trial_active' && $user->onboarding_trial_ends_at?->isFuture();

        return view('admin.dashboard', [
            'website' => $website,
            'report' => $website->latestHealthReport,
            'topFindings' => $this->topFindings($website),
            'searchMonths' => $searchMonths,
            'searchMetrics' => $searchMetrics,
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
