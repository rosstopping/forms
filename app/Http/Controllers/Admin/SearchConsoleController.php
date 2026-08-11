<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSearchConsolePropertyRequest;
use App\Models\Website;
use App\Services\GoogleOAuthClient;
use App\Services\SearchConsoleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Throwable;

class SearchConsoleController extends Controller
{
    public function __construct(protected GoogleOAuthClient $oauth, protected SearchConsoleClient $searchConsole) {}

    public function connect(Request $request, Website $website): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $state = Crypt::encryptString(json_encode(['website_id' => $website->id, 'user_id' => $request->user()->id], JSON_THROW_ON_ERROR));

        return Redirect::away($this->oauth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);

        try {
            $state = json_decode(Crypt::decryptString($data['state']), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(403, 'The Google authorization request is invalid.');
        }

        abort_unless(data_get($state, 'user_id') === $request->user()->id, 403);
        $website = Website::query()->findOrFail(data_get($state, 'website_id'));
        $this->oauth->authorize($website, $request->user(), $data['code']);

        return Redirect::route('admin.search-console.property', $website);
    }

    public function property(Request $request, Website $website): View
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $connection = $website->searchConsoleConnection()->firstOrFail();
        $properties = $this->searchConsole->sites($connection);

        return view('admin.websites.search-console-property', compact('website', 'properties'));
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
        abort_unless($request->user()?->isAdmin(), 403);
        $website->searchConsoleConnection()->delete();
        $website->contentPlan()->update(['enabled' => false]);

        return Redirect::route('admin.websites.show', $website)->with('status', 'Google Search Console disconnected and content generation paused.');
    }
}
