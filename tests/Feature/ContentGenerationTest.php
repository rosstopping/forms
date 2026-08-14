<?php

use App\Jobs\StartContentGeneration;
use App\Jobs\SyncContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use App\Services\GoogleOAuthClient;
use App\Services\SearchConsoleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
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

test('website owners can connect Search Console to their website', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $oauth = $this->mock(GoogleOAuthClient::class);
    $oauth->shouldReceive('authorizationUrl')->once()->andReturn('https://accounts.google.test/authorize');

    $this->actingAs($owner)
        ->get(route('admin.search-console.connect', $website))
        ->assertRedirect('https://accounts.google.test/authorize');

    $connection = SearchConsoleConnection::factory()->for($website)->for($owner, 'connector')->create([
        'property_url' => null,
        'permission_level' => null,
    ]);
    $this->mock(SearchConsoleClient::class)
        ->shouldReceive('sites')
        ->once()
        ->withArgs(fn (SearchConsoleConnection $selectedConnection): bool => $selectedConnection->is($connection))
        ->andReturn([['siteUrl' => 'sc-domain:client.test', 'permissionLevel' => 'siteOwner']]);

    $this->actingAs($owner)
        ->post(route('admin.search-console.property.store', $website), [
            'property_url' => 'sc-domain:client.test',
        ])
        ->assertRedirect(route('admin.websites.show', $website));

    expect($connection->fresh()->property_url)->toBe('sc-domain:client.test');
});

test('website owners cannot connect Search Console to another clients website', function () {
    $owner = User::factory()->create();
    $otherWebsite = Website::factory()->create();

    $this->actingAs($owner)
        ->get(route('admin.search-console.connect', $otherWebsite))
        ->assertForbidden();
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

test('content plan settings remain saved when activation requirements are missing', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    WebsiteRepository::factory()->for($website)->create();

    $this->actingAs($admin)
        ->put(route('admin.content-plans.update', $website), [
            'enabled' => true,
            'weekday' => 4,
            'hour' => 15,
            'timezone' => 'America/New_York',
            'audience' => 'Saved audience details',
            'guidance' => 'Saved editorial guidance',
        ])
        ->assertSessionHasErrors('enabled');

    $plan = $website->contentPlan()->firstOrFail();

    expect($plan->enabled)->toBeFalse()
        ->and($plan->weekday)->toBe(4)
        ->and($plan->hour)->toBe(15)
        ->and($plan->timezone)->toBe('America/New_York')
        ->and($plan->audience)->toBe('Saved audience details')
        ->and($plan->guidance)->toBe('Saved editorial guidance');

    $this->actingAs($admin)
        ->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Saved audience details')
        ->assertSee('Saved editorial guidance')
        ->assertSee('America/New_York');
});

test('content plans accept substantial audience and editorial guidance', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $audience = rtrim(str_repeat('Large groups planning a special occasion at a fully staffed private villa. ', 100));
    $guidance = rtrim(str_repeat('Write confidently, show the guest experience, and avoid generic luxury clichés. ', 100));

    expect(mb_strlen($audience))->toBeGreaterThan(5000)
        ->and(mb_strlen($guidance))->toBeGreaterThan(5000);

    $this->actingAs($admin)
        ->put(route('admin.content-plans.update', $website), [
            'enabled' => false,
            'weekday' => 4,
            'hour' => 15,
            'timezone' => 'Europe/London',
            'audience' => $audience,
            'guidance' => $guidance,
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('admin.websites.show', $website));

    $plan = $website->contentPlan()->firstOrFail();

    expect($plan->audience)->toBe($audience)
        ->and($plan->guidance)->toBe($guidance);
});

test('updating the website owner does not overwrite saved content plan settings', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $owner = User::factory()->create();
    $website = Website::factory()->create();
    WebsiteRepository::factory()->for($website)->create();

    $this->actingAs($admin)
        ->put(route('admin.content-plans.update', $website), [
            'enabled' => false,
            'weekday' => 4,
            'hour' => 15,
            'timezone' => 'Europe/London',
            'audience' => 'Independent venue owners',
            'guidance' => 'Use a practical and direct tone',
        ])
        ->assertRedirect(route('admin.websites.show', $website));

    $this->actingAs($admin)
        ->put(route('admin.websites.update', $website), [
            'user_id' => $owner->id,
            'health_reports_enabled' => false,
        ])
        ->assertRedirect(route('admin.websites.show', $website));

    $plan = $website->contentPlan()->firstOrFail();

    expect($website->fresh()->user_id)->toBe($owner->id)
        ->and($plan->audience)->toBe('Independent venue owners')
        ->and($plan->guidance)->toBe('Use a practical and direct tone');

    $this->actingAs($admin)
        ->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Independent venue owners')
        ->assertSee('Use a practical and direct tone');
});

