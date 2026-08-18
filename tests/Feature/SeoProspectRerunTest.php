<?php

use App\Contracts\SerpProvider;
use App\Data\SerpResult;
use App\Data\SerpSearchResponse;
use App\Jobs\DiscoverSeoProspects;
use App\Models\ExternalApiUsage;
use App\Models\SeoProspectSearch;
use App\Models\User;
use App\Services\CachedSerpProvider;
use App\Services\DataForSEO\DataForSEOSerpProvider;
use App\Services\PixelUrlNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

it('creates a dated SEO search rerun without changing the previous result set', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $search = SeoProspectSearch::factory()->for($admin, 'owner')->create([
        'status' => 'analyzed',
        'api_cost' => 0.04,
    ]);

    $this->actingAs($admin)->post(route('admin.seo-prospect-searches.rerun', $search), [
        'refresh_serps' => false,
    ])->assertRedirect();

    $rerun = SeoProspectSearch::query()->whereKeyNot($search->id)->sole();
    expect($rerun->rerun_of_id)->toBe($search->id)
        ->and($rerun->keywords)->toBe($search->keywords)
        ->and($rerun->status)->toBe('pending')
        ->and($rerun->estimated_api_cost)->toBe('0.040000')
        ->and($search->fresh()->api_cost)->toBe('0.040000');
    Queue::assertPushed(DiscoverSeoProspects::class, fn (DiscoverSeoProspects $job): bool => $job->search->is($rerun));
});

it('will not rerun a search while it is still active', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $search = SeoProspectSearch::factory()->for($admin, 'owner')->create(['status' => 'analyzing']);

    $this->actingAs($admin)->post(route('admin.seo-prospect-searches.rerun', $search))->assertUnprocessable();

    expect(SeoProspectSearch::query()->count())->toBe(1);
    Queue::assertNothingPushed();
});

it('caches identical provider-neutral SERP responses as array payloads', function (): void {
    Cache::flush();
    config()->set('services.dataforseo.serp_cache_days', 7);
    $provider = Mockery::mock(DataForSEOSerpProvider::class);
    $provider->shouldReceive('search')->once()->with('roofer barnsley', 'Barnsley', 100)->andReturn(new SerpSearchResponse(
        'dataforseo',
        'serp/google/organic/live/advanced',
        collect([new SerpResult(32, 'https://example.com/roofing', 'example.com')]),
        0.02,
        'task-1',
    ));
    $cachedProvider = new CachedSerpProvider($provider);

    $fresh = $cachedProvider->search('roofer barnsley', 'Barnsley', 100);
    $cached = $cachedProvider->search('roofer barnsley', 'Barnsley', 100);

    expect($fresh->cached)->toBeFalse()
        ->and($fresh->cost)->toBe(0.02)
        ->and($cached->cached)->toBeTrue()
        ->and($cached->cost)->toBe(0.0)
        ->and($cached->results->first()->position)->toBe(32)
        ->and($cached->fetchedAt)->toBe($fresh->fetchedAt);
});

it('records cache freshness without creating a paid API usage row', function (): void {
    Queue::fake();
    $search = SeoProspectSearch::factory()->create(['keywords' => ['roofer barnsley']]);
    $provider = Mockery::mock(SerpProvider::class);
    $provider->shouldReceive('search')->once()->andReturn(new SerpSearchResponse(
        'dataforseo',
        'serp/google/organic/live/advanced',
        collect([new SerpResult(32, 'https://example.com/roofing', 'example.com')]),
        cached: true,
        fetchedAt: now()->subDay()->toIso8601String(),
    ));

    (new DiscoverSeoProspects($search))->handle($provider, app(PixelUrlNormalizer::class));

    expect($search->refresh()->cached_keyword_count)->toBe(1)
        ->and($search->fresh_keyword_count)->toBe(0)
        ->and($search->api_cost)->toBe('0.000000')
        ->and(data_get($search->serp_freshness, 'roofer barnsley.source'))->toBe('cache')
        ->and(ExternalApiUsage::query()->count())->toBe(0);
});
