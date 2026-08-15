<?php

use App\Jobs\BuildWebsite;
use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteBuild;
use App\Services\CopilotAgentClient;
use App\Services\GithubOAuthClient;
use App\Services\NetlifyClient;
use App\Services\StaticWebsiteGenerator;
use App\Services\WebsiteBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

it('shows the website builder only to administrators', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create();

    $this->actingAs($admin)->get(route('admin.website-builder.create'))
        ->assertSuccessful()
        ->assertSee('Website builder')
        ->assertSee('Queue website build');

    $this->actingAs($user)->get(route('admin.website-builder.create'))->assertForbidden();
});

it('generates an Eleventy and Tailwind scaffold with a complete automated build brief', function (): void {
    $files = app(StaticWebsiteGenerator::class)->generate([
        'name' => 'Acme <Studio>',
        'sector' => 'Architecture',
        'description' => 'Thoughtful spaces & careful details.',
        'pages' => ['About us', 'Services'],
    ]);

    expect($files)->toHaveKeys(['package.json', 'eleventy.config.js', 'src/index.njk', 'src/contact.njk', 'src/assets/css/input.css', 'BUILD_SITE.md', 'netlify.toml'])
        ->and($files['package.json'])->toContain('@11ty/eleventy', '@tailwindcss/cli')
        ->and($files['src/assets/css/input.css'])->toContain('@import "tailwindcss"')
        ->and($files['src/contact.njk'])->toContain('action="https://sitewell.digizu.co.uk/submit"')
        ->and($files['src/contact.njk'])->toContain('name="_form_name" value="Contact form"')
        ->and($files['BUILD_SITE.md'])->toContain(
            'Architecture',
            'About us, Services',
            'Understand the business before designing',
            'what creates trust in this specific industry',
            'Do not default to a generic "modern website" aesthetic',
            'Final design sanity check',
            'npm run build',
        );
});

