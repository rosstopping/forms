<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteDomain;
use App\Services\SearchConsoleClient;
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
            $query->where('user_id', $request->user()->id);
        }

        $websites = $query
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

    public function show(Website $website): View
    {
        $user = Auth::user();

        abort_unless($user?->isAdmin() || $website->user_id === $user?->id, 403);

        $website->load([
            'domains',
            'forms' => fn ($query) => $query->latest('created_at'),
            'healthReports' => fn ($query) => $query->latest('created_at')->limit(8),
            'repository.installation',
            'searchConsoleConnection',
            'contentPlan.generations' => fn ($query) => $query->latest('created_at')->limit(8),
            'contentRequests' => fn ($query) => $query->with('creator')->latest('created_at')->limit(20),
            'submissions' => fn ($query) => $query->latest('created_at')->limit(10),
        ]);
        $users = $user?->isAdmin() ? User::query()->orderBy('name')->get(['id', 'name', 'email']) : collect();
        $searchConsoleReport = null;
        $searchConsoleReportUnavailable = false;

        if ($user?->isAdmin() && $website->searchConsoleConnection?->property_url) {
            try {
                $connection = $website->searchConsoleConnection;
                $cacheKey = 'search-console-report:'.$connection->id.':'.hash('sha256', $connection->property_url).':'.$connection->updated_at->timestamp;
                $searchConsoleReport = Cache::remember($cacheKey, now()->addMinutes(15), fn (): array => $this->searchConsole->report($connection));
            } catch (\Throwable) {
                $searchConsoleReportUnavailable = true;
            }
        }

        return view('admin.websites.show', compact('website', 'users', 'searchConsoleReport', 'searchConsoleReportUnavailable'));
    }

    public function update(Request $request, Website $website): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user?->isAdmin(), 403);

        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'health_reports_enabled' => ['sometimes', 'boolean'],
        ]);

        $website->fill($data)->save();

        return Redirect::route('admin.websites.show', $website)->with('status', 'Website settings updated.');
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
