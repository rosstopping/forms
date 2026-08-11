<?php

use App\Jobs\StartCopilotRemediation;
use App\Jobs\SyncCopilotRemediation;
use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\RemediationRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Models\WebsiteRepository;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use App\Services\GithubOAuthClient;
use App\Services\RemediationPromptGenerator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

it('retrieves pull request content and changed files with an installation token', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.test/repos/acme/site/pulls/42' => Http::response([
            'number' => 42,
            'title' => 'Publish a new guide',
            'body' => 'Adds a guide based on search demand.',
            'merged_at' => now()->toIso8601String(),
        ]),
        'api.github.test/repos/acme/site/pulls/42/files*' => Http::response([
            ['filename' => 'resources/views/guide.blade.php', 'status' => 'added', 'additions' => 100, 'deletions' => 0],
        ]),
    ]);
    $installation = GithubInstallation::factory()->create(['installation_id' => 9876]);
    $repository = WebsiteRepository::factory()->for($installation, 'installation')->create(['full_name' => 'acme/site']);
    $github = mock(GithubAppClient::class)->makePartial();
    $github->shouldReceive('installationToken')->once()->with(9876)->andReturn('installation-token');

    $details = $github->pullRequestDetails($repository, 42);

    expect($details['pull_request']['title'])->toBe('Publish a new guide')
        ->and($details['files'][0]['filename'])->toBe('resources/views/guide.blade.php');
});

it('retrieves every page of repositories available to an installation', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.test/installation/repositories*' => Http::sequence()
            ->push([
                'total_count' => 101,
                'repositories' => collect(range(1, 100))
                    ->map(fn (int $id): array => ['id' => $id, 'full_name' => "acme/repository-{$id}"])
                    ->all(),
            ])
            ->push([
                'total_count' => 101,
                'repositories' => [['id' => 101, 'full_name' => 'rosstopping/digizu']],
            ]),
    ]);
    $github = mock(GithubAppClient::class)->makePartial();
    $github->shouldReceive('installationToken')->once()->with(9876)->andReturn('installation-token');

    $repositories = $github->repositories(9876);

    expect($repositories)->toHaveCount(101)
        ->and($repositories[100]['full_name'])->toBe('rosstopping/digizu');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.github.test/installation/repositories?per_page=100&page=2');
});

it('starts a GitHub App installation for an administrator', function (): void {
    config(['services.github.app_slug' => 'website-health-bot']);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.github.connect', $website));

    $response->assertRedirectContains('https://github.com/apps/website-health-bot/installations/new?state=');
});

it('stores a verified GitHub App installation callback', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $state = Crypt::encryptString(json_encode([
        'website_id' => $website->id,
        'user_id' => $admin->id,
    ], JSON_THROW_ON_ERROR));

    mock(GithubAppClient::class)
        ->shouldReceive('installation')
        ->once()
        ->with(9876)
        ->andReturn([
            'id' => 9876,
            'account' => ['id' => 123, 'login' => 'acme', 'type' => 'Organization'],
            'repository_selection' => 'selected',
            'permissions' => ['contents' => 'write', 'pull_requests' => 'write'],
        ]);
    mock(GithubOAuthClient::class)
        ->shouldReceive('authorizationUrl')
        ->once()
        ->andReturn('https://github.com/login/oauth/authorize?state=test');

    $this->actingAs($admin)
        ->get(route('admin.github.callback', ['installation_id' => 9876, 'state' => $state]))
        ->assertRedirect('https://github.com/login/oauth/authorize?state=test');

    $installation = GithubInstallation::query()->sole();
    expect($installation->installation_id)->toBe(9876)
        ->and($installation->account_login)->toBe('acme')
        ->and($installation->installed_by)->toBe($admin->id);
});

