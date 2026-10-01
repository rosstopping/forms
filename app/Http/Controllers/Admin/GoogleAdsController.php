<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoogleAdsCampaignDraftRequest;
use App\Models\GoogleAdsCampaignDraft;
use App\Models\Website;
use App\Services\GoogleAdsCampaignCreator;
use App\Services\GoogleAdsClient;
use App\Services\GoogleAdsOAuthClient;
use App\Services\GoogleAdsOpportunityFinder;
use App\Support\MembershipPlan;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class GoogleAdsController extends Controller
{
    public function __construct(protected GoogleAdsOAuthClient $oauth, protected GoogleAdsClient $client, protected GoogleAdsOpportunityFinder $opportunities, protected GoogleAdsCampaignCreator $campaigns) {}

    public function index(Request $request, Website $website): View
    {
        $this->authorizeWebsite($request, $website);
        $connection = $website->googleAdsConnection;
        $searchGaps = $this->opportunities->searchGaps($website);
        $drafts = $website->googleAdsCampaignDrafts()->latest()->limit(10)->get();
        $customerIds = [];
        $connectionError = null;
        $conversionActions = [];
        $conversionError = null;
        if ($connection) {
            try {
                $customerIds = $this->client->accessibleCustomerIds($connection);
            } catch (RequestException|RuntimeException) {
                $connectionError = 'Google Ads could not list accounts. Check that the Google Cloud project has Google Ads API production access, then reconnect if needed.';
            }
            if ($connection->customer_id) {
                try {
                    $conversionActions = $this->client->conversionActions($connection);
                } catch (RequestException|RuntimeException) {
                    $conversionError = 'Could not check conversion actions in this account.';
                }
            }
        }

        return view('admin.websites.google-ads', compact('website', 'connection', 'customerIds', 'connectionError', 'conversionActions', 'conversionError', 'searchGaps', 'drafts'));
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
            return back()->with('error', $exception->getMessage());
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
        } catch (RequestException|RuntimeException) {
            return Redirect::route('admin.google-ads.index', $website)->with('error', 'Google Ads could not complete the connection. Check your Google OAuth settings and try again.');
        }

        return Redirect::route('admin.google-ads.index', $website)->with('status', 'Google Ads authorized. Choose the account for this website.');
    }

    public function selectAccount(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $data = $request->validate([
            'customer_id' => ['required', 'regex:/^\d{3}-?\d{3}-?\d{4}$/'],
            'login_customer_id' => ['nullable', 'regex:/^\d{3}-?\d{3}-?\d{4}$/'],
        ]);
        $connection = $website->googleAdsConnection()->firstOrFail();
        $customerId = str_replace('-', '', $data['customer_id']);
        $loginCustomerId = filled($data['login_customer_id'] ?? null) ? str_replace('-', '', $data['login_customer_id']) : null;

        try {
            $customer = $this->client->customer($connection, $customerId, $loginCustomerId);
        } catch (RequestException|RuntimeException) {
            return back()->withInput()->with('error', 'Google Ads could not verify that account. Check the customer ID, manager ID and API access.');
        }
        $connection->update([
            'customer_id' => $customer['id'],
            'login_customer_id' => $loginCustomerId,
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

            return Redirect::route('admin.google-ads.index', $website)->with('status', 'This campaign request has already been processed. Check its status below.');
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

        try {
            $this->campaigns->create($draft);
        } catch (RequestException|RuntimeException $exception) {
            $draft->refresh();
            $message = $draft->status === GoogleAdsCampaignDraft::STATUS_UNCERTAIN
                ? 'Google Ads may have created the paused campaign. Check the Ads account before trying again.'
                : 'Google Ads rejected the campaign. Check the account, budget, location, keywords and ad copy.';

            return Redirect::route('admin.google-ads.index', $website)->with('error', $message);
        }

        return Redirect::route('admin.google-ads.index', $website)->with('status', 'Paused Search campaign created in Google Ads. Review conversion tracking before enabling it.');
    }

    protected function authorizeWebsite(Request $request, Website $website): void
    {
        abort_unless($website->isManageableBy($request->user()), 403);
    }
}
