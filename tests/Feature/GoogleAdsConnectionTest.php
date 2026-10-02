<?php

use App\Jobs\CreateGoogleAdsCampaign;
use App\Models\GoogleAdsCampaignDraft;
use App\Models\GoogleAdsConnection;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\User;
use App\Models\Website;
use App\Services\GoogleAdsCampaignCreator;
use App\Services\GoogleAdsOAuthClient;
use App\Support\MembershipPlan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'services.google_ads.client_id' => 'test-client',
        'services.google_ads.client_secret' => 'test-secret',
        'services.google_ads.api_url' => 'https://googleads.test/v25',
    ]);
    Http::preventStrayRequests();
});

test('Google Ads requests its own offline OAuth scope', function (): void {
    $url = app(GoogleAdsOAuthClient::class)->authorizationUrl('secure-state');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'scope' => 'https://www.googleapis.com/auth/adwords',
        'access_type' => 'offline',
        'state' => 'secure-state',
        'redirect_uri' => route('admin.google-ads.callback'),
    ]);
});

test('missing local OAuth credentials explain why connect returns to the workspace', function (): void {
    config(['services.google_ads.client_id' => null, 'services.google_ads.client_secret' => null]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($admin, 'owner')->create();

    $this->actingAs($admin)->get(route('admin.google-ads.connect', $website))
        ->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings']))
        ->assertSessionHas('error');
    $this->actingAs($admin)->get(route('admin.google-ads.index', $website))
        ->assertSuccessful()
        ->assertSee('GOOGLE_ADS_CLIENT_ID')
        ->assertSee('Google Ads connection is not configured here.');
});

test('a website manager can authorize Google Ads for only that website', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $otherWebsite = Website::factory()->create();
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response([
        'access_token' => 'access-secret',
        'refresh_token' => 'refresh-secret',
        'expires_in' => 3600,
    ])]);

    $response = $this->actingAs($owner)->get(route('admin.google-ads.connect', $website));
    $response->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);

    $this->actingAs($owner)->get(route('admin.google-ads.callback', [
        'code' => 'authorization-code',
        'state' => $parameters['state'],
    ]))->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings']));

    $connection = $website->googleAdsConnection()->firstOrFail();
    expect($connection->access_token)->toBe('access-secret')
        ->and($connection->refresh_token)->toBe('refresh-secret')
        ->and(GoogleAdsConnection::query()->count())->toBe(1);
    expect($connection->getRawOriginal('access_token'))->not->toContain('access-secret');
    $this->actingAs($owner)->get(route('admin.google-ads.index', $otherWebsite))->assertForbidden();
});

test('OAuth callback rejects an invalid or replayed state', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $this->actingAs($owner)->get(route('admin.google-ads.connect', $website))->assertRedirect();

    $this->actingAs($owner)->get(route('admin.google-ads.callback', [
        'code' => 'authorization-code',
        'state' => 'incorrect-state',
    ]))->assertForbidden();
    $this->actingAs($owner)->get(route('admin.google-ads.callback', [
        'code' => 'authorization-code',
        'state' => 'incorrect-state',
    ]))->assertForbidden();
    expect(GoogleAdsConnection::query()->count())->toBe(0);
});

test('account selection verifies access and stores the account currency', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create();
    Http::fake([
        'https://googleads.test/v25/customers:listAccessibleCustomers' => Http::response(['resourceNames' => ['customers/1234567890']]),
        'https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [['customer' => [
            'id' => '1234567890', 'descriptiveName' => 'Sitewell', 'currencyCode' => 'GBP', 'manager' => false,
        ]]]]]),
    ]);

    $this->actingAs($owner)->post(route('admin.google-ads.account', $website), [
        'account' => '1234567890',
    ])->assertRedirect(route('admin.google-ads.index', $website));

    expect($website->googleAdsConnection->fresh())
        ->customer_id->toBe('1234567890')
        ->currency_code->toBe('GBP');
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-access-token'));
});

