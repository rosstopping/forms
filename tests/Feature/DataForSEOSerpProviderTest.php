<?php

use App\Data\SerpResult;
use App\Services\CachedSerpProvider;
use App\Services\DataForSEO\Data\DataForSEOResponse;
use App\Services\DataForSEO\DataForSEOClient;
use App\Services\DataForSEO\DataForSEOLocationResolver;
use App\Services\DataForSEO\DataForSEOSerpProvider;
use App\Services\GoogleSerpRedirectResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('maps DataForSEO organic results into the provider-neutral response', function () {
    config()->set('services.dataforseo.language_code', 'en');
    $client = Mockery::mock(DataForSEOClient::class);
    $locations = Mockery::mock(DataForSEOLocationResolver::class);
    $locations->shouldReceive('resolve')->once()->with('Barnsley')->andReturn(1006787);
    $client->shouldReceive('post')->once()->with(DataForSEOSerpProvider::ENDPOINT, Mockery::on(fn (array $task): bool => $task === [
        'keyword' => 'roofer barnsley',
        'location_code' => 1006787,
        'language_code' => 'en',
        'device' => 'desktop',
        'depth' => 100,
    ]))->andReturn(new DataForSEOResponse(DataForSEOSerpProvider::ENDPOINT, [[
        'items' => [
            ['type' => 'organic', 'rank_group' => 38, 'url' => 'https://acme.example/roofing', 'domain' => 'acme.example', 'title' => 'Acme Roofing', 'description' => 'Roofing in Barnsley', 'website_name' => 'Acme Roofing Ltd'],
            ['type' => 'paid', 'rank_group' => 1, 'url' => 'https://ads.example', 'domain' => 'ads.example'],
        ],
    ]], 0.02, 2, 'task-123'));

    $response = (new DataForSEOSerpProvider($client, $locations))->search('roofer barnsley', 'Barnsley');

    expect($response->provider)->toBe('dataforseo')
        ->and($response->cost)->toBe(0.02)
        ->and($response->results)->toHaveCount(1)
        ->and($response->results->first()->position)->toBe(38)
        ->and($response->results->first()->websiteName)->toBe('Acme Roofing Ltd');
});

it('resolves Google wrappers before domain matching without visiting destination sites', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://www.google.com/goto?url=opaque-vvip' => Http::response('', 302, ['Location' => 'https://vvipeventszante.com/zante-event-packages']),
        'https://www.google.com/goto?url=opaque-alist' => Http::response('', 302, ['Location' => 'https://a-listzante.com/']),
    ]);
    $results = app(GoogleSerpRedirectResolver::class)->resolve(collect([
        new SerpResult(2, 'https://google.com/goto?url=opaque-vvip', 'google.com', 'VVIP'),
        new SerpResult(5, 'https://google.com/goto?url=opaque-alist', 'google.com'),
        new SerpResult(8, 'https://example.com/', 'example.com'),
    ]));
    expect($results->pluck('domain')->all())->toBe(['vvipeventszante.com', 'a-listzante.com', 'example.com'])
        ->and($results->pluck('position')->all())->toBe([2, 5, 8])
        ->and($results->first()->title)->toBe('VVIP');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://www.google.com/goto?url=opaque-vvip');
});

it('preserves unresolved wrappers and does not trust unsafe destinations', function (string $location) {
    Http::preventStrayRequests();
    Http::fake(['https://www.google.com/goto*' => Http::response('', 302, ['Location' => $location])]);
    $result = new SerpResult(1, 'https://google.com/goto?url=opaque', 'google.com');
    expect(app(GoogleSerpRedirectResolver::class)->resolve(collect([$result]))->first())->toBe($result);
    Http::assertSentCount(1);
})->with(['http://127.0.0.1/', 'http://169.254.169.254/', 'https://user:pass@example.com/', 'https://example.com:8080/', 'https://www.google.com/goto?url=another', 'javascript:alert(1)']);

it('repairs cached wrappers while preserving freshness and avoiding another paid request', function () {
    config()->set('cache.default', 'array');
    $key = 'target-keyword-serp:'.hash('sha256', json_encode(['test keyword', 2826, 'en', 'desktop', 100]));
    Cache::put($key, [
        'provider' => 'dataforseo', 'endpoint' => DataForSEOSerpProvider::ENDPOINT, 'cost' => .02, 'task_id' => 'old-task', 'fetched_at' => '2026-11-03T10:00:00+00:00',
        'results' => [['position' => 3, 'url' => 'https://google.com/url?q=https%3A%2F%2Fa-listzante.com%2F', 'domain' => 'google.com', 'title' => null, 'description' => null, 'website_name' => null]],
    ]);
    Http::preventStrayRequests();
    $provider = Mockery::mock(DataForSEOSerpProvider::class);
    $response = (new CachedSerpProvider($provider))->searchForMarket('test keyword', 2826, 'en');
    expect($response->results->first()->domain)->toBe('a-listzante.com')->and($response->cached)->toBeTrue()
        ->and($response->fetchedAt)->toBe('2026-11-03T10:00:00+00:00')->and($response->cost)->toBe(0.0);
    Http::assertNothingSent();
});

it('leaves failed redirect lookups unresolved without fetching another host', function () {
    Http::preventStrayRequests();
    Http::fake(['https://www.google.com/goto*' => Http::failedConnection()]);
    $result = new SerpResult(1, 'https://google.com/goto?url=opaque', 'google.com');
    expect(app(GoogleSerpRedirectResolver::class)->resolve(collect([$result]))->first())->toBe($result);
});