test('website owners can queue and remove manual content requests', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    WebsiteRepository::factory()->for($website)->create();
    $instructions = 'Create a Love Island inspired villa landing page while clearly stating that this is not the official villa.';

    $this->actingAs($owner)
        ->post(route('admin.content-requests.store', $website), ['instructions' => $instructions])
        ->assertRedirect(route('admin.websites.show', $website));

    $contentRequest = $website->contentRequests()->sole();
    expect($contentRequest->instructions)->toBe($instructions)
        ->and($contentRequest->created_by)->toBe($owner->id);

    $this->actingAs($owner)
        ->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Manual content requests')
        ->assertSee($instructions)
        ->assertSee('Pending');

    $this->actingAs($otherOwner)
        ->post(route('admin.content-requests.store', $website), ['instructions' => 'Should not be saved.'])
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('admin.content-requests.destroy', [$website, $contentRequest]))
        ->assertRedirect(route('admin.websites.show', $website));

    $this->assertModelMissing($contentRequest);
});

test('actioned content todos remain visible separately with their generation outcome', function () {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $repository = WebsiteRepository::factory()->for($website)->create();
    $plan = ContentPlan::factory()->for($website)->for($owner, 'creator')->create();
    $generation = ContentGeneration::factory()
        ->for($plan, 'plan')
        ->for($repository, 'repository')
        ->for($owner, 'requester')
        ->create([
            'status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN,
            'pull_request_url' => 'https://github.com/example/site/pull/42',
            'pull_request_number' => 42,
        ]);
    $pending = ContentRequest::factory()->for($website)->for($owner, 'creator')->create([
        'instructions' => 'Pending content todo',
    ]);
    $actioned = ContentRequest::factory()->for($website)->for($owner, 'creator')->create([
        'content_generation_id' => $generation->id,
        'picked_up_at' => now()->subHour(),
        'instructions' => 'Completed content todo',
    ]);

    $response = $this->actingAs($owner)->get(route('admin.websites.show', $website));

    $response->assertSuccessful()
        ->assertSee('Pending todos')
        ->assertSee('Pending content todo')
        ->assertSee('Actioned todos')
        ->assertSee('Completed content todo')
        ->assertSee('pull request open')
        ->assertSee('View pull request')
        ->assertSee('https://github.com/example/site/pull/42');

    expect($pending->fresh()->picked_up_at)->toBeNull()
        ->and($actioned->fresh()->content_generation_id)->toBe($generation->id);
});

test('content generation uses search performance and pending requests to start a copilot pull request task', function () {
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
    $firstRequest = ContentRequest::factory()->for($website)->create([
        'created_by' => $admin->id,
        'instructions' => 'Create a landing page targeting love island villa tenerife.',
        'created_at' => now()->subMinutes(3),
    ]);
    $secondRequest = ContentRequest::factory()->for($website)->create([
        'created_by' => $admin->id,
        'instructions' => 'State clearly that this is not the official Love Island villa.',
        'created_at' => now()->subMinutes(2),
    ]);
    $thirdRequest = ContentRequest::factory()->for($website)->create([
        'created_by' => $admin->id,
        'instructions' => 'Create a separate guide in a later run.',
        'created_at' => now()->subMinute(),
    ]);
    $this->mock(SearchConsoleClient::class)->shouldReceive('performance')->once()->andReturn([
        ['query' => 'useful service', 'page' => 'https://example.test/', 'clicks' => 4.0, 'impressions' => 100.0, 'ctr' => 0.04, 'position' => 8.2],
    ]);
    $this->mock(CopilotAgentClient::class)->shouldReceive('startTask')->once()
        ->withArgs(fn ($authorization, $passedRepository, string $prompt) => $passedRepository->is($repository)
            && str_contains($prompt, 'useful service')
            && str_contains($prompt, $firstRequest->instructions)
            && str_contains($prompt, $secondRequest->instructions)
            && ! str_contains($prompt, $thirdRequest->instructions))
        ->andReturn(['id' => '11111111-1111-4111-8111-111111111111', 'state' => 'queued']);

    app()->call([new StartContentGeneration($generation), 'handle']);

    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_RUNNING)
        ->and($generation->fresh()->search_performance[0]['query'])->toBe('useful service')
        ->and($firstRequest->fresh()->content_generation_id)->toBe($generation->id)
        ->and($firstRequest->fresh()->picked_up_at)->not->toBeNull()
        ->and($secondRequest->fresh()->content_generation_id)->toBe($generation->id)
        ->and($secondRequest->fresh()->picked_up_at)->not->toBeNull()
        ->and($thirdRequest->fresh()->content_generation_id)->toBeNull()
        ->and($thirdRequest->fresh()->picked_up_at)->toBeNull();
    Queue::assertPushed(SyncContentGeneration::class);
});

