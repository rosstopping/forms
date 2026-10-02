<?php

use App\Jobs\StartGoogleAdsTrackingRequest;
use App\Jobs\SyncGoogleAdsTrackingRequest;
use App\Models\GithubUserAuthorization;
use App\Models\GoogleAdsConnection;
use App\Models\GoogleAdsTrackingRequest;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use App\Services\GoogleAdsTrackingPrompt;
use App\Support\MembershipPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config(['services.google_ads.api_url' => 'https://googleads.test/v25']);
    Http::preventStrayRequests();
});

test('connected managers can prepare a tracking pull request from the Ads settings page', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    WebsiteRepository::factory()->for($website)->create();
    GithubUserAuthorization::factory()->for($owner)->create();
    Http::fake([
        'https://googleads.test/v25/customers:listAccessibleCustomers' => Http::response(['resourceNames' => []]),
        'https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [
            ['conversionAction' => ['id' => '456', 'name' => 'Lead form', 'category' => 'SUBMIT_LEAD_FORM', 'type' => 'WEBPAGE', 'primaryForGoal' => true]],
        ]]]),
    ]);

    $this->actingAs($owner)->get(route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings']))
        ->assertSuccessful()
        ->assertSee('Prepare tracking PR')
        ->assertSee('GA4 is optional.')
        ->assertSee('value="456"', false);
});

test('a manager can queue a website conversion implementation once for review', function (): void {
    Queue::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    WebsiteRepository::factory()->for($website)->create(['full_name' => 'acme/example']);
    GithubUserAuthorization::factory()->for($owner)->create();
    Http::fake(['https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [
        ['conversionAction' => ['id' => '456', 'name' => 'Lead form', 'category' => 'SUBMIT_LEAD_FORM', 'type' => 'WEBPAGE', 'tagSnippets' => [['eventSnippet' => "gtag('event', 'conversion', {'send_to': 'AW-123456789/LeadLabel'});"]]]],
    ]]])]);

    $data = ['conversion_action_id' => '456', 'lead_success_description' => 'The contact form is accepted and saved.'];
    $this->actingAs($owner)->post(route('admin.google-ads.tracking.store', $website), $data)
        ->assertRedirect(route('admin.google-ads.index', ['website' => $website, 'tab' => 'settings']))
        ->assertSessionHas('status');

    $trackingRequest = GoogleAdsTrackingRequest::query()->firstOrFail();
    expect($trackingRequest->send_to)->toBe('AW-123456789/LeadLabel')
        ->and($trackingRequest->customer_id)->toBe('1234567890')
        ->and($trackingRequest->status)->toBe(GoogleAdsTrackingRequest::STATUS_QUEUED);
    Queue::assertPushed(StartGoogleAdsTrackingRequest::class, 1);

    $this->actingAs($owner)->post(route('admin.google-ads.tracking.store', $website), $data)
        ->assertSessionHas('error');
    expect(GoogleAdsTrackingRequest::query()->count())->toBe(1);
    Queue::assertPushed(StartGoogleAdsTrackingRequest::class, 1);
});

test('a non-website conversion cannot be sent to Copilot as a website tag', function (): void {
    Queue::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    WebsiteRepository::factory()->for($website)->create();
    GithubUserAuthorization::factory()->for($owner)->create();
    Http::fake(['https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [
        ['conversionAction' => ['id' => '456', 'name' => 'Imported lead', 'category' => 'SUBMIT_LEAD_FORM', 'type' => 'UPLOAD_CLICKS']],
    ]]])]);

    $this->actingAs($owner)->post(route('admin.google-ads.tracking.store', $website), [
        'conversion_action_id' => '456', 'lead_success_description' => 'The contact form is accepted and saved.',
    ])->assertSessionHas('error');

    expect(GoogleAdsTrackingRequest::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a page-view action cannot be queued as a lead conversion', function (): void {
    Queue::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    WebsiteRepository::factory()->for($website)->create();
    GithubUserAuthorization::factory()->for($owner)->create();
    Http::fake(['https://googleads.test/v25/customers/1234567890/googleAds:searchStream' => Http::response([['results' => [
        ['conversionAction' => ['id' => '456', 'name' => 'Page view', 'category' => 'PAGE_VIEW', 'type' => 'WEBPAGE', 'tagSnippets' => [['eventSnippet' => "send_to: 'AW-123456789/PageView'"]]]],
    ]]])]);

    $this->actingAs($owner)->post(route('admin.google-ads.tracking.store', $website), [
        'conversion_action_id' => '456', 'lead_success_description' => 'The contact form is accepted and saved.',
    ])->assertSessionHas('error');

    expect(GoogleAdsTrackingRequest::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('tracking automation asks Copilot for a reviewable consent-aware pull request', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    $repository = WebsiteRepository::factory()->for($website)->create(['full_name' => 'acme/example']);
    GithubUserAuthorization::factory()->for($owner)->create();
    $trackingRequest = GoogleAdsTrackingRequest::factory()->for($website)->for($repository, 'repository')->for($owner, 'requester')->create();

    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldReceive('startTask')->once()
        ->withArgs(fn ($authorization, $passedRepository, string $prompt): bool => $passedRepository->is($repository)
            && str_contains($prompt, 'AW-123456789/ExampleLabel')
            && str_contains($prompt, 'consent-aware')
            && str_contains($prompt, 'Do not merge or deploy.')
            && str_contains($prompt, 'The contact form has been accepted and saved.'))
        ->andReturn(['id' => 'task-123', 'state' => 'queued']);
    $copilot->shouldReceive('task')->once()->andReturn([
        'state' => 'completed',
        'artifacts' => [['provider' => 'github', 'type' => 'pull', 'data' => ['number' => 42]]],
    ]);

    (new StartGoogleAdsTrackingRequest($trackingRequest))->handle($copilot, app(GoogleAdsTrackingPrompt::class));
    expect($trackingRequest->fresh()->copilot_task_id)->toBe('task-123')
        ->and($trackingRequest->fresh()->status)->toBe(GoogleAdsTrackingRequest::STATUS_RUNNING);
    Queue::assertPushed(SyncGoogleAdsTrackingRequest::class, 1);

    $github = $this->mock(GithubAppClient::class);
    $github->shouldNotReceive('pullRequestForHead');
    (new SyncGoogleAdsTrackingRequest($trackingRequest))->handle($copilot, $github);

    expect($trackingRequest->fresh()->status)->toBe(GoogleAdsTrackingRequest::STATUS_PULL_REQUEST_OPEN)
        ->and($trackingRequest->fresh()->pull_request_url)->toBe('https://github.com/acme/example/pull/42');
});

test('a queued tracking request stops when the selected Ads account changes', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $connection = GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890']);
    $repository = WebsiteRepository::factory()->for($website)->create();
    GithubUserAuthorization::factory()->for($owner)->create();
    $trackingRequest = GoogleAdsTrackingRequest::factory()->for($website)->for($repository, 'repository')->for($owner, 'requester')->create();
    $connection->update(['customer_id' => '9999999999']);

    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldNotReceive('startTask');
    (new StartGoogleAdsTrackingRequest($trackingRequest))->handle($copilot, app(GoogleAdsTrackingPrompt::class));

    expect($trackingRequest->fresh()->status)->toBe(GoogleAdsTrackingRequest::STATUS_FAILED);
    Queue::assertNothingPushed();
});
