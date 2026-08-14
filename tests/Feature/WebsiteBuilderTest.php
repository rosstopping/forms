<?php

use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\User;
use App\Models\Website;
use App\Services\GithubOAuthClient;
use App\Services\NetlifyClient;
use App\Services\StaticWebsiteGenerator;
use App\Services\WebsiteBuilder;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\mock;

it('shows the website builder only to administrators', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create();

    $this->actingAs($admin)->get(route('admin.website-builder.create'))
        ->assertSuccessful()
        ->assertSee('Website builder')
        ->assertSee('Build and publish website');

    $this->actingAs($user)->get(route('admin.website-builder.create'))->assertForbidden();
});

it('generates every requested page and the Sitewell lead form safely', function (): void {
    $files = app(StaticWebsiteGenerator::class)->generate([
        'name' => 'Acme <Studio>',
        'sector' => 'Architecture',
        'description' => 'Thoughtful spaces & careful details.',
        'pages' => ['About us', 'Services'],
    ]);

    expect($files)->toHaveKeys(['index.html', 'about-us.html', 'services.html', 'contact.html', 'styles.css'])
        ->and($files['index.html'])->toContain('Acme &lt;Studio&gt;')
        ->and($files['contact.html'])->toContain('action="https://sitewell.digizu.co.uk/submit"')
        ->and($files['contact.html'])->toContain('name="_form_name" value="Contact form"')
        ->and($files['contact.html'])->toContain('name="_honeypot"')
        ->and($files['contact.html'])->toContain('name="name" required')
        ->and($files['contact.html'])->toContain('name="email" required')
        ->and($files['contact.html'])->toContain('name="message"');
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
            && isset($files['index.html'], $files['contact.html']))
        ->andReturn([
            'id' => 456,
            'full_name' => 'acme/acme-studio',
            'default_branch' => 'main',
            'private' => false,
            'permissions' => ['push' => true],
        ]);
    $netlify = mock(NetlifyClient::class);
    $netlify->shouldReceive('deploy')->once()->withArgs(fn (array $files): bool => isset($files['contact.html']))
        ->andReturn(['id' => 'site-123', 'url' => 'https://acme-studio.netlify.app', 'domain' => 'acme-studio.netlify.app']);

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
        ->and($website->repository()->sole()->full_name)->toBe('acme/acme-studio');
});

it('normalizes the page list before starting a build', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $installation = GithubInstallation::factory()->create([
        'repository_selection' => 'all',
        'permissions' => ['administration' => 'write', 'contents' => 'write'],
    ]);
    $website = Website::factory()->create();
    mock(WebsiteBuilder::class)->shouldReceive('build')->once()
        ->withArgs(fn (array $details, User $creator): bool => $details['pages'] === ['Home', 'About', 'Contact']
            && $details['repository_name'] === 'acme-site'
            && $creator->is($admin))
        ->andReturn($website);

    $this->actingAs($admin)->post(route('admin.website-builder.store'), [
        'name' => 'Acme',
        'sector' => 'Consulting',
        'description' => 'A clear and useful website.',
        'pages' => "Home\nAbout, Contact\nabout",
        'repository_name' => 'ACME-SITE',
        'github_installation_id' => $installation->id,
    ])->assertRedirect(route('admin.websites.show', $website));
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
    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.github.test/repos/octocat/acme-site/contents/index.html'
        && $request['content'] === base64_encode('<h1>Acme</h1>'));
});

it('uploads a zip deployment and returns the Netlify development link', function (): void {
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

    $site = app(NetlifyClient::class)->deploy(['index.html' => '<h1>Bright Site</h1>']);

    expect($site)->toBe([
        'id' => 'site-123',
        'url' => 'https://bright-site.netlify.app',
        'domain' => 'bright-site.netlify.app',
    ]);
    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.netlify.test/sites'
        && $request->hasHeader('Authorization', 'Bearer netlify-token')
        && $request->hasHeader('Content-Type', 'application/zip'));
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
    mock(NetlifyClient::class)->shouldNotReceive('deploy');

    expect(fn () => app(WebsiteBuilder::class)->build([
        'name' => 'Acme Studio',
        'sector' => 'Architecture',
        'description' => 'Thoughtful spaces for modern teams.',
        'pages' => ['Home', 'Contact'],
        'repository_name' => 'acme-studio',
        'github_installation_id' => $installation->id,
    ], $admin))->toThrow(RuntimeException::class, 'Administration set to Read and write');
});

it('submits an installation with stale permissions and shows the actionable permission error', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $installation = GithubInstallation::factory()->create([
        'repository_selection' => 'all',
        'permissions' => ['contents' => 'write'],
    ]);
    $github = mock(GithubOAuthClient::class);
    $github->shouldReceive('refreshInstallation')->once()->andReturn($installation);
    $github->shouldNotReceive('createRepository');
    mock(NetlifyClient::class)->shouldNotReceive('deploy');

    $this->actingAs($admin)->get(route('admin.website-builder.create'))
        ->assertSuccessful()
        ->assertSee('value="'.$installation->id.'"', false)
        ->assertSee('permission update required');

    $this->post(route('admin.website-builder.store'), [
        'name' => 'Acme Studio',
        'sector' => 'Architecture',
        'description' => 'Thoughtful spaces for modern teams.',
        'pages' => "Home\nContact",
        'repository_name' => 'acme-studio',
        'github_installation_id' => $installation->id,
    ])->assertSessionHasErrors([
        'builder' => 'The Sitewell GitHub App needs Repository permissions → Administration set to Read and write. Update the GitHub App, approve the new permission for this installation, then try again.',
    ])->assertSessionDoesntHaveErrors('github_installation_id');
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
