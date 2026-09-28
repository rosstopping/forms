<?php

use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\User;
use App\Models\Website;
use App\Services\GithubOAuthClient;
use App\Services\GithubRepositoryPilot;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.github.app_id' => '123',
        'services.github.app_slug' => 'sitewell-test',
        'services.github.client_id' => 'client',
        'services.github.client_secret' => 'secret',
        'services.github.api_url' => 'https://api.github.test',
        'services.github.oauth_url' => 'https://github.test/login/oauth',
    ]);
    $this->mock(GithubRepositoryPilot::class)->shouldReceive('enabledFor')->andReturn(true);
    Http::preventStrayRequests();
});

function customerGithubInstallation(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 9876, 'app_id' => 123,
        'account' => ['id' => 456, 'login' => 'customer-org', 'type' => 'Organization'],
        'repository_selection' => 'selected',
        'permissions' => ['contents' => 'write', 'pull_requests' => 'write'],
        'suspended_at' => null,
    ], $overrides);
}

function customerGithubRepository(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 10, 'full_name' => 'customer-org/site', 'default_branch' => 'main',
        'private' => true, 'permissions' => ['push' => true, 'pull' => true],
    ], $overrides);
}

function githubStateFromRedirect($response): string
{
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    test()->withCookie(config('session.cookie'), session()->getId());

    return $query['state'];
}

function fakeCustomerGithub(array $installations, array $repositories = []): void
{
    Http::fake([
        'github.test/login/oauth/access_token' => Http::response(['access_token' => 'customer-token']),
        'api.github.test/user' => Http::response(['id' => 33, 'login' => 'customer']),
        'api.github.test/user/installations/9876/repositories*' => Http::response(['repositories' => $repositories]),
        'api.github.test/user/installations*' => Http::response(['installations' => $installations]),
    ]);
}

it('connects a customer installation only after verifying the customers GitHub identity', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    fakeCustomerGithub([customerGithubInstallation()]);
    $this->actingAs($owner);
    $state = githubStateFromRedirect($this->get(route('admin.github.connect', $website))->assertRedirect());
    $oauthState = githubStateFromRedirect($this->get(route('admin.github.callback', ['state' => $state, 'installation_id' => 9876]))->assertRedirect());
    expect(GithubInstallation::count())->toBe(0);
    Http::assertNothingSent();

    $this->get(route('admin.github.callback', ['state' => $oauthState, 'code' => 'code']))
        ->assertRedirectToRoute('admin.website-repositories.create', $website);
    expect(GithubInstallation::sole()->installed_by)->toBe($owner->id);
    $this->get(route('admin.github.callback', ['state' => $oauthState, 'code' => 'code']))->assertForbidden();
    Http::assertSentCount(3);
});

it('does not let an installation callback claim another customers installation', function (): void {
    $owner = User::factory()->create();
    $original = User::factory()->create();
    $installation = GithubInstallation::factory()->for($original, 'installer')->create(['installation_id' => 9876]);
    $website = Website::factory()->for($owner, 'owner')->create();
    fakeCustomerGithub([]);
    $state = githubStateFromRedirect($this->actingAs($owner)->get(route('admin.github.connect', $website)));
    $this->get(route('admin.github.callback', ['state' => $state, 'installation_id' => 9876, 'code' => 'code']))->assertForbidden();
    expect($installation->fresh()->installed_by)->toBe($original->id);
});

it('expires connection states and binds them to the initiating user', function (string $scenario): void {
    Http::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $state = githubStateFromRedirect($this->actingAs($owner)->get(route('admin.github.connect', $website)));
    if ($scenario === 'expired') {
        $this->travel(16)->minutes();
    } else {
        $this->actingAs(User::factory()->create());
    }
    $this->get(route('admin.github.callback', ['state' => $state, 'installation_id' => 9876, 'code' => 'code']))->assertForbidden();
    Http::assertNothingSent();
})->with(['expired', 'other-user']);

it('lists only writable repositories visible to the customer even for a shared installation', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    GithubUserAuthorization::factory()->for($owner)->create(['access_token' => 'customer-token']);
    $installation = GithubInstallation::factory()->create(['installation_id' => 9876]);
    $originalInstaller = $installation->installed_by;
    fakeCustomerGithub([customerGithubInstallation()], [
        customerGithubRepository(),
        customerGithubRepository(['id' => 20, 'full_name' => 'customer-org/read-only', 'permissions' => ['push' => false]]),
    ]);
    $this->actingAs($owner)->get(route('admin.website-repositories.create', $website))
        ->assertSuccessful()->assertSee('customer-org/site')->assertDontSee('customer-org/read-only');
    expect($installation->fresh()->installed_by)->toBe($originalInstaller);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/user/installations/9876/repositories') && $request->hasHeader('Authorization', 'Bearer customer-token'));
});