test('account picker shows names and manager clients without listing managers as selectable accounts', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create();
    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), 'customers:listAccessibleCustomers')) {
            return Http::response(['resourceNames' => ['customers/1111111111', 'customers/2222222222']]);
        }
        if (str_contains($request->url(), '/customers/1111111111/')) {
            if (str_contains((string) $request['query'], 'FROM customer_client')) {
                return Http::response([['results' => [
                    ['customerClient' => ['id' => '3333333333', 'descriptiveName' => 'Ross Plumbing', 'currencyCode' => 'GBP', 'manager' => false, 'level' => 2, 'status' => 'ENABLED', 'hidden' => false]],
                    ['customerClient' => ['id' => '4444444444', 'descriptiveName' => 'Sub-manager', 'manager' => true, 'level' => 1, 'status' => 'ENABLED', 'hidden' => false]],
                ]]]);
            }

            return Http::response([['results' => [['customer' => ['id' => '1111111111', 'descriptiveName' => 'Agency manager', 'currencyCode' => 'GBP', 'manager' => true]]]]]);
        }
        if (str_contains($request->url(), '/customers/2222222222/')) {
            return Http::response([['results' => [['customer' => ['id' => '2222222222', 'descriptiveName' => 'Sitewell Ads', 'currencyCode' => 'GBP', 'manager' => false]]]]]);
        }
        if (str_contains($request->url(), '/customers/3333333333/')) {
            return Http::response([['results' => [['customer' => ['id' => '3333333333', 'descriptiveName' => 'Ross Plumbing', 'currencyCode' => 'GBP', 'manager' => false]]]]]);
        }

        return Http::response([], 404);
    });

    $this->actingAs($owner)->get(route('admin.google-ads.index', $website))
        ->assertSuccessful()
        ->assertSee('Ross Plumbing')
        ->assertSee('Sitewell Ads')
        ->assertSee('value="1111111111:3333333333"', false)
        ->assertSee('value="2222222222"', false)
        ->assertDontSee('value="1111111111"', false)
        ->assertDontSee('value="1111111111:4444444444"', false)
        ->assertDontSee('Ads customer ID');

    $this->actingAs($owner)->post(route('admin.google-ads.account', $website), ['account' => '1111111111:3333333333'])
        ->assertRedirect(route('admin.google-ads.index', $website));
    expect($website->googleAdsConnection->fresh())
        ->customer_id->toBe('3333333333')
        ->login_customer_id->toBe('1111111111')
        ->customer_name->toBe('Ross Plumbing');
    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/customers/3333333333/')
        && $request->hasHeader('login-customer-id', '1111111111'));
});

test('account picker rejects a customer not in the available account list', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create();
    Http::fake([
        'https://googleads.test/v25/customers:listAccessibleCustomers' => Http::response(['resourceNames' => ['customers/1234567890']]),
        'https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [['customer' => [
            'id' => '1234567890', 'descriptiveName' => 'Sitewell', 'currencyCode' => 'GBP', 'manager' => false,
        ]]]]]),
    ]);

    $this->actingAs($owner)->post(route('admin.google-ads.account', $website), ['account' => '9999999999'])
        ->assertSessionHasErrors('account');

    expect($website->googleAdsConnection->fresh()->customer_id)->toBeNull();
    Http::assertSentCount(2);
});

test('unverified account selection does not replace the existing account', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1111111111']);
    Http::fake(['https://googleads.test/*' => Http::response(['error' => ['message' => 'No access']], 403)]);

    $this->actingAs($owner)->post(route('admin.google-ads.account', $website), [
        'account' => '1234567890',
    ])->assertSessionHas('error');

    expect($website->googleAdsConnection->fresh()->customer_id)->toBe('1111111111');
});

test('a viewer cannot connect or disconnect Google Ads', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $viewer = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);

    $this->actingAs($viewer)->get(route('admin.google-ads.connect', $website))->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.google-ads.destroy', $website))->assertForbidden();
});