it('publishes a repository and creates the website and contact form records', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $owner = User::factory()->create();
    $authorization = GithubUserAuthorization::factory()->for($admin)->create(['github_login' => 'octocat']);
    $installation = GithubInstallation::factory()->create([
        'account_login' => 'acme',
        'repository_selection' => 'all',
        'permissions' => ['administration' => 'write', 'contents' => 'write'],
    ]);
    $github = mock(GithubOAuthClient::class);
    $github->shouldReceive('refreshInstallation')->once()->withArgs(fn ($selectedAuthorization, $selectedInstallation): bool => $selectedAuthorization->is($authorization) && $selectedInstallation->is($installation))->andReturn($installation);
    $github->shouldReceive('createRepository')
        ->once()
        ->withArgs(fn ($selectedAuthorization, string $account, string $repository, array $files): bool => $selectedAuthorization->is($authorization)
            && $account === 'acme'
            && $repository === 'acme-studio'
            && isset($files['package.json'], $files['BUILD_SITE.md'], $files['src/contact.njk']))
        ->andReturn([
            'id' => 456,
            'full_name' => 'acme/acme-studio',
            'default_branch' => 'main',
            'private' => false,
            'permissions' => ['push' => true],
        ]);
    $netlify = mock(NetlifyClient::class);
    $netlify->shouldReceive('deployRepository')->once()->withArgs(fn (array $repository): bool => $repository['full_name'] === 'acme/acme-studio')
        ->andReturn(['id' => 'site-123', 'url' => 'https://acme-studio.netlify.app', 'domain' => 'acme-studio.netlify.app']);
    mock(CopilotAgentClient::class)->shouldReceive('startTask')->once()
        ->withArgs(fn ($selectedAuthorization, $selectedRepository, string $prompt): bool => $selectedAuthorization->is($authorization)
            && $selectedRepository->full_name === 'acme/acme-studio'
            && str_contains($prompt, 'Architecture')
            && str_contains($prompt, 'Tailwind CSS v4'))
        ->andReturn(['id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'html_url' => 'https://github.test/copilot/tasks/123', 'state' => 'queued']);

    $website = app(WebsiteBuilder::class)->build([
        'name' => 'Acme Studio',
        'sector' => 'Architecture',
        'description' => 'Thoughtful spaces for modern teams.',
        'pages' => ['Home', 'About', 'Contact'],
        'repository_name' => 'acme-studio',
        'github_installation_id' => $installation->id,
        'user_id' => $owner->id,
    ], $admin);

    expect($website->user_id)->toBe($owner->id)
        ->and($website->primaryDomain()?->domain)->toBe('acme-studio.netlify.app')
        ->and($website->forms()->sole()->name)->toBe('Contact form')
        ->and($website->forms()->sole()->auto_discovered)->toBeFalse()
        ->and($website->repository()->sole()->full_name)->toBe('acme/acme-studio')
        ->and($website->copilot_build_task_state)->toBe('queued')
        ->and($website->copilot_build_task_url)->toBe('https://github.test/copilot/tasks/123');
});

it('normalizes the page list before starting a build', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $installation = GithubInstallation::factory()->create([
        'repository_selection' => 'all',
        'permissions' => ['administration' => 'write', 'contents' => 'write'],
    ]);
    Queue::fake();

    $this->actingAs($admin)->post(route('admin.website-builder.store'), [
        'name' => 'Acme',
        'sector' => 'Consulting',
        'description' => 'A clear and useful website.',
        'pages' => "Home\nAbout, Contact\nabout",
        'repository_name' => 'ACME-SITE',
        'github_installation_id' => $installation->id,
    ])->assertRedirect(route('admin.website-builder.create'))
        ->assertSessionHas('status');

    $build = WebsiteBuild::query()->sole();

    expect($build->requested_by)->toBe($admin->id)
        ->and($build->status)->toBe(WebsiteBuild::STATUS_QUEUED)
        ->and($build->details['pages'])->toBe(['Home', 'About', 'Contact'])
        ->and($build->details['repository_name'])->toBe('acme-site');
    Queue::assertPushed(BuildWebsite::class, fn (BuildWebsite $job): bool => $job->websiteBuildId === $build->id);
});

it('completes a queued website build in the background', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $build = WebsiteBuild::factory()->for($admin, 'requester')->create();
    $builder = mock(WebsiteBuilder::class);
    $builder->shouldReceive('build')->once()
        ->withArgs(fn (array $details, User $creator): bool => $details === $build->details && $creator->is($admin))
        ->andReturn($website);

    (new BuildWebsite($build->id))->handle($builder);

    $build->refresh();

    expect($build->status)->toBe(WebsiteBuild::STATUS_COMPLETED)
        ->and($build->website_id)->toBe($website->id)
        ->and($build->started_at)->not->toBeNull()
        ->and($build->completed_at)->not->toBeNull();
});

it('records a failed queued website build for the administrator', function (): void {
    $build = WebsiteBuild::factory()->create(['status' => WebsiteBuild::STATUS_RUNNING]);

    (new BuildWebsite($build->id))->failed(new RuntimeException('GitHub is unavailable.'));

    $build->refresh();

    expect($build->status)->toBe(WebsiteBuild::STATUS_FAILED)
        ->and($build->error)->toBe('GitHub is unavailable.')
        ->and($build->completed_at)->not->toBeNull();
});

it('creates a GitHub repository and commits the generated files', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.test/user/repos' => Http::response([
            'id' => 456,
            'full_name' => 'octocat/acme-site',
            'default_branch' => 'main',
            'private' => false,
        ], 201),
        'api.github.test/repos/octocat/acme-site/contents/*' => Http::response(['content' => ['sha' => 'abc']], 201),
    ]);
    $authorization = GithubUserAuthorization::factory()->create([
        'github_login' => 'octocat',
        'access_token' => 'github-token',
        'access_token_expires_at' => now()->addHour(),
    ]);

    $repository = app(GithubOAuthClient::class)->createRepository($authorization, 'octocat', 'acme-site', [
        'index.html' => '<h1>Acme</h1>',
        'contact.html' => '<form></form>',
    ]);

    expect($repository['full_name'])->toBe('octocat/acme-site');
    Http::assertSentCount(5);
    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.github.test/repos/octocat/acme-site/contents/index.html'
        && $request['content'] === base64_encode('<h1>Acme</h1>'));
});

