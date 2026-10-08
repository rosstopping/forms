<?php

use App\Jobs\CreateGoogleAdsCampaign;
use App\Models\GoogleAdsCampaignDraft;
use App\Models\GoogleAdsConnection;
use App\Models\Website;
use App\Services\GoogleAdsCampaignCreator;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['services.google_ads.api_url' => 'https://googleads.test/v25']);
});

it('queues one paused UK service campaign for the verified Sitewell Ads account', function (): void {
    Queue::fake();
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'sitewell.digizu.co.uk', 'is_primary' => true]);
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '8204189823', 'currency_code' => 'GBP']);
    $migration = require database_path('migrations/2026_10_08_084800_queue_sitewell_uk_service_search_campaign.php');
    $migration->up();
    $migration->up();
    $migration->down();

    $draft = GoogleAdsCampaignDraft::query()->sole();
    expect($draft->website_id)->toBe($website->id)
        ->and($draft->google_ads_connection_id)->toBe($connection->id)
        ->and($draft->name)->toBe('Sitewell | Managed SEO | UK')
        ->and($draft->daily_budget_micros)->toBe(20_000_000)
        ->and($draft->max_cpc_micros)->toBe(3_000_000)
        ->and($draft->target_country)->toBe('GB')
        ->and($draft->status)->toBe(GoogleAdsCampaignDraft::STATUS_PENDING)
        ->and($draft->final_url)->toContain('/managed-seo?', 'utm_campaign=sitewell_service_search')
        ->and($draft->keywords)->toContain('managed seo services', 'seo services for small business')
        ->and($draft->negative_keywords)->toContain('website speed test', 'ahrefs');
    foreach ($draft->headlines as $headline) {
        expect(mb_strlen($headline))->toBeLessThanOrEqual(30);
    }
    foreach ($draft->descriptions as $description) {
        expect(mb_strlen($description))->toBeLessThanOrEqual(90);
    }
    Queue::assertPushed(CreateGoogleAdsCampaign::class, 1);
    Http::assertNothingSent();

    Http::fake(function (ClientRequest $request) {
        if (str_contains((string) ($request['query'] ?? ''), 'FROM geo_target_constant')) {
            return Http::response([['results' => [['geoTargetConstant' => [
                'resourceName' => 'geoTargetConstants/2826', 'countryCode' => 'GB', 'targetType' => 'Country', 'status' => 'ENABLED',
            ]]]]]);
        }

        return Http::response($request['validateOnly'] ? [] : ['mutateOperationResponses' => [
            [], ['campaignResult' => ['resourceName' => 'customers/8204189823/campaigns/987']],
        ]]);
    });
    (new CreateGoogleAdsCampaign($draft->id))->handle(app(GoogleAdsCampaignCreator::class));
    (new CreateGoogleAdsCampaign($draft->id))->handle(app(GoogleAdsCampaignCreator::class));
    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_CREATED);
    Http::assertSentCount(3);
    Http::assertSent(function (ClientRequest $request): bool {
        if (! str_contains($request->url(), 'googleAds:mutate') || $request['validateOnly']) {
            return false;
        }
        $operations = $request['mutateOperations'];
        $negatives = array_filter($operations, fn (array $operation): bool => data_get($operation, 'campaignCriterionOperation.create.negative') === true);

        return data_get($operations, '1.campaignOperation.create.status') === 'PAUSED'
            && data_get($operations, '0.campaignBudgetOperation.create.amountMicros') === '20000000'
            && data_get($operations, '2.campaignCriterionOperation.create.location.geoTargetConstant') === 'geoTargetConstants/2826'
            && data_get($operations, '2.campaignCriterionOperation.create.proximity') === null
            && data_get($operations, '1.campaignOperation.create.geoTargetTypeSetting.positiveGeoTargetType') === 'PRESENCE'
            && count($negatives) === 9;
    });
});

it('never seeds this campaign into an unrelated account or unverified domain', function (string $domain, string $customer, string $currency, string $ownership): void {
    Queue::fake();
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => $domain, 'is_primary' => true, 'ownership_status' => $ownership]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => $customer, 'currency_code' => $currency]);
    $migration = require database_path('migrations/2026_10_08_084800_queue_sitewell_uk_service_search_campaign.php');
    $migration->up();
    expect(GoogleAdsCampaignDraft::query()->count())->toBe(0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with([
    'different website' => ['customer.example', '8204189823', 'GBP', 'verified'],
    'different account' => ['sitewell.digizu.co.uk', '1234567890', 'GBP', 'verified'],
    'different currency' => ['sitewell.digizu.co.uk', '8204189823', 'USD', 'verified'],
    'unverified domain' => ['sitewell.digizu.co.uk', '8204189823', 'GBP', 'pending'],
]);

it('fails country targeting before creating a campaign if Google cannot confirm the country', function (): void {
    $connection = GoogleAdsConnection::factory()->create(['customer_id' => '8204189823']);
    $draft = GoogleAdsCampaignDraft::factory()->for($connection, 'connection')->create(['customer_id' => '8204189823', 'target_country' => 'GB']);
    Http::fake(['https://googleads.test/*' => Http::response([['results' => []]])]);
    expect(fn (): mixed => (new CreateGoogleAdsCampaign($draft->id))->handle(app(GoogleAdsCampaignCreator::class)))
        ->toThrow(RuntimeException::class, 'Google Ads did not confirm the requested target country.');
    expect($draft->fresh()->status)->toBe(GoogleAdsCampaignDraft::STATUS_FAILED);
    Http::assertSentCount(1);
    Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), 'googleAds:mutate'));
});