test('search gaps use only recent queries from the websites connected property', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    SearchConsoleConnection::factory()->for($website)->create(['property_url' => 'sc-domain:example.com']);
    $eligible = SearchConsoleMetric::factory()->for($website)->create([
        'property_url' => 'sc-domain:example.com',
        'property_hash' => hash('sha256', 'sc-domain:example.com'),
        'month' => today()->startOfMonth(),
        'dimension_key' => hash('sha256', 'query:seo doncaster'),
        'query' => 'seo doncaster',
        'impressions' => 100,
        'clicks' => 2,
        'ctr' => 0.02,
        'position' => 14,
    ]);
    SearchConsoleMetric::factory()->for($website)->create([
        'property_url' => 'sc-domain:other.com',
        'property_hash' => hash('sha256', 'sc-domain:other.com'),
        'month' => today()->startOfMonth(),
        'dimension_key' => hash('sha256', 'query:private other website'),
        'query' => 'private other website',
        'impressions' => 100,
        'clicks' => 0,
        'ctr' => 0,
        'position' => 20,
    ]);

    $this->actingAs($owner)->get(route('admin.google-ads.index', ['website' => $website, 'tab' => 'create']))
        ->assertSuccessful()
        ->assertSee($eligible->query)
        ->assertDontSee('private other website');
});

test('a reviewed campaign is validated then created paused exactly once', function (): void {
    Queue::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $connection = GoogleAdsConnection::factory()->for($website)->create([
        'customer_id' => '1234567890', 'currency_code' => 'GBP',
        'campaign_form_draft' => ['name' => 'Saved working copy'],
        'campaign_form_draft_saved_at' => now(),
    ]);
    Http::fakeSequence()->push([])->push(['mutateOperationResponses' => [[], ['campaignResult' => ['resourceName' => 'customers/1234567890/campaigns/987']]]]);

    $data = [
        'request_key' => (string) Str::uuid(),
        'name' => 'Sitewell | Doncaster',
        'daily_budget' => '20',
        'city_name' => 'Doncaster',
        'radius_miles' => 20,
        'final_url' => 'https://example.com/get-started',
        'keywords_text' => "seo agency doncaster\nlocal seo services",
        'headlines' => ['SEO for Doncaster', 'Get Your Free Audit', 'Talk to Sitewell'],
        'descriptions' => ['Find out where your website could win more searches.', 'Get a free search audit for your business.'],
    ];
    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), $data)
        ->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns']));
    $draft = GoogleAdsCampaignDraft::query()->firstOrFail();
    expect($draft->status)->toBe(GoogleAdsCampaignDraft::STATUS_PENDING);
    expect($connection->fresh()->campaign_form_draft)->toBeNull();
    Queue::assertPushed(CreateGoogleAdsCampaign::class, fn (CreateGoogleAdsCampaign $job): bool => $job->draftId === $draft->id);
    Http::assertNothingSent();

    (new CreateGoogleAdsCampaign($draft->id))->handle(app(GoogleAdsCampaignCreator::class));
    $draft->refresh();
    expect($draft->status)->toBe(GoogleAdsCampaignDraft::STATUS_CREATED)
        ->and($draft->daily_budget_micros)->toBe(20000000)
        ->and($draft->campaign_resource_name)->toBe('customers/1234567890/campaigns/987');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request['validateOnly'] === true
        && $request['mutateOperations'][1]['campaignOperation']['create']['status'] === 'PAUSED'
        && $request['mutateOperations'][2]['campaignCriterionOperation']['create']['proximity']['radius'] === 20);

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), $data)
        ->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns']));
    expect(GoogleAdsCampaignDraft::query()->count())->toBe(1);
    Http::assertSentCount(2);
});

