<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateGoogleAdsSuggestionsRequest;
use App\Http\Requests\StoreGoogleAdsCampaignDraftRequest;
use App\Jobs\CreateGoogleAdsCampaign;
use App\Models\GoogleAdsCampaignDraft;
use App\Models\Website;
use App\Services\GoogleAdsClient;
use App\Services\GoogleAdsOAuthClient;
use App\Services\GoogleAdsOpportunityFinder;
use App\Services\GoogleAdsSuggestionGenerator;
use App\Support\MembershipPlan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class GoogleAdsController extends Controller
{
    public function __construct(protected GoogleAdsOAuthClient $oauth, protected GoogleAdsClient $client, protected GoogleAdsOpportunityFinder $opportunities, protected GoogleAdsSuggestionGenerator $suggestions) {}

    public function index(Request $request, Website $website): View
    {
        $this->authorizeWebsite($request, $website);
        $connection = $website->googleAdsConnection;
        $oauthConfigured = filled(config('services.google_ads.client_id')) && filled(config('services.google_ads.client_secret'));
        $searchGaps = $this->opportunities->searchGaps($website);
        $drafts = $website->googleAdsCampaignDrafts()->latest()->limit(10)->get();
        $availableAccounts = [];
        $unavailableAccountCount = 0;
        $connectionError = null;
        $conversionActions = [];
        $conversionError = null;
        if ($connection) {
            try {
                $accountList = $this->client->availableAccounts($connection);
                $availableAccounts = $accountList['accounts'];
                $unavailableAccountCount = $accountList['unavailable_count'];
            } catch (ConnectionException|RequestException|RuntimeException) {
                $connectionError = 'Google Ads could not list accounts. Check that the Google Cloud project has Google Ads API production access, then reconnect if needed.';
            }
            if ($connection->customer_id) {
                try {
                    $conversionActions = $this->client->conversionActions($connection);
                } catch (ConnectionException|RequestException|RuntimeException) {
                    $conversionError = 'Could not check conversion actions in this account.';
                }
            }
        }

        return view('admin.websites.google-ads', compact('website', 'connection', 'oauthConfigured', 'availableAccounts', 'unavailableAccountCount', 'connectionError', 'conversionActions', 'conversionError', 'searchGaps', 'drafts'));
    }

    public function connect(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $state = Str::random(40);
        $request->session()->put('google_ads_oauth_state', [
            'nonce' => $state,
            'website_id' => $website->id,
            'user_id' => $request->user()->id,
        ]);

        try {
            return Redirect::away($this->oauth->authorizationUrl($state));
        } catch (RuntimeException $exception) {
            return Redirect::route('admin.google-ads.index', $website)->with('error', 'Google Ads connection is not configured here. Ask an administrator to check the Google OAuth credentials.');
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('google_ads_oauth_state');
        abort_unless(is_array($state)
            && is_string($request->query('state'))
            && hash_equals((string) $state['nonce'], $request->query('state'))
            && $state['user_id'] === $request->user()->id, 403);
        $website = Website::query()->findOrFail($state['website_id']);
        $this->authorizeWebsite($request, $website);
        if (! $request->user()->isAdmin() && ! $website->owner?->hasMembershipFeature(MembershipPlan::FEATURE_COMPLETE)) {
            return Redirect::route('admin.billing.index')
                ->with('error', 'Google Ads is available on the Complete plan.');
        }

        if ($request->query('error')) {
            return Redirect::route('admin.google-ads.index', $website)->with('error', 'Google Ads connection was cancelled.');
        }

        $data = $request->validate(['code' => ['required', 'string']]);
        try {
            $this->oauth->authorize($website, $request->user(), $data['code']);
        } catch (ConnectionException|RequestException|RuntimeException) {
            return Redirect::route('admin.google-ads.index', $website)->with('error', 'Google Ads could not complete the connection. Check your Google OAuth settings and try again.');
        }

        return Redirect::route('admin.google-ads.index', $website)->with('status', 'Google Ads authorized. Choose the account for this website.');
    }

    public function selectAccount(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $data = $request->validate([
            'account' => ['required', 'regex:/^(?:\d{10}:)?\d{10}$/'],
        ]);
        $connection = $website->googleAdsConnection()->firstOrFail();

        try {
            $accounts = $this->client->availableAccounts($connection)['accounts'];
            $selected = collect($accounts)->first(fn (array $account): bool => ($account['login_customer_id'] ? $account['login_customer_id'].':' : '').$account['id'] === $data['account']);
            if (! $selected) {
                return back()->withInput()->withErrors(['account' => 'Choose an available client account from the list.']);
            }
            $customer = $this->client->customer($connection, $selected['id'], $selected['login_customer_id']);
        } catch (ConnectionException|RequestException|RuntimeException) {
            return back()->withInput()->with('error', 'Google Ads could not verify that account. Refresh the account list and try again.');
        }
        $connection->update([
            'customer_id' => $customer['id'],
            'login_customer_id' => $selected['login_customer_id'],
            'customer_name' => $customer['name'],
            'currency_code' => $customer['currency'],
        ]);

        return Redirect::route('admin.google-ads.index', $website)->with('status', 'Google Ads account connected to this website.');
    }

    public function destroy(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $website->googleAdsConnection()->delete();

        return Redirect::route('admin.google-ads.index', $website)->with('status', 'Google Ads disconnected.');
    }

    public function storeDraft(StoreGoogleAdsCampaignDraftRequest $request, Website $website): RedirectResponse
    {
        $connection = $website->googleAdsConnection()->firstOrFail();
        if (! $connection->customer_id || ! $connection->currency_code) {
            return back()->withInput()->with('error', 'Verify a Google Ads client account before creating a campaign.');
        }

        $data = $request->validated();
        $keywords = $request->keywords();
        $existing = GoogleAdsCampaignDraft::query()->where('request_key', $data['request_key'])->first();
        if ($existing) {
            abort_unless($existing->website_id === $website->id && $existing->created_by === $request->user()->id, 403);

            if ($existing->status === GoogleAdsCampaignDraft::STATUS_PENDING) {
                CreateGoogleAdsCampaign::dispatch($existing->id);
            }

            return Redirect::route('admin.google-ads.index', $website)->with('status', 'This campaign request is already recorded. Check its status below.');
        }

        if ($website->googleAdsCampaignDrafts()->where('name', $data['name'])
            ->whereIn('status', [GoogleAdsCampaignDraft::STATUS_PENDING, GoogleAdsCampaignDraft::STATUS_UNCERTAIN])->exists()) {
            return back()->withInput()->with('error', 'A campaign with this name is still pending or needs checking in Google Ads. Review it below before trying again.');
        }

        $draft = $website->googleAdsCampaignDrafts()->create([
            'google_ads_connection_id' => $connection->id,
            'created_by' => $request->user()->id,
            'request_key' => $data['request_key'],
            'customer_id' => $connection->customer_id,
            'name' => $data['name'],
            'daily_budget_micros' => (int) round((float) $data['daily_budget'] * 1000000),
            'city_name' => $data['city_name'],
            'country_code' => 'GB',
            'radius_miles' => $data['radius_miles'],
            'final_url' => $data['final_url'],
            'keywords' => $keywords,
            'headlines' => $data['headlines'],
            'descriptions' => $data['descriptions'],
        ]);

        CreateGoogleAdsCampaign::dispatch($draft->id);

        return Redirect::route('admin.google-ads.index', $website)->with('status', 'Campaign queued. It will be created paused; refresh this page shortly to see the result.');
    }

    public function checkCampaign(Request $request, Website $website, GoogleAdsCampaignDraft $draft): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless($draft->website_id === $website->id, 404);
        abort_unless($draft->status === GoogleAdsCampaignDraft::STATUS_UNCERTAIN, 404);
        $connection = $website->googleAdsConnection;

        if (! $connection || $connection->customer_id !== $draft->customer_id) {
            return Redirect::route('admin.google-ads.index', $website)
                ->with('error', 'Select Ads account '.$draft->customer_id.' before checking this campaign request.');
        }

        try {
            $matches = $this->client->campaignsNamed($connection, $draft->name);
        } catch (ConnectionException|RequestException|RuntimeException) {
            return Redirect::route('admin.google-ads.index', $website)
                ->with('error', 'Google Ads could not check this campaign right now. Its creation status is still unconfirmed.');
        }

        return Redirect::route('admin.google-ads.index', $website)->with('campaign_check', [
            'draft_id' => $draft->id,
            'matches' => $matches,
        ]);
    }

    public function suggest(GenerateGoogleAdsSuggestionsRequest $request, Website $website): RedirectResponse
    {
        if (! $website->googleAdsConnection?->customer_id) {
            return back()->withInput()->with('error', 'Connect a Google Ads client account before generating suggestions.');
        }

        $data = $request->validated();

        try {
            $suggestions = $this->suggestions->generate($website, $data['final_url'], $data['city_name'], $data['campaign_brief'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Could not suggest ad copy right now. Please try again or enter it manually.');
        }

        return back()->withInput(array_replace($request->except('_token'), $suggestions))
            ->with('status', 'Suggestions added. Review every search and claim before creating the paused campaign.');
    }

    protected function authorizeWebsite(Request $request, Website $website): void
    {
        abort_unless($website->isManageableBy($request->user()), 403);
    }
}
