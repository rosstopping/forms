<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSearchConsolePropertyRequest;
use App\Models\Website;
use App\Services\GoogleOAuthClient;
use App\Services\SearchConsoleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Throwable;

class SearchConsoleController extends Controller
{
    protected const PERFORMANCE_PAGE_SIZE = 100;

    public function __construct(protected GoogleOAuthClient $oauth, protected SearchConsoleClient $searchConsole) {}

    public function connect(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $state = Crypt::encryptString(json_encode(['website_id' => $website->id, 'user_id' => $request->user()->id], JSON_THROW_ON_ERROR));

        return Redirect::away($this->oauth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);

        try {
            $state = json_decode(Crypt::decryptString($data['state']), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(403, 'The Google authorization request is invalid.');
        }

        abort_unless(data_get($state, 'user_id') === $request->user()->id, 403);
        $website = Website::query()->findOrFail(data_get($state, 'website_id'));
        $this->authorizeWebsite($request, $website);
        $this->oauth->authorize($website, $request->user(), $data['code']);

        return Redirect::route('admin.search-console.property', $website);
    }

    public function property(Request $request, Website $website): View
    {
        $this->authorizeWebsite($request, $website);
        $connection = $website->searchConsoleConnection()->firstOrFail();
        $properties = $this->searchConsole->sites($connection);

        return view('admin.websites.search-console-property', compact('website', 'properties'));
    }

    public function performance(Request $request, Website $website): View
    {
        $this->authorizeWebsite($request, $website);
        $connection = $website->searchConsoleConnection()->firstOrFail();
        $pages = $request->validate([
            'queries_page' => ['sometimes', 'integer', 'min:1'],
            'pages_page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $queryPage = (int) ($pages['queries_page'] ?? 1);
        $pagePage = (int) ($pages['pages_page'] ?? 1);
        $limit = self::PERFORMANCE_PAGE_SIZE + 1;
        $cacheKey = 'search-console-performance:'.$connection->id.':'.$connection->updated_at->timestamp.':';
        $queries = Cache::remember($cacheKey.'queries:'.$queryPage, now()->addMinutes(15), fn (): array => $this->searchConsole->queryPerformance($connection, $limit, ($queryPage - 1) * self::PERFORMANCE_PAGE_SIZE));
        $landingPages = Cache::remember($cacheKey.'pages:'.$pagePage, now()->addMinutes(15), fn (): array => $this->searchConsole->pagePerformance($connection, $limit, ($pagePage - 1) * self::PERFORMANCE_PAGE_SIZE));

        return view('admin.websites.search-console-performance', [
            'website' => $website,
            'connection' => $connection,
            'queries' => array_slice($queries, 0, self::PERFORMANCE_PAGE_SIZE),
            'landingPages' => array_slice($landingPages, 0, self::PERFORMANCE_PAGE_SIZE),
            'queryPage' => $queryPage,
            'pagePage' => $pagePage,
            'hasMoreQueries' => count($queries) > self::PERFORMANCE_PAGE_SIZE,
            'hasMorePages' => count($landingPages) > self::PERFORMANCE_PAGE_SIZE,
            'pageSize' => self::PERFORMANCE_PAGE_SIZE,
            'period' => ['start' => now()->subDays(29), 'end' => now()->subDay()],
        ]);
    }

    public function storeProperty(StoreSearchConsolePropertyRequest $request, Website $website): RedirectResponse
    {
        $connection = $website->searchConsoleConnection()->firstOrFail();
        $property = collect($this->searchConsole->sites($connection))->firstWhere('siteUrl', $request->validated('property_url'));
        abort_unless($property, 422, 'That Search Console property is not available to this Google account.');
        $connection->update(['property_url' => $property['siteUrl'], 'permission_level' => $property['permissionLevel'] ?? null]);

        return Redirect::route('admin.websites.show', $website)->with('status', 'Google Search Console connected.');
    }

    public function destroy(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $website->searchConsoleConnection()->delete();
        $website->contentPlan()->update(['enabled' => false]);

        return Redirect::route('admin.websites.show', $website)->with('status', 'Google Search Console disconnected and content generation paused.');
    }

    protected function authorizeWebsite(Request $request, Website $website): void
    {
        abort_unless($request->user()?->isAdmin() || $website->user_id === $request->user()?->id, 403);
    }
}