test('a partial Google Ads form draft saves and restores AI copy without creating a campaign', function (): void {
    Queue::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    Http::fake([
        'https://googleads.test/v25/customers:listAccessibleCustomers' => Http::response(['resourceNames' => []]),
        'https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => []]]),
    ]);

    $this->actingAs($owner)->post(route('admin.google-ads.campaign-draft.save', $website), [
        'name' => 'Local search campaign',
        'daily_budget' => '20',
        'city_name' => 'Doncaster',
        'radius_miles' => '20',
        'final_url' => 'https://example.com/',
        'campaign_brief' => 'Help nearby businesses',
        'keywords_text' => "local seo agency\nseo doncaster",
        'headlines' => ['AI headline one', 'AI headline two', ''],
        'descriptions' => ['AI description one', ''],
    ])->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'create']))
        ->assertSessionHas('status', 'Campaign draft saved. You can return and finish it later.');

    expect($connection->fresh()->campaign_form_draft)->toMatchArray([
        'name' => 'Local search campaign',
        'keywords_text' => "local seo agency\nseo doncaster",
        'headlines' => ['AI headline one', 'AI headline two', ''],
        'descriptions' => ['AI description one', ''],
    ]);
    expect($connection->fresh()->campaign_form_draft_saved_at)->not->toBeNull();
    expect(GoogleAdsCampaignDraft::query()->count())->toBe(0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();

    $this->actingAs($owner)->get(route('admin.google-ads.index', $website))
        ->assertSuccessful()
        ->assertSee('AI headline one')
        ->assertSee('AI description one')
        ->assertSee('local seo agency')
        ->assertSee('Save draft');
});

test('campaigns open first when the selected Ads account has campaigns', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'customer_name' => 'Ross Ads', 'currency_code' => 'GBP']);
    Http::fake(['https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [
        ['campaign' => ['id' => '987654321', 'resourceName' => 'customers/1234567890/campaigns/987654321', 'name' => 'Local search', 'status' => 'PAUSED', 'advertisingChannelType' => 'SEARCH'], 'campaignBudget' => ['amountMicros' => '20000000']],
    ]]])]);

    $this->actingAs($owner)->get(route('admin.google-ads.index', $website))
        ->assertSuccessful()
        ->assertSee('aria-current="page" >Campaigns', false)
        ->assertSee('Local search')
        ->assertSee('https://ads.google.com/aw/overview?campaignId=987654321', false)
        ->assertSee('Enable campaign')
        ->assertDontSee('Search opportunities');
    Http::assertSentCount(1);
});

test('the create tab opens first when the selected Ads account has no campaigns', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    Http::fake(['*' => Http::response([['results' => []]])]);

    $this->actingAs($owner)->get(route('admin.google-ads.index', $website))
        ->assertSuccessful()
        ->assertSee('aria-current="page" >Create campaign', false)
        ->assertSee('Save draft')
        ->assertDontSee('Conversion tracking');
    Http::assertSentCount(1);
});

test('enabling a campaign requires tracking confirmation and updates only the selected Ads campaign', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'login_customer_id' => '1111111111']);
    Http::fake([
        'https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [
            ['campaign' => ['id' => '987654321', 'name' => 'Local search', 'status' => 'PAUSED']],
        ]]]),
        'https://googleads.test/v25/customers/1234567890/campaigns:mutate' => Http::response(['results' => [['resourceName' => 'customers/1234567890/campaigns/987654321']]]),
    ]);
    $url = route('admin.google-ads.live-campaigns.status', [$website, '987654321']);

    $this->actingAs($owner)->patch($url, ['status' => 'ENABLED'])->assertSessionHasErrors('tracking_confirmed');
    Http::assertNothingSent();
    $this->actingAs($owner)->patch($url, ['status' => 'ENABLED', 'tracking_confirmed' => '1'])
        ->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns']))
        ->assertSessionHas('status', 'Campaign enabled in Google Ads.');

    Http::assertSentCount(2);
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/campaigns:mutate')
        && $request->hasHeader('login-customer-id', '1111111111')
        && $request['operations'][0]['updateMask'] === 'status'
        && $request['operations'][0]['update']['resourceName'] === 'customers/1234567890/campaigns/987654321'
        && $request['operations'][0]['update']['status'] === 'ENABLED');
});