it('exchanges the GitHub OAuth code and encrypts user tokens', function (): void {
    config([
        'services.github.client_id' => 'client-id',
        'services.github.client_secret' => 'client-secret',
        'services.github.oauth_url' => 'https://github.test/login/oauth',
        'services.github.api_url' => 'https://api.github.test',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'github.test/login/oauth/access_token' => Http::response([
            'access_token' => 'ghu_secret_access',
            'expires_in' => 28800,
            'refresh_token' => 'ghr_secret_refresh',
            'refresh_token_expires_in' => 15897600,
        ]),
        'api.github.test/user' => Http::response(['id' => 55, 'login' => 'octocat']),
    ]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $authorization = app(GithubOAuthClient::class)->authorize($admin, 'temporary-code');

    expect($authorization->github_login)->toBe('octocat')
        ->and($authorization->access_token)->toBe('ghu_secret_access')
        ->and($authorization->getRawOriginal('access_token'))->not->toContain('ghu_secret_access')
        ->and($authorization->getRawOriginal('refresh_token'))->not->toContain('ghr_secret_refresh');
});

it('completes OAuth requested during installation and keeps the website context', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $authorization = GithubUserAuthorization::factory()->for($admin)->create(['github_login' => 'octocat']);
    $state = Crypt::encryptString(json_encode([
        'website_id' => $website->id,
        'user_id' => $admin->id,
    ], JSON_THROW_ON_ERROR));

    mock(GithubAppClient::class)
        ->shouldReceive('installation')
        ->once()
        ->with(9876)
        ->andReturn([
            'id' => 9876,
            'account' => ['id' => 123, 'login' => 'acme', 'type' => 'Organization'],
            'repository_selection' => 'selected',
            'permissions' => ['agent_tasks' => 'write', 'pull_requests' => 'read'],
        ]);
    $oauth = mock(GithubOAuthClient::class);
    $oauth->shouldReceive('authorize')
        ->once()
        ->withArgs(fn (User $user, string $code) => $user->is($admin) && $code === 'temporary-code')
        ->andReturn($authorization);
    $oauth->shouldReceive('canAccessInstallation')
        ->once()
        ->withArgs(fn (GithubUserAuthorization $selectedAuthorization, int $installationId) => $selectedAuthorization->is($authorization) && $installationId === 9876)
        ->andReturnTrue();

    $this->actingAs($admin)
        ->get(route('admin.github.callback', [
            'code' => 'temporary-code',
            'installation_id' => 9876,
            'state' => $state,
        ]))
        ->assertRedirect(route('admin.website-repositories.create', $website));

    expect(GithubInstallation::query()->sole()->account_login)->toBe('acme');
});

it('rotates an expired GitHub user token before starting API work', function (): void {
    config([
        'services.github.client_id' => 'client-id',
        'services.github.client_secret' => 'client-secret',
        'services.github.oauth_url' => 'https://github.test/login/oauth',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'github.test/login/oauth/access_token' => Http::response([
            'access_token' => 'ghu_rotated_access',
            'expires_in' => 28800,
            'refresh_token' => 'ghr_rotated_refresh',
            'refresh_token_expires_in' => 15897600,
        ]),
    ]);
    $authorization = GithubUserAuthorization::factory()->create([
        'access_token' => 'ghu_expired',
        'access_token_expires_at' => now()->subMinute(),
        'refresh_token_expires_at' => now()->addMonth(),
    ]);

    $token = app(GithubOAuthClient::class)->accessToken($authorization);

    expect($token)->toBe('ghu_rotated_access')
        ->and($authorization->fresh()->refresh_token)->toBe('ghr_rotated_refresh');
});

it('binds only a repository returned by the selected installation', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $installation = GithubInstallation::factory()->create(['installation_id' => 9876]);

    mock(GithubAppClient::class)
        ->shouldReceive('repositories')
        ->once()
        ->with(9876)
        ->andReturn([[
            'id' => 456,
            'full_name' => 'acme/marketing',
            'default_branch' => 'main',
            'private' => true,
            'permissions' => ['admin' => true, 'push' => true, 'pull' => true],
        ]]);

    $this->actingAs($admin)
        ->post(route('admin.website-repositories.store', $website), [
            'repository' => $installation->id.':456',
            'project_path' => '/apps/site/',
        ])
        ->assertRedirect(route('admin.websites.show', $website));

    $repository = $website->repository()->sole();
    expect($repository->full_name)->toBe('acme/marketing')
        ->and($repository->project_path)->toBe('apps/site');
});

it('renders repository choices as a searchable select', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $installation = GithubInstallation::factory()->create([
        'installation_id' => 9876,
        'account_login' => 'acme',
    ]);

    mock(GithubAppClient::class)
        ->shouldReceive('repositories')
        ->once()
        ->with(9876)
        ->andReturn([[
            'id' => 456,
            'full_name' => 'acme/marketing',
            'default_branch' => 'main',
            'private' => true,
            'permissions' => ['admin' => true, 'push' => true, 'pull' => true],
        ]]);

    $this->actingAs($admin)
        ->get(route('admin.website-repositories.create', $website))
        ->assertOk()
        ->assertSee('role="combobox"', false)
        ->assertSee('data-searchable-select-option', false)
        ->assertSee('acme/marketing')
        ->assertSee('name="repository"', false)
        ->assertSee('value="'.$installation->id.':456"', false);
});