test('content generation prompts keep every section within the Copilot request budget', function () {
    $website = Website::factory()->create(['name' => 'Example Site']);
    $repository = WebsiteRepository::factory()->create(['website_id' => $website->id]);
    $plan = ContentPlan::factory()->create([
        'website_id' => $website->id,
        'audience' => str_repeat('A', 6000),
        'guidance' => str_repeat('G', 18000),
    ]);
    $generation = ContentGeneration::factory()->create([
        'content_plan_id' => $plan->id,
        'website_repository_id' => $repository->id,
        'search_performance' => collect(range(1, 250))->map(fn (int $row): array => [
            'query' => "useful search query {$row}",
            'page' => "https://example.test/a-long-content-page/{$row}",
            'clicks' => 10.0,
            'impressions' => 100.0,
            'ctr' => 0.1,
            'position' => 4.2,
        ])->all(),
    ]);

    $prompt = app(ContentGenerationPromptGenerator::class)->generate($generation);

    expect(mb_strlen($prompt))->toBeLessThanOrEqual(30000)
        ->and($prompt)->toContain('[Audience truncated for Copilot.]')
        ->and($prompt)->toContain('[Editorial guidance truncated for Copilot.]')
        ->and($prompt)->toContain('Search Console rows included.]')
        ->and($prompt)->toContain('one high-quality, reviewable content initiative')
        ->and($prompt)->toContain('a lightweight blog or content section when none exists')
        ->and($prompt)->toContain('Do not force a blog')
        ->and($prompt)->toContain('It may touch multiple pages and files')
        ->and($prompt)->toContain('Requirements:')
        ->and($prompt)->toContain('Search Console top query/page rows');
});

test('manual content requests remain pending when copilot does not accept the task', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $website = Website::factory()->create();
    $repository = WebsiteRepository::factory()->for($website)->create();
    SearchConsoleConnection::factory()->for($website)->create(['connected_by' => $admin->id]);
    $plan = ContentPlan::factory()->for($website)->create(['created_by' => $admin->id]);
    $generation = ContentGeneration::factory()->for($plan, 'plan')->for($repository, 'repository')->for($admin, 'requester')->create();
    $contentRequest = ContentRequest::factory()->for($website)->for($admin, 'creator')->create();

    $this->mock(SearchConsoleClient::class)->shouldReceive('performance')->once()->andReturn([]);
    $this->mock(CopilotAgentClient::class)->shouldReceive('startTask')->once()->andThrow(new RuntimeException('GitHub unavailable.'));

    expect(fn () => app()->call([new StartContentGeneration($generation), 'handle']))
        ->toThrow(RuntimeException::class, 'GitHub unavailable.');

    expect($contentRequest->fresh()->picked_up_at)->toBeNull()
        ->and($contentRequest->fresh()->content_generation_id)->toBeNull();
});

test('starting a Copilot task is never automatically replayed', function () {
    config(['services.github.api_url' => 'https://api.github.test']);
    Http::preventStrayRequests();
    Http::fakeSequence('api.github.test/agents/repos/*/tasks')
        ->push(['message' => 'Temporary GitHub error'], 500)
        ->push([
            'id' => '22222222-2222-4222-8222-222222222222',
            'state' => 'queued',
        ], 201);
    $authorization = GithubUserAuthorization::factory()->create();
    $repository = WebsiteRepository::factory()->create(['full_name' => 'acme/site']);

    expect(fn () => app(CopilotAgentClient::class)->startTask($authorization, $repository, 'Create useful content.'))
        ->toThrow(RequestException::class)
        ->and((new StartContentGeneration(ContentGeneration::factory()->create()))->tries)->toBe(1);

    Http::assertSentCount(1);
});