test('pausing and removing campaigns require current account data and an exact removal confirmation', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    Http::fake([
        'https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [
            ['campaign' => ['id' => '987654321', 'name' => 'Local search', 'status' => 'ENABLED']],
        ]]]),
        'https://googleads.test/v25/customers/1234567890/campaigns:mutate' => Http::response(['results' => [['resourceName' => 'customers/1234567890/campaigns/987654321']]]),
    ]);

    $this->actingAs($owner)->patch(route('admin.google-ads.live-campaigns.status', [$website, '987654321']), ['status' => 'PAUSED'])
        ->assertSessionHas('status', 'Campaign paused in Google Ads.');
    $this->actingAs($owner)->delete(route('admin.google-ads.live-campaigns.destroy', [$website, '987654321']), ['confirmation' => 'Wrong name'])
        ->assertSessionHas('error', 'The campaign name did not match. Nothing was removed.');
    $this->actingAs($owner)->delete(route('admin.google-ads.live-campaigns.destroy', [$website, '987654321']), ['confirmation' => 'Local search'])
        ->assertSessionHas('status', 'Campaign removed from Google Ads.');

    Http::assertSentCount(5);
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/campaigns:mutate')
        && ($request['operations'][0]['update']['status'] ?? null) === 'PAUSED');
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/campaigns:mutate')
        && ($request['operations'][0]['remove'] ?? null) === 'customers/1234567890/campaigns/987654321');
});

test('campaign controls cannot mutate a campaign absent from the selected Ads account', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    Http::fake(['*' => Http::response([['results' => []]])]);

    $this->actingAs($owner)->patch(route('admin.google-ads.live-campaigns.status', [$website, '987654321']), ['status' => 'PAUSED'])
        ->assertSessionHas('error', 'This campaign is no longer available in the selected Ads account.');
    $this->actingAs($owner)->delete(route('admin.google-ads.live-campaigns.destroy', [$website, '987654321']), ['confirmation' => 'Local search'])
        ->assertSessionHas('error', 'This campaign is no longer available in the selected Ads account.');

    Http::assertSentCount(2);
    Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/campaigns:mutate'));
});

test('campaign controls reject stale status and users without website management access', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $viewer = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    Http::fake(['*' => Http::response([['results' => [
        ['campaign' => ['id' => '987654321', 'name' => 'Local search', 'status' => 'UNKNOWN']],
    ]]])]);
    $statusUrl = route('admin.google-ads.live-campaigns.status', [$website, '987654321']);
    $removeUrl = route('admin.google-ads.live-campaigns.destroy', [$website, '987654321']);

    $this->actingAs($viewer)->patch($statusUrl, ['status' => 'ENABLED', 'tracking_confirmed' => '1'])->assertForbidden();
    $this->actingAs($viewer)->delete($removeUrl, ['confirmation' => 'Local search'])->assertForbidden();
    $this->actingAs($owner)->patch($statusUrl, ['status' => 'ENABLED', 'tracking_confirmed' => '1'])
        ->assertSessionHas('error', 'This campaign changed in Google Ads. Refresh the list before trying again.');

    Http::assertSentCount(1);
    Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/campaigns:mutate'));
});

test('saving a campaign draft requires permission to manage the website and an Ads account', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $viewer = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => null]);

    $this->actingAs($viewer)->post(route('admin.google-ads.campaign-draft.save', $website), ['name' => 'Private'])->assertForbidden();
    $this->actingAs($owner)->post(route('admin.google-ads.campaign-draft.save', $website), ['name' => 'Private'])
        ->assertSessionHas('error', 'Choose a Google Ads account before saving a campaign draft.');

    expect($connection->fresh()->campaign_form_draft)->toBeNull();
});