it('reuses a repository from a partial earlier build and updates its files', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.test/user/repos' => Http::response([
            'message' => 'Repository creation failed.',
            'errors' => [['field' => 'name', 'message' => 'name already exists on this account']],
        ], 422),
        'api.github.test/repos/octocat/acme-site' => Http::response([
            'id' => 456,
            'full_name' => 'octocat/acme-site',
            'default_branch' => 'main',
            'private' => false,
        ]),
        'api.github.test/repos/octocat/acme-site/contents/index.html' => Http::sequence()
            ->push(['sha' => 'existing-file-sha'])
            ->push(['content' => ['sha' => 'updated-file-sha']], 200),
    ]);
    $authorization = GithubUserAuthorization::factory()->create([
        'github_login' => 'octocat',
        'access_token' => 'github-token',
        'access_token_expires_at' => now()->addHour(),
    ]);

    $repository = app(GithubOAuthClient::class)->createRepository($authorization, 'octocat', 'acme-site', [
        'index.html' => '<h1>Updated Acme</h1>',
    ]);

    expect($repository['full_name'])->toBe('octocat/acme-site');
    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.github.test/repos/octocat/acme-site/contents/index.html'
        && $request['sha'] === 'existing-file-sha'
        && $request['content'] === base64_encode('<h1>Updated Acme</h1>'));
});

it('shows the complete GitHub repository validation message when a repository cannot be reused', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.test/user/repos' => Http::response([
            'message' => 'Repository creation failed.',
            'errors' => [['field' => 'name', 'message' => 'name contains an invalid character']],
        ], 422),
        'api.github.test/repos/octocat/bad-name' => Http::response([], 404),
    ]);
    $authorization = GithubUserAuthorization::factory()->create([
        'github_login' => 'octocat',
        'access_token' => 'github-token',
        'access_token_expires_at' => now()->addHour(),
    ]);

    expect(fn () => app(GithubOAuthClient::class)->createRepository($authorization, 'octocat', 'bad-name', [
        'index.html' => '<h1>Acme</h1>',
    ]))->toThrow(RuntimeException::class, 'name contains an invalid character');
});

it('creates a Netlify site linked to the Eleventy repository', function (): void {
    config([
        'services.netlify.api_url' => 'https://api.netlify.test',
        'services.netlify.token' => 'netlify-token',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'api.netlify.test/sites' => Http::response([
            'id' => 'site-123',
            'ssl_url' => 'https://bright-site.netlify.app',
            'default_domain' => 'bright-site.netlify.app',
        ], 201),
    ]);

    $site = app(NetlifyClient::class)->deployRepository([
        'id' => 456,
        'full_name' => 'octocat/bright-site',
        'default_branch' => 'main',
        'private' => false,
    ]);

    expect($site)->toBe([
        'id' => 'site-123',
        'url' => 'https://bright-site.netlify.app',
        'domain' => 'bright-site.netlify.app',
    ]);
    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.netlify.test/sites'
        && $request->hasHeader('Authorization', 'Bearer netlify-token')
        && $request['repo']['repo'] === 'octocat/bright-site'
        && $request['repo']['cmd'] === 'npm run build'
        && $request['repo']['dir'] === '_site');
});

