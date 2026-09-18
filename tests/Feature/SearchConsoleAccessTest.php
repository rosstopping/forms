<?php

use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Services\SearchConsoleClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config(['services.google.search_console_url' => 'https://search-console.test']);
    Http::preventStrayRequests();
    Sleep::fake();
});

test('property permission failures persist a warning for only the affected connection', function (string $method): void {
    $connection = SearchConsoleConnection::factory()->create();
    $otherConnection = SearchConsoleConnection::factory()->create();
    Http::fake(['search-console.test/*' => Http::response(['error' => [
        'message' => 'User does not have sufficient permission for site.',
        'errors' => [['reason' => 'forbidden']],
    ]], 403)]);
    $client = app(SearchConsoleClient::class);

    expect(fn () => match ($method) {
        'performance' => $client->performance($connection),
        'weekly' => $client->weeklyPerformance($connection, now()->subDays(14), now()->subDays(8)),
        'impact' => $client->impactPerformance($connection, now()->subDays(14), now()->subDays(8), ['https://example.test/']),
    })->toThrow(RequestException::class);

    expect($connection->fresh()->access_denied_at)->not->toBeNull()
        ->and($otherConnection->fresh()->access_denied_at)->toBeNull();
})->with(['performance', 'weekly', 'impact']);

test('temporary failures do not create or clear a property access warning', function (int $status, string $reason, bool $alreadyDenied): void {
    $connection = SearchConsoleConnection::factory()->create(['access_denied_at' => $alreadyDenied ? now()->subHour() : null]);
    $deniedAt = $connection->access_denied_at;
    Http::fake(['search-console.test/*' => Http::response(['error' => ['errors' => [['reason' => $reason]]]], $status)]);

    expect(fn () => app(SearchConsoleClient::class)->performance($connection))->toThrow(RequestException::class)
        ->and($connection->fresh()->access_denied_at?->toIso8601String())->toBe($deniedAt?->toIso8601String());
})->with([
    [403, 'rateLimitExceeded', false],
    [403, 'dailyLimitExceeded', false],
    [429, 'rateLimitExceeded', false],
    [500, 'backendError', false],
    [503, 'backendError', true],
]);

test('only a successful read of the selected property clears its access warning', function (): void {
    $connection = SearchConsoleConnection::factory()->create(['access_denied_at' => now()->subHour()]);
    Http::fake(['search-console.test/*' => Http::response(['rows' => [], 'siteEntry' => []])]);
    $client = app(SearchConsoleClient::class);

    $client->sites($connection);
    expect($connection->fresh()->access_denied_at)->not->toBeNull();

    expect($client->performance($connection))->toBe([])
        ->and($connection->fresh()->access_denied_at)->toBeNull();
});

test('the access warning is visible on the affected site and its audit but not another site', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['is_active' => false, 'name' => 'Rustic Pet Furniture']);
    $connection = SearchConsoleConnection::factory()->for($website)->create(['access_denied_at' => now()->subHour()]);
    $report = WebsiteHealthReport::factory()->for($website)->create();
    $otherWebsite = Website::factory()->create(['is_active' => false, 'name' => 'Luxury Casa Rentals']);

    $this->actingAs($admin)->get(route('admin.websites.section', [$website, 'search']))
        ->assertSuccessful()
        ->assertSee('Search Console access needs attention')
        ->assertSee('Check ownership verification and account permissions')
        ->assertSee($connection->property_url)
        ->assertSee('Reconnect Google');
    $this->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()->assertSee('Search Console access needs attention');
    $this->get(route('admin.websites.section', [$otherWebsite, 'search']))
        ->assertSuccessful()->assertDontSee('Search Console access needs attention');

    $connection->forceFill(['access_denied_at' => null])->save();
    $this->get(route('admin.websites.section', [$website, 'search']))
        ->assertSuccessful()->assertDontSee('Search Console access needs attention');
    Http::assertNothingSent();
});

test('a permission failure while opening a site shows the notice in the same response', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    SearchConsoleConnection::factory()->for($website)->create();
    Http::fake(['search-console.test/*' => Http::response(['error' => ['errors' => [['reason' => 'insufficientPermissions']]]], 403)]);

    $this->actingAs($admin)->get(route('admin.websites.section', [$website, 'search']))
        ->assertSuccessful()->assertSee('Search Console access needs attention');
});

test('viewers see the access notice without a reconnect control', function (): void {
    $viewer = User::factory()->create();
    $website = Website::factory()->create(['is_active' => false]);
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    SearchConsoleConnection::factory()->for($website)->create(['access_denied_at' => now()]);

    $this->actingAs($viewer)->get(route('admin.websites.section', [$website, 'search']))
        ->assertSuccessful()
        ->assertSee('Search Console access needs attention')
        ->assertDontSee('Reconnect Google');
});

test('an old property response cannot change the newly selected property access state', function (): void {
    $connection = SearchConsoleConnection::factory()->create();
    Http::fake(function () use ($connection) {
        SearchConsoleConnection::query()->whereKey($connection->id)->update(['property_url' => 'sc-domain:new.example']);

        return Http::response(['error' => ['errors' => [['reason' => 'forbidden']]]], 403);
    });

    expect(fn () => app(SearchConsoleClient::class)->performance($connection))->toThrow(RequestException::class)
        ->and($connection->fresh()->access_denied_at)->toBeNull();
});