test('a campaign cannot send visitors to an unverified or different website', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), [
        'request_key' => (string) Str::uuid(),
        'name' => 'Wrong domain', 'daily_budget' => 20, 'city_name' => 'Doncaster', 'radius_miles' => 20,
        'final_url' => 'https://another.example.com/', 'keywords_text' => 'local seo services',
        'headlines' => ['Headline one', 'Headline two', 'Headline three'],
        'descriptions' => ['A useful first description.', 'A useful second description.'],
    ])->assertSessionHasErrors('final_url');

    expect(GoogleAdsCampaignDraft::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('an uncertain Ads response cannot trigger a second campaign mutation', function (): void {
    Queue::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    Http::fakeSequence()->push([])->push(['error' => ['message' => 'timeout']], 500);

    $data = [
        'request_key' => (string) Str::uuid(),
        'name' => 'Example search', 'daily_budget' => 20, 'city_name' => 'Doncaster', 'radius_miles' => 20,
        'final_url' => 'https://example.com/', 'keywords_text' => 'local seo services',
        'headlines' => ['Headline one', 'Headline two', 'Headline three'],
        'descriptions' => ['A useful first description.', 'A useful second description.'],
    ];
    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), $data)
        ->assertSessionHas('status');
    $draft = GoogleAdsCampaignDraft::query()->firstOrFail();
    expect(fn (): mixed => (new CreateGoogleAdsCampaign($draft->id))->handle(app(GoogleAdsCampaignCreator::class)))
        ->toThrow(RequestException::class);
    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_UNCERTAIN);
    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), $data)
        ->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns']));
    Http::assertSentCount(2);
});

test('a definite Google Ads rejection is marked failed so its reason is visible', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    $draft = GoogleAdsCampaignDraft::factory()->for($website)->for($connection, 'connection')->create();
    Http::fakeSequence()->push([])->push(['error' => ['message' => 'Invalid campaign settings']], 400);

    expect(fn (): mixed => (new CreateGoogleAdsCampaign($draft->id))->handle(app(GoogleAdsCampaignCreator::class)))
        ->toThrow(RequestException::class);
    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_FAILED)
        ->and($draft->fresh()->error)->toBe('Invalid campaign settings');
    Http::assertSentCount(2);
});

test('an uncertain campaign can be checked without creating another campaign', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    $draft = GoogleAdsCampaignDraft::factory()->for($website)->for($connection, 'connection')->create([
        'name' => "Ross's search",
        'status' => GoogleAdsCampaignDraft::STATUS_UNCERTAIN,
    ]);
    Http::fake(['https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [
        ['campaign' => ['id' => '987654321', 'name' => "Ross's search", 'status' => 'PAUSED']],
    ]]])]);

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.check', [$website, $draft]))
        ->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns']))
        ->assertSessionHas('campaign_check.matches.0.id', '987654321');

    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_UNCERTAIN);
    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request['query'], "campaign.name = 'Ross\\'s search'")
        && ! str_contains($request->url(), 'googleAds:mutate'));
});

test('a no-match campaign check leaves an uncertain request unchanged', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    $draft = GoogleAdsCampaignDraft::factory()->for($website)->for($connection, 'connection')->create(['status' => GoogleAdsCampaignDraft::STATUS_UNCERTAIN]);
    Http::fake(['*' => Http::response([['results' => []]])]);

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.check', [$website, $draft]))
        ->assertSessionHas('campaign_check.matches', []);

    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_UNCERTAIN);
    Http::assertSentCount(1);
});

test('campaign checks cannot read another website or a different Ads account', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $otherWebsite = Website::factory()->for($owner, 'owner')->create();
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    $draft = GoogleAdsCampaignDraft::factory()->for($website)->for($connection, 'connection')->create(['status' => GoogleAdsCampaignDraft::STATUS_UNCERTAIN]);
    Http::fake();

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.check', [$otherWebsite, $draft]))->assertNotFound();
    $connection->update(['customer_id' => '9999999999']);
    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.check', [$website, $draft]))
        ->assertSessionHas('error', 'Select Ads account 1234567890 before checking this campaign request.');
    Http::assertNothingSent();
});

