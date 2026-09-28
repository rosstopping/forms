<?php

use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\User;
use App\Models\Website;
use App\Services\GithubAppClient;
use App\Services\GithubCustomerRepositories;
use App\Services\GithubOAuthClient;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['services.github.app_slug' => 'digizuaudit']);
});

it('uses the pilot for every admin regardless of obsolete configuration across connection listing and saving', function (bool $enabled, bool $admin, bool $listed, bool $pilot): void {
    $user = User::factory()->create(['role' => $admin ? User::ROLE_ADMIN : User::ROLE_USER]);
    $website = Website::factory()->for($user, 'owner')->create();
    $installation = GithubInstallation::factory()->for($user, 'installer')->create(['installation_id' => 9876]);
    GithubUserAuthorization::factory()->for($user)->create();
    config([
        'copilot_sdk.customer_repositories_enabled' => $enabled,
        'copilot_sdk.customer_repository_admin_ids' => $listed ? [(string) $user->id] : [],
    ]);
    $repository = ['id' => 10, 'full_name' => 'sitewellross/test', 'private' => true, 'default_branch' => 'main', 'github_installation_id' => $installation->id, 'account_login' => 'sitewellross'];
    $legacy = mock(GithubAppClient::class);
    $customer = mock(GithubCustomerRepositories::class);
    if ($pilot) {
        $legacy->shouldNotReceive('repositories');
        $customer->shouldReceive('available')->once()->withArgs(fn (User $actor): bool => $actor->is($user))->andReturn(collect([$repository]));
        $customer->shouldReceive('selected')->once()->withArgs(fn (User $actor, GithubInstallation $selected, int $id): bool => $actor->is($user) && $selected->is($installation) && $id === 10)->andReturn($repository);
    } else {
        $customer->shouldNotReceive('available');
        $customer->shouldNotReceive('selected');
        $legacy->shouldReceive('repositories')->twice()->with(9876)->andReturn([$repository]);
    }
    $response = $this->actingAs($user)->get(route('admin.github.connect', $website))->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect(str_starts_with($query['state'], 'repository_'))->toBe($pilot);
    if (! $pilot) {
        expect(json_decode(Crypt::decryptString($query['state']), true)['user_id'])->toBe($user->id);
    }
    $this->get(route('admin.website-repositories.create', $website))->assertSuccessful()->assertSee('sitewellross/test');
    $this->post(route('admin.website-repositories.store', $website), ['repository' => $installation->id.':10'])->assertSessionHasNoErrors()->assertRedirect();
    expect($website->repository()->sole()->full_name)->toBe('sitewellross/test');
    Http::assertNothingSent();
})->with([
    'selected admin' => [true, true, true, true],
    'admin without allowlist' => [true, true, false, true],
    'customer in list' => [true, false, true, false],
    'ordinary customer' => [true, false, false, false],
    'admin with obsolete flag disabled' => [false, true, true, true],
]);

it('rejects pilot callbacks if access is removed during consent', function (string $change): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($admin, 'owner')->create();
    config(['copilot_sdk.customer_repositories_enabled' => true, 'copilot_sdk.customer_repository_admin_ids' => [(string) $admin->id]]);
    $response = $this->actingAs($admin)->get(route('admin.github.connect', $website));
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    $this->withCookie(config('session.cookie'), session()->getId());
    if ($change === 'role') {
        $admin->update(['role' => User::ROLE_USER]);
    } else {
        $this->actingAs(User::factory()->create());
    }
    $this->get(route('admin.github.callback', ['state' => $query['state'], 'installation_id' => 9876, 'code' => 'test-code']))->assertForbidden();
    expect(GithubInstallation::count())->toBe(0);
    Http::assertNothingSent();
})->with(['role', 'different-user']);

it('rejects pilot callbacks for a non-admin user without invoking either OAuth flow', function (): void {
    $user = User::factory()->create();
    config(['copilot_sdk.customer_repositories_enabled' => true, 'copilot_sdk.customer_repository_admin_ids' => [(string) $user->id]]);
    $this->actingAs($user)->get(route('admin.github.callback', ['state' => 'repository_'.str_repeat('a', 64), 'code' => 'test-code', 'installation_id' => 9876]))->assertForbidden();
    Http::assertNothingSent();
});

it('completes legacy OAuth for customers while the admin pilot is enabled', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create();
    $installation = GithubInstallation::factory()->for($user, 'installer')->create();
    $authorization = GithubUserAuthorization::factory()->for($user)->create();
    config(['copilot_sdk.customer_repositories_enabled' => true, 'copilot_sdk.customer_repository_admin_ids' => [(string) $user->id]]);
    $state = Crypt::encryptString(json_encode(['user_id' => $user->id, 'website_id' => $website->id, 'installation_id' => $installation->id], JSON_THROW_ON_ERROR));
    $oauth = mock(GithubOAuthClient::class);
    $oauth->shouldReceive('authorize')->once()->andReturn($authorization);
    $oauth->shouldReceive('canAccessInstallation')->once()->with($authorization, $installation->installation_id)->andReturn(true);
    $this->actingAs($user)->get(route('admin.github.callback', ['state' => $state, 'code' => 'code']))->assertRedirectToRoute('admin.website-repositories.create', $website);
    Http::assertNothingSent();
});
