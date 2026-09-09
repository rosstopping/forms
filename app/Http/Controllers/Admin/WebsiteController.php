<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use App\Models\WebsiteDomain;
use App\Services\PixelInstallationSnippet;
use App\Services\SearchConsoleClient;
use App\Services\SearchConsoleHistoryStore;
use App\Services\WebsiteProspectService;
use App\Support\MembershipPlan;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function __construct(
        protected SearchConsoleClient $searchConsole,
        protected SearchConsoleHistoryStore $searchConsoleHistory,
    ) {}

    public function index(Request $request): View
    {
        $query = Website::query();

        if (! $request->user()?->isAdmin()) {
            $query->accessibleTo($request->user());
        }

        $websites = $query
            ->with('latestHealthReport')
            ->withCount('forms')
            ->withCount('submissions')
            ->latest('created_at')
            ->paginate(15);

        return view('admin.websites.index', compact('websites'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.websites.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->merge(['domain' => $this->normalizeDomain($request->string('domain')->toString())]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => $this->domainRules(),
            'user_id' => ['nullable', 'exists:users,id'],
            'health_reports_enabled' => ['required', 'boolean'],
        ]);

        $website = DB::transaction(function () use ($data): Website {
            $website = Website::query()->create([
                'name' => $data['name'],
                'user_id' => $data['user_id'] ?? null,
                'is_active' => true,
                'auto_discovered' => false,
                'email_enabled' => true,
                'email_recipients' => [config('forms.default_recipient')],
                'webhook_enabled' => false,
                'health_reports_enabled' => $data['health_reports_enabled'],
            ]);

            $website->domains()->create([
                'domain' => $data['domain'],
                'is_primary' => true,
            ]);

            if ($website->user_id) {
                $website->members()->attach($website->user_id, ['role' => Website::MEMBER_ROLE_MANAGER]);
            }

            return $website;
        });

        return Redirect::route('admin.websites.show', $website)->with('status', 'Website created.');
    }

    public function show(
        Request $request,
        Website $website,
        WebsiteProspectService $websiteProspects,
        PixelInstallationSnippet $pixelInstallation,
    ): View|RedirectResponse {
        $user = Auth::user();

        abort_unless($website->isAccessibleBy($user), 403);

        if ($request->routeIs('admin.websites.section') && $request->route('section') === 'health') {
            $latestReport = $website->healthReports()->latest('created_at')->latest('id')->first();

            if ($latestReport) {
                return Redirect::route('admin.website-health-reports.show', [$website, $latestReport]);
            }
        }

        $website->load([
            'domains',
            'owner:id,name,email,role,membership_tier,admin_membership_tier,admin_membership_expires_at,membership_status,membership_current_period_end',
            'members' => fn ($query) => $query->select('users.id', 'users.name', 'users.email', 'users.admin_membership_tier', 'users.admin_membership_expires_at')->orderBy('name'),
            'forms' => fn ($query) => $query->withCount('submissions')->latest('created_at'),
            'healthReports' => fn ($query) => $query
                ->latest('created_at')
                ->latest('id')
                ->select(['id', 'website_id', 'status', 'overall_status', 'passed_checks', 'warning_checks', 'failed_checks', 'created_at']),
            'repository.installation',
            'wordpressConnection',
            'wordpressStaticReleases' => fn ($query) => $query->latest('created_at')->limit(5),
            'searchConsoleConnection',
            'mailConnection',
            'searchOpportunities' => fn ($query) => $query->whereIn('status', ['open', 'queued'])->orderByDesc('priority_score')->limit(20),
            'businessProfileConnection.audits' => fn ($query) => $query->with('recommendations')->latest()->limit(8),
            'businessProfileConnection.posts' => fn ($query) => $query->latest()->limit(8),
            'businessProfileConnection.reviews' => fn ($query) => $query->latest('reviewed_at')->limit(20),
            'contentPlan.generations' => fn ($query) => $query->latest('created_at')->limit(8),
            'contentRequests' => fn ($query) => $query->with(['creator', 'generation'])->latest('created_at')->limit(50),
        ]);
        $website->loadCount([
            'pixelPages',
            'optimisations as active_pixel_optimisations_count' => fn ($query) => $query
                ->where('status', 'deployed')
                ->where('deployment_method', 'pixel'),
        ]);
        $canManageMembers = $user?->can('manageMembers', $website) === true;
        $canRunHealthReports = $user?->isAdmin() === true || $website->owner?->hasMembershipFeature(MembershipPlan::FEATURE_HEALTH_REPORTS) === true;
        $canUseSearchConsole = $user?->isAdmin() === true || $website->owner?->hasMembershipFeature(MembershipPlan::FEATURE_SEARCH_CONSOLE) === true;
        $canUseGrowthFeatures = $user?->isAdmin() === true || $website->owner?->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH) === true;
        $hasContentDeliveryConnection = $website->pixel_last_seen_at !== null
            || $website->wordpressConnection?->isConnected() === true
            || $website->repository !== null;
        $contentSupportCallUrl = $user?->onboarding_status === 'trial_active'
            && $user->onboarding_trial_ends_at?->isFuture() === true
            && ! $user->onboarding_call_completed_at
                ? route('admin.onboarding-call')
                : (string) config('marketing.booking_url');
        $canUseAutoresponders = $website->canUseAutoresponders($user);
        $canUseCompleteFeatures = $user?->isAdmin() === true || $website->owner?->hasMembershipFeature(MembershipPlan::FEATURE_COMPLETE) === true;
        $searchConsoleReport = null;
        $searchConsoleHistory = [];
        $searchConsoleReportUnavailable = false;
        $seoGeneration = $website->seoSnapshots()->latest('id')->first();
        $seoSnapshot = $website->seoSnapshots()
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->latest('completed_at')
            ->first();
        $seoHistory = $website->seoSnapshots()
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'organic_keywords', 'estimated_organic_traffic', 'top_3_keywords', 'top_10_keywords', 'top_20_keywords']);
        $seoFilter = $request->string('seo_filter')->toString();
        $seoFilter = in_array($seoFilter, ['top_3', 'page_1', 'positions_11_20', 'positions_21_50', 'positions_51_100', 'commercial'], true) ? $seoFilter : 'all';
        $seoSort = $request->string('seo_sort')->toString();
        $seoSort = in_array($seoSort, ['position', 'search_volume', 'estimated_traffic', 'cpc'], true) ? $seoSort : 'position';
        $seoDirection = $request->string('seo_direction')->toString() === 'asc' ? 'asc' : 'desc';
        $seoKeywords = null;
        $seoReferringDomains = collect();
        $trackedCompetitors = $website->competitors()->with('latestAudit')->orderBy('excluded')->orderBy('domain')->get();
        $seoCompetitors = collect();
        $seoOpportunities = collect();
        $strikingDistanceCount = 0;

        if ($seoSnapshot) {
            $seoReferringDomains = $seoSnapshot->referringDomains()
                ->orderByDesc('domain_rank')
                ->orderByDesc('backlinks_count')
                ->limit(10)
                ->get();
            $seoCompetitors = $seoSnapshot->competitors()
                ->orderByDesc('common_keywords')
                ->orderByDesc('estimated_traffic')
                ->get();
            $seoOpportunities = $seoSnapshot->opportunities()
                ->with('keyword')
                ->whereIn('status', ['open', 'queued'])
                ->orderByDesc('priority_score')
                ->limit(20)
                ->get();
            $strikingDistanceCount = $seoSnapshot->keywords()->whereBetween('position', [4, 20])->count();
            $seoKeywordsQuery = $seoSnapshot->keywords();

            match ($seoFilter) {
                'top_3' => $seoKeywordsQuery->whereBetween('position', [1, 3]),
                'page_1' => $seoKeywordsQuery->whereBetween('position', [1, 10]),
                'positions_11_20' => $seoKeywordsQuery->whereBetween('position', [11, 20]),
                'positions_21_50' => $seoKeywordsQuery->whereBetween('position', [21, 50]),
                'positions_51_100' => $seoKeywordsQuery->whereBetween('position', [51, 100]),
                'commercial' => $seoKeywordsQuery->whereIn('search_intent', ['commercial', 'transactional']),
                default => null,
            };

            $seoKeywords = $seoKeywordsQuery
                ->orderBy($seoSort, $seoDirection)
                ->orderBy('id')
                ->simplePaginate(50, pageName: 'seo_page')
                ->withQueryString();
        }

        if ($website->is_active && $canUseSearchConsole && $website->searchConsoleConnection?->property_url) {
            try {
                $connection = $website->searchConsoleConnection;
                $cacheKey = 'search-console-report:'.$connection->id.':'.hash('sha256', $connection->property_url).':'.$connection->updated_at->timestamp;
                $searchConsoleReport = Cache::remember($cacheKey, now()->addMinutes(15), fn (): array => $this->searchConsole->report($connection));
                $searchConsoleHistory = Cache::remember($cacheKey.':monthly-history', now()->addHours(6), fn (): array => $this->searchConsoleHistory->syncSite($connection));
            } catch (\Throwable) {
                $searchConsoleReportUnavailable = true;
            }
        }

        $canManageWebsite = $website->isManageableBy($user);
        $websiteUsers = $website->members
            ->map(fn (User $member): array => [
                'user' => $member,
                'role' => $member->pivot->role,
            ]);

        if ($website->owner && ! $websiteUsers->contains(fn (array $websiteUser): bool => $websiteUser['user']->is($website->owner))) {
            $websiteUsers->prepend([
                'user' => $website->owner,
                'role' => Website::MEMBER_ROLE_MANAGER,
            ]);
        }

        $managerIds = $websiteUsers
            ->where('role', Website::MEMBER_ROLE_MANAGER)
            ->pluck('user.id')
            ->unique()
            ->values();
        $soleManagerId = $managerIds->count() === 1 ? $managerIds->first() : null;
        $dataForSeoConfigured = filled(config('services.dataforseo.login')) && filled(config('services.dataforseo.password'));
        $outreachProspect = $user?->isAdmin() ? $websiteProspects->find($website) : null;
        $pixelInstallationSnippet = $pixelInstallation->for($website);
        $pixelOptimisations = $canUseGrowthFeatures
            ? $website->optimisations()
                ->with(['currentVersion', 'page.report', 'contentRequest', 'deployments' => fn ($query) => $query->with(['version', 'performer'])->latest('performed_at')])
                ->latest('updated_at')
                ->limit(500)
                ->get()
            : collect();
        $websiteAiWeeklyLimit = (int) config('memberships.website_ai_questions_per_week', 25);
        $websiteAiQuestions = collect();
        $websiteAiQuestionsUsed = 0;

        if ($canUseCompleteFeatures) {
            $websiteAiQuestions = WebsiteAiQuestion::query()
                ->whereBelongsTo($website)
                ->whereBelongsTo($user)
                ->latest()
                ->limit(10)
                ->get();
            $websiteAiQuestionsUsed = WebsiteAiQuestion::query()
                ->whereBelongsTo($website)
                ->whereBelongsTo($user)
                ->where('created_at', '>=', now()->startOfWeek())
                ->countsTowardsAllowance()
                ->count();
        }

        return view('admin.websites.show', compact(
            'website', 'canManageMembers', 'canManageWebsite', 'canRunHealthReports', 'canUseSearchConsole',
            'searchConsoleReport', 'searchConsoleHistory', 'searchConsoleReportUnavailable', 'seoGeneration', 'seoSnapshot', 'seoHistory',
            'trackedCompetitors', 'seoKeywords', 'seoReferringDomains', 'seoCompetitors', 'seoOpportunities', 'seoFilter', 'seoSort', 'seoDirection', 'strikingDistanceCount',
            'dataForSeoConfigured', 'outreachProspect', 'pixelInstallationSnippet', 'canUseGrowthFeatures', 'canUseCompleteFeatures', 'canUseAutoresponders',
            'websiteAiQuestions', 'websiteAiQuestionsUsed', 'websiteAiWeeklyLimit', 'pixelOptimisations', 'websiteUsers', 'soleManagerId',
            'hasContentDeliveryConnection', 'contentSupportCallUrl',
        ));
    }

    public function update(Request $request, Website $website): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($website->isManageableBy($user), 403);

        if (! $user?->isAdmin()) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
            ]);

            $website->update(['name' => $data['name']]);

            return Redirect::route('admin.websites.show', ['website' => $website, 'tab' => 'settings'])->with('status', 'Website name updated.');
        }

        if ($request->has('domain')) {
            $request->merge(['domain' => $this->normalizeDomain($request->string('domain')->toString())]);
        }

        foreach (['wordpress_enabled', 'pixel_enabled'] as $connectionSetting) {
            if ($request->has($connectionSetting)) {
                $request->merge([$connectionSetting => $request->boolean($connectionSetting)]);
            }
        }

        $primaryDomain = $website->primaryDomain();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subscription_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'domain' => [
                'sometimes',
                ...$this->domainRules($primaryDomain),
            ],
            'health_reports_enabled' => ['sometimes', 'boolean'],
            'wordpress_enabled' => ['sometimes', 'boolean'],
            'pixel_enabled' => ['sometimes', 'boolean'],
            'webhook_enabled' => ['sometimes', 'boolean'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'turnstile_enabled' => ['sometimes', 'boolean'],
            'turnstile_site_key' => ['nullable', 'string', 'max:255'],
            'turnstile_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        if (($data['turnstile_enabled'] ?? false) && blank($data['turnstile_site_key'] ?? $website->turnstile_site_key)) {
            return back()->withErrors(['turnstile_site_key' => 'A Turnstile site key is required when protection is enabled.'])->withInput();
        }

        if (($data['turnstile_enabled'] ?? false) && blank($data['turnstile_secret_key'] ?? $website->turnstile_secret_key)) {
            return back()->withErrors(['turnstile_secret_key' => 'A Turnstile secret key is required when protection is enabled.'])->withInput();
        }

        if (blank($data['turnstile_secret_key'] ?? null)) {
            unset($data['turnstile_secret_key']);
        }

        $domain = $data['domain'] ?? null;
        unset($data['domain']);

        $pixelEnabledChanged = array_key_exists('pixel_enabled', $data) && $website->pixel_enabled !== $data['pixel_enabled'];

        DB::transaction(function () use ($data, $domain, $pixelEnabledChanged, $primaryDomain, $website): void {
            if (array_key_exists('subscription_user_id', $data)) {
                Website::query()->whereKey($website->id)->lockForUpdate()->firstOrFail();
                $website->refresh();
                $subscriberId = $data['subscription_user_id'];

                if ($subscriberId !== null && (int) $subscriberId !== $website->user_id
                    && ! $website->members()->whereKey($subscriberId)->exists()) {
                    throw ValidationException::withMessages([
                        'subscription_user_id' => 'Choose an existing website member as the subscription account.',
                    ]);
                }

                if ($website->user_id && ! $website->members()->whereKey($website->user_id)->exists()) {
                    $website->members()->attach($website->user_id, ['role' => Website::MEMBER_ROLE_MANAGER]);
                }

                $data['user_id'] = $subscriberId;
                unset($data['subscription_user_id']);
            }

            $website->fill($data);

            if ($pixelEnabledChanged) {
                $website->pixel_payload_version++;
            }

            $website->save();

            if ($domain !== null && $domain !== $primaryDomain?->domain) {
                if ($primaryDomain) {
                    $primaryDomain->update(['domain' => $domain]);
                } else {
                    $website->domains()->create(['domain' => $domain, 'is_primary' => true]);
                }

                $website->update(['seo_history_backfilled_at' => null]);
            }
        });

        return Redirect::route('admin.websites.show', ['website' => $website, 'tab' => 'settings'])->with('status', 'Website settings updated.');
    }

    public function destroy(Request $request, Website $website): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $website->delete();

        return Redirect::route('admin.websites.index')->with('status', 'Website deleted.');
    }

    protected function normalizeDomain(string $value): string
    {
        $value = Str::lower(trim($value));
        $host = parse_url(Str::contains($value, '://') ? $value : '//'.$value, PHP_URL_HOST);
        $domain = Str::of(is_string($host) ? $host : $value)
            ->trim('.')
            ->toString();

        return idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
    }

    /** @return array<int, mixed> */
    protected function domainRules(?WebsiteDomain $ignoredDomain = null): array
    {
        return [
            'required',
            'string',
            'max:253',
            'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/',
            function (string $attribute, mixed $value, Closure $fail) use ($ignoredDomain): void {
                if (! is_string($value)) {
                    return;
                }

                $apexDomain = Str::startsWith($value, 'www.') ? Str::after($value, 'www.') : $value;
                $equivalentDomains = [$apexDomain, 'www.'.$apexDomain];
                $domainExists = WebsiteDomain::query()
                    ->whereIn('domain', $equivalentDomains)
                    ->where('ownership_status', WebsiteDomain::OWNERSHIP_VERIFIED)
                    ->when($ignoredDomain, fn ($query) => $query->whereKeyNot($ignoredDomain->id))
                    ->exists();

                if ($domainExists) {
                    $fail('That domain is already assigned to another website.');
                }
            },
        ];
    }
}
