<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateGoogleAdsSuggestionsRequest;
use App\Http\Requests\SaveGoogleAdsCampaignFormDraftRequest;
use App\Http\Requests\StoreGoogleAdsCampaignDraftRequest;
use App\Http\Requests\StoreGoogleAdsTrackingRequest;
use App\Jobs\CreateGoogleAdsCampaign;
use App\Jobs\StartGoogleAdsTrackingRequest;
use App\Models\GoogleAdsCampaignDraft;
use App\Models\GoogleAdsTrackingRequest;
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
use Illuminate\Support\Facades\Cache;
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
        $formDraft = $connection?->campaign_form_draft ?? [];
        $oauthConfigured = filled(config('services.google_ads.client_id')) && filled(config('services.google_ads.client_secret'));
        $requestedTab = $request->query('tab');
        $tab = in_array($requestedTab, ['settings', 'campaigns', 'create'], true) ? $requestedTab : null;
        if (! $connection?->customer_id) {
            $tab = 'settings';
        }

        $campaigns = [];
        $campaignPerformance = [];
        $campaignPerformanceAvailable = false;
        $campaignError = null;
        if ($connection?->customer_id && ($tab === null || $tab === 'campaigns')) {
            try {
                $campaigns = $this->client->campaigns($connection);
                if ($campaigns !== []) {
                    try {
                        $campaignPerformance = $this->client->campaignPerformance($connection);
                        $campaignPerformanceAvailable = true;
                    } catch (ConnectionException|RequestException|RuntimeException $exception) {
                        report($exception);
                    }
                }
            } catch (ConnectionException|RequestException|RuntimeException) {
                $campaignError = 'Could not load campaigns from this Ads account. Refresh to try again.';
            }
        }
        $tab ??= $campaignError || $campaigns !== [] ? 'campaigns' : 'create';
        $statusFilter = in_array($request->query('status'), ['enabled', 'paused'], true) ? $request->query('status') : 'all';
        $visibleCampaigns = $statusFilter === 'all'
            ? $campaigns
            : array_values(array_filter($campaigns, fn (array $campaign): bool => $campaign['status'] === strtoupper($statusFilter)));
        $campaignCounts = [
            'all' => count($campaigns),
            'enabled' => count(array_filter($campaigns, fn (array $campaign): bool => $campaign['status'] === 'ENABLED')),
            'paused' => count(array_filter($campaigns, fn (array $campaign): bool => $campaign['status'] === 'PAUSED')),
        ];
        $campaignTotals = $campaignPerformanceAvailable ? [
            'impressions' => array_sum(array_column($campaignPerformance, 'impressions')),
            'clicks' => array_sum(array_column($campaignPerformance, 'clicks')),
            'cost_micros' => array_sum(array_column($campaignPerformance, 'cost_micros')),
            'conversions' => array_sum(array_column($campaignPerformance, 'conversions')),
        ] : null;

        $searchGaps = $tab === 'create' ? $this->opportunities->searchGaps($website) : collect();
        $drafts = $tab === 'campaigns' ? $website->googleAdsCampaignDrafts()->latest()->limit(10)->get() : collect();
        $availableAccounts = [];
        $unavailableAccountCount = 0;
        $connectionError = null;
        $conversionActions = [];
        $conversionError = null;
        $trackingRequests = $tab === 'settings' ? GoogleAdsTrackingRequest::query()->where('website_id', $website->id)->latest()->limit(10)->get() : collect();
        $canPrepareTracking = $tab === 'settings' && $website->repository !== null && $request->user()->githubAuthorization !== null && $website->primaryDomain()?->isVerified();
        if ($connection && $tab === 'settings') {
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

        return view('admin.websites.google-ads', compact('website', 'connection', 'formDraft', 'oauthConfigured', 'tab', 'campaigns', 'visibleCampaigns', 'statusFilter', 'campaignCounts', 'campaignTotals', 'campaignError', 'availableAccounts', 'unavailableAccountCount', 'connectionError', 'conversionActions', 'conversionError', 'trackingRequests', 'canPrepareTracking', 'searchGaps', 'drafts'));
    }

    public function prepareTracking(StoreGoogleAdsTrackingRequest $request, Website $website): RedirectResponse
    {
        $connection = $website->googleAdsConnection;
        $repository = $website->repository;
        $authorization = $request->user()->githubAuthorization;
        if (! $connection?->customer_id || ! $repository || ! $authorization || ! $website->primaryDomain()?->isVerified()) {
            return back()->with('error', 'Connect an Ads account, a verified website domain, and an authorized GitHub repository first.');
        }

        $data = $request->validated();
        try {
            $action = $this->client->websiteConversionAction($connection, $data['conversion_action_id']);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'Could not check the conversion action in Google Ads. Try again shortly.');
        }
        if (! $action) {
            return back()->with('error', 'Select an enabled website-tag conversion with an available event snippet.');
        }

        return Cache::lock('ads-tracking-'.$website->id.'-'.$action['id'], 10)->block(5, function () use ($website, $connection, $repository, $request, $data, $action): RedirectResponse {
            if (GoogleAdsTrackingRequest::query()->where('website_id', $website->id)
                ->where('customer_id', $connection->customer_id)
                ->where('conversion_action_id', $action['id'])
                ->whereIn('status', [GoogleAdsTrackingRequest::STATUS_QUEUED, GoogleAdsTrackingRequest::STATUS_RUNNING, GoogleAdsTrackingRequest::STATUS_PULL_REQUEST_OPEN, GoogleAdsTrackingRequest::STATUS_UNCERTAIN])
                ->exists()) {
                return back()->with('error', 'A tracking request for this conversion already exists. Review it below before starting another.');
            }

            $trackingRequest = GoogleAdsTrackingRequest::query()->create([
                'website_id' => $website->id,
                'website_repository_id' => $repository->id,
                'requested_by' => $request->user()->id,
                'customer_id' => $connection->customer_id,
                'conversion_action_id' => $action['id'],
                'conversion_action_name' => $action['name'],
                'send_to' => $action['send_to'],
                'lead_success_description' => trim($data['lead_success_description']),
                'status' => GoogleAdsTrackingRequest::STATUS_QUEUED,
            ]);
            StartGoogleAdsTrackingRequest::dispatch($trackingRequest);

            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings'])
                ->with('status', 'Tracking implementation queued. Review the Copilot pull request before testing it on the live site.');
        });
    }

    public function showCampaign(Request $request, Website $website, string $campaignId): View|RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless(preg_match('/^[1-9]\d*$/', $campaignId) === 1, 404);
        $connection = $website->googleAdsConnection;
        abort_unless($connection?->customer_id, 404);

        try {
            $campaign = $this->client->campaign($connection, $campaignId);
            abort_unless($campaign && $campaign['status'] !== 'REMOVED', 404);
            $ads = $this->client->responsiveSearchAds($connection, $campaignId);
            $keywords = $this->client->campaignKeywords($connection, $campaignId);
            $proximities = $this->client->campaignProximities($connection, $campaignId);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
                ->with('error', 'Could not load this campaign from Google Ads. Try again shortly.');
        }

        $performance = null;
        try {
            $performance = $this->client->campaignPerformance($connection)[$campaignId] ?? null;
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);
        }

        return view('admin.websites.google-ads-campaign', compact('website', 'connection', 'campaign', 'ads', 'keywords', 'proximities', 'performance'));
    }

    public function updateCampaignName(Request $request, Website $website, string $campaignId): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless(preg_match('/^[1-9]\d*$/', $campaignId) === 1, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'original_name' => ['required', 'string', 'max:120']]);
        $connection = $website->googleAdsConnection;
        abort_unless($connection?->customer_id, 404);

        try {
            $campaign = $this->client->campaign($connection, $campaignId);
            abort_unless($campaign && $campaign['status'] !== 'REMOVED', 404);
            if (! hash_equals($campaign['name'], $data['original_name'])) {
                return back()->with('error', 'The campaign name changed in Google Ads. Refresh before saving.');
            }
            if ($campaign['name'] !== $data['name']) {
                $this->client->updateCampaignName($connection, $campaignId, $data['name']);
            }
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'Google Ads did not confirm the name change. Refresh before trying again.');
        }

        return back()->with('status', 'Campaign name saved in Google Ads.');
    }

    public function updateCampaignBudget(Request $request, Website $website, string $campaignId): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless(preg_match('/^[1-9]\d*$/', $campaignId) === 1, 404);
        $data = $request->validate(['daily_budget' => ['required', 'numeric', 'min:1', 'max:1000'], 'original_budget_micros' => ['required', 'integer', 'min:0']]);
        $connection = $website->googleAdsConnection;
        abort_unless($connection?->customer_id, 404);

        try {
            $campaign = $this->client->campaign($connection, $campaignId);
            abort_unless($campaign && $campaign['status'] !== 'REMOVED', 404);
            if ($campaign['budget_shared'] || $campaign['budget_period'] !== 'DAILY' || $campaign['budget_resource_name'] === '') {
                return back()->with('error', 'This budget cannot be edited here. Open it in Google Ads.');
            }
            if ($campaign['daily_budget_micros'] !== (int) $data['original_budget_micros']) {
                return back()->with('error', 'The budget changed in Google Ads. Refresh before saving.');
            }
            $amountMicros = (int) round((float) $data['daily_budget'] * 1000000);
            if ($amountMicros !== $campaign['daily_budget_micros']) {
                $this->client->updateCampaignBudget($connection, $campaign['budget_resource_name'], $amountMicros);
            }
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'Google Ads did not confirm the budget change. Refresh before trying again.');
        }

        return back()->with('status', 'Daily budget saved in Google Ads.');
    }

    public function updateAdCopy(Request $request, Website $website, string $campaignId, string $adId): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless(preg_match('/^[1-9]\d*$/', $campaignId) === 1 && preg_match('/^[1-9]\d*$/', $adId) === 1, 404);
        $data = $request->validate([
            'headlines' => ['required', 'array', 'between:3,15'],
            'headlines.*' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'descriptions' => ['required', 'array', 'between:2,4'],
            'descriptions.*' => ['required', 'string', 'max:90', 'distinct:ignore_case'],
            'copy_signature' => ['required', 'string', 'size:64'],
        ]);
        $connection = $website->googleAdsConnection;
        abort_unless($connection?->customer_id, 404);

        try {
            $campaign = $this->client->campaign($connection, $campaignId);
            abort_unless($campaign && $campaign['status'] !== 'REMOVED', 404);
            $ad = collect($this->client->responsiveSearchAds($connection, $campaignId))->firstWhere('id', $adId);
            abort_unless($ad, 404);
            if (! hash_equals(hash('sha256', json_encode([$ad['headlines'], $ad['descriptions']])), $data['copy_signature'])) {
                return back()->with('error', 'This ad changed in Google Ads. Refresh before saving.');
            }
            if (count($data['headlines']) !== count($ad['headlines']) || count($data['descriptions']) !== count($ad['descriptions'])) {
                return back()->with('error', 'Refresh before changing the number of ad lines.');
            }
            $headlines = array_map(fn (string $text, array $asset): array => ['text' => trim($text), 'pinned_field' => $asset['pinned_field']], $data['headlines'], $ad['headlines']);
            $descriptions = array_map(fn (string $text, array $asset): array => ['text' => trim($text), 'pinned_field' => $asset['pinned_field']], $data['descriptions'], $ad['descriptions']);
            if ($headlines !== $ad['headlines'] || $descriptions !== $ad['descriptions']) {
                $this->client->updateResponsiveSearchAd($connection, $adId, $headlines, $descriptions);
            }
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return back()->with('error', 'Google Ads did not confirm the ad changes. Refresh before trying again.');
        }

        return back()->with('status', 'Ad copy saved in Google Ads.');
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
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings'])->with('error', 'Google Ads connection is not configured here. Ask an administrator to check the Google OAuth credentials.');
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
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings'])->with('error', 'Google Ads connection was cancelled.');
        }

        $data = $request->validate(['code' => ['required', 'string']]);
        try {
            $this->oauth->authorize($website, $request->user(), $data['code']);
        } catch (ConnectionException|RequestException|RuntimeException) {
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings'])->with('error', 'Google Ads could not complete the connection. Check your Google OAuth settings and try again.');
        }

        return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings'])->with('status', 'Google Ads authorized. Choose the account for this website.');
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

        return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings'])->with('status', 'Google Ads disconnected.');
    }

    public function updateCampaignStatus(Request $request, Website $website, string $campaignId): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless(preg_match('/^[1-9]\d*$/', $campaignId) === 1, 404);
        $data = $request->validate(['status' => ['required', 'in:ENABLED,PAUSED']]);
        $status = $data['status'];
        if ($status === 'ENABLED') {
            $request->validate(['tracking_confirmed' => ['accepted']]);
        }

        $connection = $website->googleAdsConnection;
        abort_unless($connection?->customer_id, 404);

        try {
            $campaign = $this->client->campaign($connection, $campaignId);
            if (! $campaign || $campaign['status'] === 'REMOVED') {
                return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
                    ->with('error', 'This campaign is no longer available in the selected Ads account.');
            }
            if ($campaign['status'] === $status) {
                return Redirect::route('admin.google-ads.live-campaigns.show', [$website, $campaignId])
                    ->with('status', 'This campaign is already '.strtolower($status).'.');
            }
            if (($status === 'ENABLED' && $campaign['status'] !== 'PAUSED')
                || ($status === 'PAUSED' && $campaign['status'] !== 'ENABLED')) {
                return Redirect::route('admin.google-ads.live-campaigns.show', [$website, $campaignId])
                    ->with('error', 'This campaign changed in Google Ads. Refresh the review page before trying again.');
            }

            $this->client->updateCampaignStatus($connection, $campaignId, $status);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return Redirect::route('admin.google-ads.live-campaigns.show', [$website, $campaignId])
                ->with('error', 'Google Ads did not confirm the status change. Refresh the review page before trying again.');
        }

        return Redirect::route('admin.google-ads.live-campaigns.show', [$website, $campaignId])
            ->with('status', $status === 'ENABLED' ? 'Campaign enabled in Google Ads.' : 'Campaign paused in Google Ads.');
    }

    public function removeCampaign(Request $request, Website $website, string $campaignId): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless(preg_match('/^[1-9]\d*$/', $campaignId) === 1, 404);
        $data = $request->validate(['confirmation' => ['required', 'string', 'max:120']]);
        $connection = $website->googleAdsConnection;
        abort_unless($connection?->customer_id, 404);

        try {
            $campaign = $this->client->campaign($connection, $campaignId);
            if (! $campaign || $campaign['status'] === 'REMOVED') {
                return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
                    ->with('error', 'This campaign is no longer available in the selected Ads account.');
            }
            if (! hash_equals($campaign['name'], $data['confirmation'])) {
                return Redirect::route('admin.google-ads.live-campaigns.show', [$website, $campaignId])
                    ->with('error', 'The campaign name did not match. Nothing was removed.');
            }

            $this->client->removeCampaign($connection, $campaignId);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return Redirect::route('admin.google-ads.live-campaigns.show', [$website, $campaignId])
                ->with('error', 'Google Ads did not confirm removal. Refresh the review page before trying again.');
        }

        return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
            ->with('status', 'Campaign removed from Google Ads.');
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

            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])->with('status', 'This campaign request is already recorded. Check its status below.');
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
            'max_cpc_micros' => (int) round((float) $data['max_cpc'] * 1000000),
            'city_name' => $data['city_name'],
            'country_code' => 'GB',
            'radius_miles' => $data['radius_miles'],
            'final_url' => $data['final_url'],
            'keywords' => $keywords,
            'headlines' => $data['headlines'],
            'descriptions' => $data['descriptions'],
        ]);

        CreateGoogleAdsCampaign::dispatch($draft->id);
        $connection->update(['campaign_form_draft' => null, 'campaign_form_draft_saved_at' => null]);

        return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])->with('status', 'Campaign queued. It will be created paused; refresh this page shortly to see the result.');
    }

    public function saveFormDraft(SaveGoogleAdsCampaignFormDraftRequest $request, Website $website): RedirectResponse
    {
        $connection = $website->googleAdsConnection()->firstOrFail();
        if (! $connection->customer_id) {
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings'])
                ->with('error', 'Choose a Google Ads account before saving a campaign draft.');
        }

        $data = $request->validated();
        $formDraft = [];
        foreach (['name', 'daily_budget', 'max_cpc', 'city_name', 'radius_miles', 'final_url', 'campaign_brief', 'keywords_text'] as $field) {
            $formDraft[$field] = (string) ($data[$field] ?? '');
        }
        foreach (['headlines' => 3, 'descriptions' => 2] as $field => $count) {
            $formDraft[$field] = array_pad(array_map(fn (mixed $value): string => (string) ($value ?? ''), array_values($data[$field] ?? [])), $count, '');
        }

        $connection->update(['campaign_form_draft' => $formDraft, 'campaign_form_draft_saved_at' => now()]);

        return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'create'])->with('status', 'Campaign draft saved. You can return and finish it later.');
    }

    public function checkCampaign(Request $request, Website $website, GoogleAdsCampaignDraft $draft): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless($draft->website_id === $website->id, 404);
        abort_unless($draft->status === GoogleAdsCampaignDraft::STATUS_UNCERTAIN, 404);
        $connection = $website->googleAdsConnection;

        if (! $connection || $connection->customer_id !== $draft->customer_id) {
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
                ->with('error', 'Select Ads account '.$draft->customer_id.' before checking this campaign request.');
        }

        try {
            $matches = $this->client->campaignsNamed($connection, $draft->name);
        } catch (ConnectionException|RequestException|RuntimeException) {
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
                ->with('error', 'Google Ads could not check this campaign right now. Its creation status is still unconfirmed.');
        }

        return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])->with('campaign_check', [
            'draft_id' => $draft->id,
            'matches' => $matches,
        ]);
    }

    public function clearUnconfirmedCampaign(Request $request, Website $website, GoogleAdsCampaignDraft $draft): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless($draft->website_id === $website->id, 404);
        abort_unless($draft->status === GoogleAdsCampaignDraft::STATUS_UNCERTAIN, 404);

        if ($draft->updated_at->isAfter(now()->subMinutes(5))) {
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
                ->with('error', 'Wait five minutes for the original campaign request to finish, then check it again.');
        }

        $connection = $website->googleAdsConnection;
        if (! $connection || $connection->customer_id !== $draft->customer_id) {
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
                ->with('error', 'Select Ads account '.$draft->customer_id.' before clearing this request.');
        }

        try {
            $matches = $this->client->campaignsNamed($connection, $draft->name);
        } catch (ConnectionException|RequestException|RuntimeException) {
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
                ->with('error', 'Google Ads could not check this campaign right now. The request was not cleared.');
        }

        if ($matches !== []) {
            return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])
                ->with('error', 'A campaign with this name now exists in Google Ads. The request was not cleared.');
        }

        $cleared = GoogleAdsCampaignDraft::query()
            ->whereKey($draft->id)
            ->where('status', GoogleAdsCampaignDraft::STATUS_UNCERTAIN)
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->update([
                'status' => GoogleAdsCampaignDraft::STATUS_FAILED,
                'error' => 'No matching campaign was found in Ads account '.$draft->customer_id.' when this request was cleared.',
            ]);

        return Redirect::route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns'])->with(
            $cleared ? 'status' : 'error',
            $cleared ? 'The unconfirmed request was cleared. You can now create a new paused campaign.' : 'This request changed while it was being checked. Refresh the page.',
        );
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