test('an old unconfirmed request can be cleared only after a fresh no-match check', function (): void {
    Queue::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    $draft = GoogleAdsCampaignDraft::factory()->for($website)->for($connection, 'connection')->create([
        'status' => GoogleAdsCampaignDraft::STATUS_UNCERTAIN,
        'updated_at' => now()->subMinutes(6),
    ]);
    Http::fake(['*' => Http::response([['results' => []]])]);

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.clear', [$website, $draft]))
        ->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns']))
        ->assertSessionHas('status');

    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_FAILED);
    Http::assertSentCount(1);
    Queue::assertNothingPushed();

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), [
        'request_key' => (string) Str::uuid(),
        'name' => $draft->name,
        'daily_budget' => 20,
        'city_name' => 'Doncaster',
        'radius_miles' => 20,
        'final_url' => 'https://example.com/',
        'keywords_text' => 'local seo services',
        'headlines' => ['Headline one', 'Headline two', 'Headline three'],
        'descriptions' => ['A useful first description.', 'A useful second description.'],
    ])->assertSessionHas('status');

    expect(GoogleAdsCampaignDraft::query()->count())->toBe(2);
    Queue::assertPushed(CreateGoogleAdsCampaign::class);
});

test('an unconfirmed request cannot be cleared if Google Ads now has the campaign', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    $draft = GoogleAdsCampaignDraft::factory()->for($website)->for($connection, 'connection')->create([
        'status' => GoogleAdsCampaignDraft::STATUS_UNCERTAIN,
        'updated_at' => now()->subMinutes(6),
    ]);
    Http::fake(['*' => Http::response([['results' => [
        ['campaign' => ['id' => '987654321', 'name' => $draft->name, 'status' => 'PAUSED']],
    ]]])]);

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.clear', [$website, $draft]))
        ->assertSessionHas('error', 'A campaign with this name now exists in Google Ads. The request was not cleared.');

    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_UNCERTAIN);
    Http::assertSentCount(1);
});

test('a recent unconfirmed request cannot be cleared while its original job may still run', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    $draft = GoogleAdsCampaignDraft::factory()->for($website)->for($connection, 'connection')->create(['status' => GoogleAdsCampaignDraft::STATUS_UNCERTAIN]);
    Http::fake();

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.clear', [$website, $draft]))
        ->assertSessionHas('error', 'Wait five minutes for the original campaign request to finish, then check it again.');

    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_UNCERTAIN);
    Http::assertNothingSent();
});

test('an unconfirmed request cannot be cleared through another website or Ads account', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $otherWebsite = Website::factory()->for($owner, 'owner')->create();
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    $draft = GoogleAdsCampaignDraft::factory()->for($website)->for($connection, 'connection')->create([
        'status' => GoogleAdsCampaignDraft::STATUS_UNCERTAIN,
        'updated_at' => now()->subMinutes(6),
    ]);
    Http::fake();

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.clear', [$otherWebsite, $draft]))->assertNotFound();
    $connection->update(['customer_id' => '9999999999']);
    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.clear', [$website, $draft]))
        ->assertSessionHas('error', 'Select Ads account 1234567890 before clearing this request.');

    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_UNCERTAIN);
    Http::assertNothingSent();
});

