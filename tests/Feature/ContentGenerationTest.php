<?php

use App\Jobs\StartContentGeneration;
use App\Jobs\SyncContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\CopilotAgentClient;
use App\Services\GoogleOAuthClient;
use App\Services\SearchConsoleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.google.client_id' => 'google-client', 'services.google.client_secret' => 'google-secret']);
});

test('google authorization requests offline read only search console access', function () {
    $url = app(GoogleOAuthClient::class)->authorizationUrl('secure-state');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => 'secure-state',
    ]);
});

test('a due weekly content plan queues one generation only', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->create(['user_id' => $admin->id]);
    $website = Website::factory()->create();
    $installation = GithubInstallation::factory()->create();
    $repository = WebsiteRepository::factory()->create(['website_id' => $website->id, 'github_installation_id' => $installation->id]);
    SearchConsoleConnection::factory()->create(['website_id' => $website->id, 'connected_by' => $admin->id]);
    $plan = ContentPlan::factory()->create([
        'website_id' => $website->id,
        'created_by' => $admin->id,
        'weekday' => now('Europe/London')->dayOfWeek,
        'hour' => now('Europe/London')->hour,
    ]);

    $this->artisan('content:dispatch')->assertSuccessful();
    $this->artisan('content:dispatch')->assertSuccessful();

    expect($plan->generations()->count())->toBe(1)
        ->and($plan->generations()->first()->website_repository_id)->toBe($repository->id);
    Queue::assertPushed(StartContentGeneration::class, 1);
});

test('content generation uses search performance to start a copilot pull request task', function () {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->create(['user_id' => $admin->id]);
    $website = Website::factory()->create(['name' => 'Example Site']);
    $repository = WebsiteRepository::factory()->create(['website_id' => $website->id]);
    SearchConsoleConnection::factory()->create(['website_id' => $website->id, 'connected_by' => $admin->id]);
    $plan = ContentPlan::factory()->create(['website_id' => $website->id, 'created_by' => $admin->id]);
    $generation = ContentGeneration::factory()->create([
        'content_plan_id' => $plan->id,
        'website_repository_id' => $repository->id,
        'requested_by' => $admin->id,
    ]);
    $this->mock(SearchConsoleClient::class)->shouldReceive('performance')->once()->andReturn([
        ['query' => 'useful service', 'page' => 'https://example.test/', 'clicks' => 4.0, 'impressions' => 100.0, 'ctr' => 0.04, 'position' => 8.2],
    ]);
    $this->mock(CopilotAgentClient::class)->shouldReceive('startTask')->once()
        ->withArgs(fn ($authorization, $passedRepository, string $prompt) => $passedRepository->is($repository) && str_contains($prompt, 'useful service'))
        ->andReturn(['id' => '11111111-1111-4111-8111-111111111111', 'state' => 'queued']);

    app()->call([new StartContentGeneration($generation), 'handle']);

    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_RUNNING)
        ->and($generation->fresh()->search_performance[0]['query'])->toBe('useful service');
    Queue::assertPushed(SyncContentGeneration::class);
});

test('search console access and refresh tokens are encrypted at rest', function () {
    $connection = SearchConsoleConnection::factory()->create(['access_token' => 'plain-access', 'refresh_token' => 'plain-refresh']);
    $raw = DB::table('search_console_connections')->find($connection->id);

    expect($raw->access_token)->not->toContain('plain-access')
        ->and($raw->refresh_token)->not->toContain('plain-refresh');
});