it('rechecks the customers permissions when saving and never trusts a submitted repository ID', function (bool $canWrite): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    GithubUserAuthorization::factory()->for($owner)->create();
    $installation = GithubInstallation::factory()->create(['installation_id' => 9876]);
    fakeCustomerGithub([customerGithubInstallation()], [customerGithubRepository(['permissions' => ['push' => $canWrite]])]);
    $response = $this->actingAs($owner)->post(route('admin.website-repositories.store', $website), ['repository' => $installation->id.':10']);
    if ($canWrite) {
        $response->assertSessionHasNoErrors()->assertRedirect();
        expect($website->repository()->sole()->full_name)->toBe('customer-org/site');
    } else {
        $response->assertSessionHasErrors('repository');
        expect($website->repository()->exists())->toBeFalse();
    }
})->with([true, false]);

it('excludes suspended foreign-app and insufficient-permission installations', function (array $overrides): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    GithubUserAuthorization::factory()->for($owner)->create();
    fakeCustomerGithub([customerGithubInstallation($overrides)]);
    $this->actingAs($owner)->get(route('admin.website-repositories.create', $website))->assertSuccessful()->assertViewHas('repositories', fn ($items): bool => $items->isEmpty());
    expect(GithubInstallation::count())->toBe(0);
})->with([
    'suspended' => [['suspended_at' => '2026-09-01T00:00:00Z']],
    'different app' => [['app_id' => 999]],
    'read only app' => [['permissions' => ['contents' => 'read']]],
]);

it('does not retire a shared installation when one user loses access', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    GithubUserAuthorization::factory()->for($owner)->create();
    $installation = GithubInstallation::factory()->create(['installation_id' => 9876]);
    fakeCustomerGithub([customerGithubInstallation()]);
    Http::fake(['api.github.test/user/installations/9876/repositories*' => Http::response([], 403)]);
    $this->actingAs($owner)->post(route('admin.website-repositories.store', $website), ['repository' => $installation->id.':10'])->assertSessionHasErrors('repository');
    expect($installation->fresh()->status)->toBe(GithubInstallation::STATUS_ACTIVE);
});

it('does not transfer a shared installation when another authorised user connects', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $installation = GithubInstallation::factory()->create(['installation_id' => 9876]);
    $originalInstaller = $installation->installed_by;
    fakeCustomerGithub([customerGithubInstallation()]);
    $state = githubStateFromRedirect($this->actingAs($owner)->get(route('admin.github.connect', $website)));
    $this->get(route('admin.github.callback', ['state' => $state, 'installation_id' => 9876, 'code' => 'code']))->assertRedirect();
    expect($installation->fresh()->installed_by)->toBe($originalInstaller);
});

it('rejects connection state reused from another browser session', function (): void {
    Http::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $state = githubStateFromRedirect($this->actingAs($owner)->get(route('admin.github.connect', $website)));
    $this->withCookie(config('session.cookie'), str_repeat('a', 40));
    $this->get(route('admin.github.callback', ['state' => $state, 'installation_id' => 9876, 'code' => 'code']))->assertForbidden();
    Http::assertNothingSent();
});

it('checks website management permission again on callback', function (): void {
    Http::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $state = githubStateFromRedirect($this->actingAs($owner)->get(route('admin.github.connect', $website)));
    $website->update(['user_id' => User::factory()->create()->id]);
    $this->get(route('admin.github.callback', ['state' => $state, 'installation_id' => 9876, 'code' => 'code']))->assertForbidden();
    Http::assertNothingSent();
});

it('does not give global admins repository access through another persons GitHub identity', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    GithubUserAuthorization::factory()->for($admin)->create();
    GithubInstallation::factory()->create();
    fakeCustomerGithub([]);
    $this->actingAs($admin)->get(route('admin.website-repositories.create', $website))->assertSuccessful()
        ->assertViewHas('repositories', fn ($items): bool => $items->isEmpty());
});

it('paginates user-visible repositories using the customer token', function (): void {
    $authorization = GithubUserAuthorization::factory()->create(['access_token' => 'user-token']);
    $firstPage = collect(range(1, 100))->map(fn (int $id): array => customerGithubRepository(['id' => $id]))->all();
    Http::fake([
        'api.github.test/user/installations/9876/repositories*' => Http::sequence()
            ->push(['repositories' => $firstPage])->push(['repositories' => [customerGithubRepository(['id' => 101])]]),
    ]);
    $repositories = app(GithubOAuthClient::class)->installationRepositories($authorization, 9876);
    expect($repositories)->toHaveCount(101);
    Http::assertSent(fn ($request): bool => $request['page'] === 2 && $request->hasHeader('Authorization', 'Bearer user-token'));
});

it('offers reconnecting without deleting a shared installation when listing access is revoked', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    GithubUserAuthorization::factory()->for($owner)->create();
    $installation = GithubInstallation::factory()->create(['installation_id' => 9876]);
    Http::fake(['api.github.test/user/installations*' => Http::response([], 401)]);
    $this->actingAs($owner)->get(route('admin.website-repositories.create', $website))
        ->assertRedirectToRoute('admin.websites.section', [$website, 'content'])->assertSessionHas('error');
    expect($installation->fresh()->status)->toBe(GithubInstallation::STATUS_ACTIVE);
});