test('a content generation with a recorded Copilot task is not started again', function () {
    $generation = ContentGeneration::factory()->create([
        'copilot_task_id' => '33333333-3333-4333-8333-333333333333',
        'copilot_task_state' => 'queued',
    ]);
    $searchConsole = $this->mock(SearchConsoleClient::class);
    $prompts = $this->mock(ContentGenerationPromptGenerator::class);
    $copilot = $this->mock(CopilotAgentClient::class);
    $searchConsole->shouldNotReceive('performance');
    $prompts->shouldNotReceive('generate');
    $copilot->shouldNotReceive('startTask');

    (new StartContentGeneration($generation))->handle($searchConsole, $prompts, $copilot);

    expect($generation->fresh()->copilot_task_id)->toBe('33333333-3333-4333-8333-333333333333');
});

test('content synchronization stores the pull request resolved from the copilot branch', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $repository = WebsiteRepository::factory()->create(['full_name' => 'acme/site']);
    $generation = ContentGeneration::factory()->for($repository, 'repository')->for($admin, 'requester')->create([
        'status' => ContentGeneration::STATUS_RUNNING,
        'copilot_task_id' => '33333333-3333-4333-8333-333333333333',
        'started_at' => now()->subMinute(),
    ]);
    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldReceive('task')->once()->andReturn([
        'state' => 'completed',
        'artifacts' => [['provider' => 'github', 'type' => 'pull', 'data' => ['id' => 999999]]],
        'sessions' => [['head_ref' => 'copilot/create-useful-content']],
    ]);
    $github = $this->mock(GithubAppClient::class);
    $github->shouldReceive('pullRequestForHead')
        ->once()
        ->withArgs(fn ($passedRepository, string $headRef): bool => $passedRepository->is($repository) && $headRef === 'copilot/create-useful-content')
        ->andReturn([
            'number' => 42,
            'html_url' => 'https://github.com/acme/site/pull/42',
        ]);

    (new SyncContentGeneration($generation))->handle($copilot, $github);

    expect($generation->fresh()->pull_request_number)->toBe(42)
        ->and($generation->fresh()->pull_request_url)->toBe('https://github.com/acme/site/pull/42')
        ->and($generation->fresh()->pull_request_url)->not->toContain('999999');
});

test('search console access and refresh tokens are encrypted at rest', function () {
    $connection = SearchConsoleConnection::factory()->create(['access_token' => 'plain-access', 'refresh_token' => 'plain-refresh']);
    $raw = DB::table('search_console_connections')->find($connection->id);

    expect($raw->access_token)->not->toContain('plain-access')
        ->and($raw->refresh_token)->not->toContain('plain-refresh');
});

test('search console reporting returns totals and top queries and pages', function () {
    $connection = SearchConsoleConnection::factory()->create([
        'access_token' => 'valid-access-token',
        'access_token_expires_at' => now()->addHour(),
    ]);
    Http::fakeSequence()
        ->push(['rows' => [['clicks' => 125, 'impressions' => 2500, 'ctr' => 0.05, 'position' => 6.4]]])
        ->push(['rows' => [['keys' => ['example services'], 'clicks' => 40, 'impressions' => 500, 'ctr' => 0.08, 'position' => 3.2]]])
        ->push(['rows' => [['keys' => ['https://example.com/services'], 'clicks' => 40, 'impressions' => 500, 'ctr' => 0.08, 'position' => 3.2]]])
        ->push(['rows' => [['keys' => ['example services', 'https://example.com/services'], 'clicks' => 40, 'impressions' => 500, 'ctr' => 0.08, 'position' => 3.2]]]);

    $report = app(SearchConsoleClient::class)->report($connection);

    expect($report['totals'])->toMatchArray(['clicks' => 125.0, 'impressions' => 2500.0, 'ctr' => 0.05, 'position' => 6.4])
        ->and($report['queries'][0]['query'])->toBe('example services')
        ->and($report['queries'][0]['position'])->toBe(3.2)
        ->and($report['pages'][0]['page'])->toBe('https://example.com/services')
        ->and($report['pages'][0]['position'])->toBe(3.2);

    Http::assertSent(fn ($request): bool => $request['dimensions'] === ['query']
        && $request['rowLimit'] === 10
        && $request['startRow'] === 0);

    $queryPages = app(SearchConsoleClient::class)->queryPagePerformance($connection, 100, 200);

    expect($queryPages[0]['query'])->toBe('example services')
        ->and($queryPages[0]['page'])->toBe('https://example.com/services');

    Http::assertSent(fn ($request): bool => $request['dimensions'] === ['query', 'page']
        && $request['rowLimit'] === 100
        && $request['startRow'] === 200);
});