it('snapshots selected audit findings into one remediation request', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $website = Website::factory()->create();
    WebsiteRepository::factory()->for($website)->create();
    $report = WebsiteHealthReport::factory()->for($website)->create([
        'checks' => [
            ['category' => 'seo', 'key' => 'page_title', 'label' => 'Page title', 'status' => 'failed', 'message' => 'Missing title.'],
            ['category' => 'availability', 'key' => 'https', 'label' => 'HTTPS', 'status' => 'passed', 'message' => 'HTTPS enabled.'],
        ],
    ]);
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'checks' => [
            ['key' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'message' => 'Missing description.'],
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.remediation-runs.store', [$website, $report]), [
            'findings' => [
                'site:seo:page_title',
                'page:'.$page->id.':meta_description',
            ],
        ])
        ->assertRedirect(route('admin.website-health-reports.show', [$website, $report]));

    $run = RemediationRun::query()->sole();
    expect($run->status)->toBe(RemediationRun::STATUS_AWAITING_RUNNER)
        ->and($run->findings)->toHaveCount(2)
        ->and($run->findings[0]['message'])->toBe('Missing title.');
    Queue::assertPushed(StartCopilotRemediation::class, fn ($job) => $job->run->is($run));
});

it('starts a Copilot task with an audit prompt and schedules synchronization', function (): void {
    Queue::fake([SyncCopilotRemediation::class]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $website = Website::factory()->create(['name' => 'Acme Website']);
    $repository = WebsiteRepository::factory()->for($website)->create(['full_name' => 'acme/site']);
    $report = WebsiteHealthReport::factory()->for($website)->create();
    $run = RemediationRun::factory()
        ->for($report, 'report')
        ->for($repository, 'repository')
        ->for($admin, 'requester')
        ->create(['findings' => [[
            'scope' => 'site',
            'category' => 'seo',
            'key' => 'page_title',
            'label' => 'Page title',
            'status' => 'failed',
            'message' => 'Missing title.',
        ]]]);
    $copilot = mock(CopilotAgentClient::class);
    $copilot->shouldReceive('startTask')
        ->once()
        ->withArgs(fn ($authorization, $selectedRepository, string $prompt) => $authorization->user_id === $admin->id
            && $selectedRepository->is($repository)
            && str_contains($prompt, 'Missing title.'))
        ->andReturn([
            'id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'html_url' => 'https://github.com/acme/site/copilot/tasks/task-id',
            'state' => 'queued',
        ]);

    (new StartCopilotRemediation($run))->handle($copilot, app(RemediationPromptGenerator::class));

    expect($run->fresh()->status)->toBe(RemediationRun::STATUS_RUNNING)
        ->and($run->fresh()->copilot_task_id)->toBe('a1b2c3d4-e5f6-7890-abcd-ef1234567890')
        ->and($run->fresh()->prompt)->toContain('untrusted audit data');
    Queue::assertPushed(SyncCopilotRemediation::class);
});

it('links a completed Copilot task to its pull request', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $repository = WebsiteRepository::factory()->create(['full_name' => 'acme/site']);
    $run = RemediationRun::factory()
        ->for($repository, 'repository')
        ->for($admin, 'requester')
        ->create([
            'status' => RemediationRun::STATUS_RUNNING,
            'copilot_task_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'started_at' => now()->subMinute(),
        ]);
    $copilot = mock(CopilotAgentClient::class);
    $copilot->shouldReceive('task')->once()->andReturn([
        'state' => 'completed',
        'artifacts' => [['provider' => 'github', 'type' => 'pull', 'data' => ['id' => 42]]],
    ]);

    (new SyncCopilotRemediation($run))->handle($copilot, mock(GithubAppClient::class));

    expect($run->fresh()->status)->toBe(RemediationRun::STATUS_PULL_REQUEST_OPEN)
        ->and($run->fresh()->pull_request_number)->toBe(42)
        ->and($run->fresh()->pull_request_url)->toBe('https://github.com/acme/site/pull/42');
});

it('rejects unsigned GitHub webhooks and tracks merged remediation pull requests', function (): void {
    config(['services.github.webhook_secret' => 'test-secret']);
    $repository = WebsiteRepository::factory()->create(['repository_id' => 456]);
    $run = RemediationRun::factory()
        ->for($repository, 'repository')
        ->create([
            'pull_request_number' => 12,
            'status' => RemediationRun::STATUS_PULL_REQUEST_OPEN,
        ]);
    $payload = json_encode([
        'action' => 'closed',
        'repository' => ['id' => 456],
        'pull_request' => ['number' => 12, 'state' => 'closed', 'merged' => true],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', route('github.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_GITHUB_EVENT' => 'pull_request',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
    ], $payload)->assertUnauthorized();

    $this->call('POST', route('github.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_GITHUB_EVENT' => 'pull_request',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'test-secret'),
    ], $payload)->assertSuccessful();

    expect($run->fresh()->status)->toBe(RemediationRun::STATUS_COMPLETED)
        ->and($run->fresh()->merged_at)->not->toBeNull();
});

it('prevents website owners from changing GitHub repository connections', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $installation = GithubInstallation::factory()->create();

    $this->actingAs($owner)
        ->post(route('admin.website-repositories.store', $website), [
            'repository' => $installation->id.':456',
        ])
        ->assertForbidden();
});