it('stops before creating remote resources when GitHub administration permission is missing', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $installation = GithubInstallation::factory()->create([
        'repository_selection' => 'all',
        'permissions' => ['contents' => 'write'],
    ]);
    $github = mock(GithubOAuthClient::class);
    $github->shouldReceive('refreshInstallation')->once()->andReturn($installation);
    $github->shouldNotReceive('createRepository');
    mock(NetlifyClient::class)->shouldNotReceive('deployRepository');

    expect(fn () => app(WebsiteBuilder::class)->build([
        'name' => 'Acme Studio',
        'sector' => 'Architecture',
        'description' => 'Thoughtful spaces for modern teams.',
        'pages' => ['Home', 'Contact'],
        'repository_name' => 'acme-studio',
        'github_installation_id' => $installation->id,
    ], $admin))->toThrow(RuntimeException::class, 'Administration and Contents both set to Read and write');
});

it('shows failed background website builds with their actionable error', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    WebsiteBuild::factory()->for($admin, 'requester')->create([
        'status' => WebsiteBuild::STATUS_FAILED,
        'error' => 'The Sitewell GitHub App needs updated permissions.',
    ]);

    $this->actingAs($admin)->get(route('admin.website-builder.create'))
        ->assertSuccessful()
        ->assertSee('Recent builds')
        ->assertSee('Failed')
        ->assertSee('The Sitewell GitHub App needs updated permissions.');
});

it('refreshes stored GitHub installation permissions from GitHub', function (): void {
    $installation = GithubInstallation::factory()->create([
        'repository_selection' => 'all',
        'permissions' => ['contents' => 'write'],
    ]);
    config(['services.github.api_url' => 'https://api.github.test']);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.test/user/installations*' => Http::response(['installations' => [[
            'id' => $installation->installation_id,
            'account' => ['id' => 987, 'login' => 'acme', 'type' => 'Organization'],
            'repository_selection' => 'all',
            'permissions' => ['administration' => 'write', 'contents' => 'write'],
            'suspended_at' => null,
        ]]]),
    ]);
    $authorization = GithubUserAuthorization::factory()->create([
        'access_token' => 'github-token',
        'access_token_expires_at' => now()->addHour(),
    ]);

    $refreshed = app(GithubOAuthClient::class)->refreshInstallation($authorization, $installation);

    expect($refreshed->account_login)->toBe('acme')
        ->and($refreshed->permissions['administration'])->toBe('write')
        ->and($refreshed->status)->toBe(GithubInstallation::STATUS_ACTIVE);
});

it('turns a GitHub repository creation 403 into an actionable error', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.test/user/repos' => Http::response([
            'message' => 'Resource not accessible by integration',
        ], 403),
    ]);
    $authorization = GithubUserAuthorization::factory()->create([
        'github_login' => 'octocat',
        'access_token' => 'github-token',
        'access_token_expires_at' => now()->addHour(),
    ]);

    expect(fn () => app(GithubOAuthClient::class)->createRepository($authorization, 'octocat', 'acme-site', [
        'index.html' => '<h1>Acme</h1>',
    ]))->toThrow(RuntimeException::class, 'organisation allows GitHub Apps to create repositories');
});

it('turns a GitHub contents 403 into an actionable retry message', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.test/user/repos' => Http::response([
            'id' => 456,
            'full_name' => 'octocat/acme-site',
            'default_branch' => 'main',
            'private' => false,
        ], 201),
        'api.github.test/repos/octocat/acme-site/contents/index.html' => Http::sequence()
            ->push([], 404)
            ->push(['message' => 'Resource not accessible by integration'], 403),
    ]);
    $authorization = GithubUserAuthorization::factory()->create([
        'github_login' => 'octocat',
        'access_token' => 'github-token',
        'access_token_expires_at' => now()->addHour(),
    ]);

    expect(fn () => app(GithubOAuthClient::class)->createRepository($authorization, 'octocat', 'acme-site', [
        'index.html' => '<h1>Acme</h1>',
    ]))->toThrow(RuntimeException::class, 'Contents” to Read and write');
});