test('a Google Ads timeout fails safely without a 500 or a second mutation', function (): void {
    Queue::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    Http::fake(['*' => Http::failedConnection()]);

    $data = [
        'request_key' => (string) Str::uuid(),
        'name' => 'Example search', 'daily_budget' => 20, 'city_name' => 'Doncaster', 'radius_miles' => 20,
        'final_url' => 'https://example.com/', 'keywords_text' => 'local seo services',
        'headlines' => ['Headline one', 'Headline two', 'Headline three'],
        'descriptions' => ['A useful first description.', 'A useful second description.'],
    ];
    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), $data)
        ->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns']))->assertSessionHas('status');
    $draft = GoogleAdsCampaignDraft::query()->firstOrFail();

    expect(fn (): mixed => (new CreateGoogleAdsCampaign($draft->id))->handle(app(GoogleAdsCampaignCreator::class)))
        ->toThrow(ConnectionException::class);
    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_FAILED)
        ->and($draft->fresh()->error)->toBe('Google Ads did not respond in time.');
    Http::assertSentCount(1);
    $this->actingAs($owner)->get(route('admin.google-ads.index', $website))
        ->assertSuccessful()->assertSee('Google Ads did not respond in time.');
});

test('Google Ads is hidden and all website routes require the owners active Complete plan', function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::GROWTH, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $owner->update(['current_website_id' => $website->id]);
    $adsUrl = route('admin.google-ads.index', $website);

    $this->actingAs($owner)->get(route('admin.dashboard'))->assertSuccessful()->assertDontSee($adsUrl, false);
    foreach (['index', 'connect'] as $action) {
        $this->actingAs($owner)->get(route('admin.google-ads.'.$action, $website))
            ->assertRedirect(route('admin.billing.index'));
    }
    $this->actingAs($owner)->post(route('admin.google-ads.account', $website), ['customer_id' => '1234567890'])
        ->assertRedirect(route('admin.billing.index'));
    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), [])
        ->assertRedirect(route('admin.billing.index'));
    $this->actingAs($owner)->post(route('admin.google-ads.campaign-draft.save', $website), [])
        ->assertRedirect(route('admin.billing.index'));
    $this->actingAs($owner)->patch(route('admin.google-ads.live-campaigns.status', [$website, '987654321']), ['status' => 'ENABLED', 'tracking_confirmed' => '1'])
        ->assertRedirect(route('admin.billing.index'));
    $this->actingAs($owner)->delete(route('admin.google-ads.live-campaigns.destroy', [$website, '987654321']), ['confirmation' => 'Local search'])
        ->assertRedirect(route('admin.billing.index'));
    $this->actingAs($owner)->delete(route('admin.google-ads.destroy', $website))
        ->assertRedirect(route('admin.billing.index'));
    Http::assertNothingSent();

    $owner->update(['membership_tier' => MembershipPlan::COMPLETE]);
    $this->actingAs($owner)->get(route('admin.dashboard'))->assertSuccessful()->assertSee($adsUrl, false);
    $owner->update(['membership_status' => 'canceled']);
    $this->actingAs($owner)->get($adsUrl)->assertRedirect(route('admin.billing.index'));
});

test('a shared manager uses the website owners Complete plan and an admin can provide support', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $manager = User::factory()->create(['membership_tier' => MembershipPlan::ESSENTIAL, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);

    $this->actingAs($manager)->get(route('admin.google-ads.index', $website))->assertSuccessful();
    $owner->update(['membership_tier' => MembershipPlan::GROWTH]);
    $this->actingAs($manager)->get(route('admin.google-ads.index', $website))
        ->assertRedirect(route('admin.billing.index'));

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->get(route('admin.google-ads.index', $website))->assertSuccessful();
});

test('an OAuth callback cannot connect Ads after Complete access ends', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $response = $this->actingAs($owner)->get(route('admin.google-ads.connect', $website));
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);

    $owner->update(['membership_tier' => MembershipPlan::GROWTH]);
    $this->actingAs($owner)->get(route('admin.google-ads.callback', [
        'state' => $parameters['state'], 'code' => 'authorization-code',
    ]))->assertRedirect(route('admin.billing.index'));

    expect(GoogleAdsConnection::query()->count())->toBe(0);
    Http::assertNothingSent();
});
