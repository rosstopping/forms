<?php

use App\Models\GoogleAdsCampaignDraft;
use App\Models\GoogleAdsConnection;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\User;
use App\Models\Website;
use App\Services\GoogleAdsOAuthClient;
use App\Support\MembershipPlan;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
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
    ]))->assertRedirect(route('admin.google-ads.index', $website));

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

    $this->actingAs($owner)->get(route('admin.google-ads.index', $website))
        ->assertSuccessful()
        ->assertSee($eligible->query)
        ->assertDontSee('private other website');
});

test('a reviewed campaign is validated then created paused exactly once', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create([
        'customer_id' => '1234567890', 'currency_code' => 'GBP',
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
        ->assertRedirect(route('admin.google-ads.index', $website));
    $draft = GoogleAdsCampaignDraft::query()->firstOrFail();
    expect($draft->status)->toBe(GoogleAdsCampaignDraft::STATUS_CREATED)
        ->and($draft->daily_budget_micros)->toBe(20000000)
        ->and($draft->campaign_resource_name)->toBe('customers/1234567890/campaigns/987');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request['validateOnly'] === true
        && $request['mutateOperations'][1]['campaignOperation']['create']['status'] === 'PAUSED'
        && $request['mutateOperations'][2]['campaignCriterionOperation']['create']['proximity']['radius'] === 20);

    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), $data)
        ->assertRedirect(route('admin.google-ads.index', $website));
    expect(GoogleAdsCampaignDraft::query()->count())->toBe(1);
    Http::assertSentCount(2);
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
        ->assertSessionHas('error');
    expect(GoogleAdsCampaignDraft::query()->firstOrFail()->status)->toBe(GoogleAdsCampaignDraft::STATUS_UNCERTAIN);
    $this->actingAs($owner)->post(route('admin.google-ads.campaigns.store', $website), $data)
        ->assertRedirect(route('admin.google-ads.index', $website));
    Http::assertSentCount(2);
});

test('Google Ads is hidden and all website routes require the owners active Complete plan', function (): void {
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
