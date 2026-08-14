<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteDomain;
use App\Services\PixelInstallationSnippet;
use App\Services\SearchConsoleClient;
use App\Services\WebsiteProspectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function __construct(protected SearchConsoleClient $searchConsole) {}

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
            'domain' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/',
                Rule::unique((new WebsiteDomain)->getTable(), 'domain'),
            ],
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

            return $website;
        });

        return Redirect::route('admin.websites.show', $website)->with('status', 'Website created.');
    }

    public function show(
        Request $request,
        Website $website,
        WebsiteProspectService $websiteProspects,
        PixelInstallationSnippet $pixelInstallation,
    ): View {
        $user = Auth::user();

        abort_unless($website->isAccessibleBy($user), 403);

        $website->load([
            'domains',
            'owner:id,name,email',
            'members' => fn ($query) => $query->select('users.id', 'users.name', 'users.email')->orderBy('name'),
            'forms' => fn ($query) => $query->withCount('submissions')->latest('created_at'),
            'healthReports' => fn ($query) => $query->latest('created_at')->limit(8),
            'repository.installation',
            'searchConsoleConnection',
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
        $users = $user?->isAdmin() ? User::query()->orderBy('name')->get(['id', 'name', 'email']) : collect();
        $canManageMembers = $user?->can('manageMembers', $website) === true;
        $availableMembers = $canManageMembers
            ? User::query()->when($website->user_id, fn ($query) => $query->whereKeyNot($website->user_id))->whereDoesntHave('sharedWebsites', fn ($query) => $query->whereKey($website->id))->orderBy('name')->get(['id', 'name', 'email'])
            : collect();
        $searchConsoleReport = null;
        $searchConsoleReportUnavailable = false;
        $seoGeneration = $website->seoSnapshots()->latest('id')->first();
        $seoSnapshot = $website->seoSnapshots()
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->latest('completed_at')
            ->first();
        $seoFilter = $request->string('seo_filter')->toString();
        $seoFilter = in_array($seoFilter, ['top_3', 'page_1', 'positions_11_20', 'positions_21_50', 'positions_51_100', 'commercial'], true) ? $seoFilter : 'all';
        $seoSort = $request->string('seo_sort')->toString();
        $seoSort = in_array($seoSort, ['position', 'search_volume', 'estimated_traffic', 'cpc'], true) ? $seoSort : 'position';
        $seoDirection = $request->string('seo_direction')->toString() === 'asc' ? 'asc' : 'desc';
        $seoKeywords = null;
        $seoReferringDomains = collect();
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
                ->limit(10)
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

        if ($website->searchConsoleConnection?->property_url) {
            try {
                $connection = $website->searchConsoleConnection;
                $cacheKey = 'search-console-report:'.$connection->id.':'.hash('sha256', $connection->property_url).':'.$connection->updated_at->timestamp;
                $searchConsoleReport = Cache::remember($cacheKey, now()->addMinutes(15), fn (): array => $this->searchConsole->report($connection));
            } catch (\Throwable) {
                $searchConsoleReportUnavailable = true;
            }
        }

        $canManageWebsite = $website->isManageableBy($user);
        $dataForSeoConfigured = filled(config('services.dataforseo.login')) && filled(config('services.dataforseo.password'));
        $outreachProspect = $user?->isAdmin() ? $websiteProspects->find($website) : null;
        $pixelInstallationSnippet = $pixelInstallation->for($website);

        return view('admin.websites.show', compact(
            'website', 'users', 'availableMembers', 'canManageMembers', 'canManageWebsite',
            'searchConsoleReport', 'searchConsoleReportUnavailable', 'seoGeneration', 'seoSnapshot',
            'seoKeywords', 'seoReferringDomains', 'seoCompetitors', 'seoOpportunities', 'seoFilter', 'seoSort', 'seoDirection', 'strikingDistanceCount',
            'dataForSeoConfigured', 'outreachProspect', 'pixelInstallationSnippet',
        ));
    }

    public function update(Request $request, Website $website): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user?->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'health_reports_enabled' => ['sometimes', 'boolean'],
            'webhook_enabled' => ['sometimes', 'boolean'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $website->fill($data)->save();

        if ($website->user_id) {
            $website->members()->detach($website->user_id);
        }

        return Redirect::route('admin.websites.show', $website)->with('status', 'Website settings updated.');
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
            ->replaceStart('www.', '')
            ->trim('.')
            ->toString();

        return idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
    }
}
