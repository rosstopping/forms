<?php

use App\Contracts\SerpProvider;
use App\Data\SerpResult;
use App\Data\SerpSearchResponse;
use App\Jobs\AnalyzeProspect;
use App\Jobs\DiscoverProspects;
use App\Jobs\DiscoverSeoProspects;
use App\Models\ExternalApiUsage;
use App\Models\Prospect;
use App\Models\ProspectDiscovery;
use App\Models\ProspectDiscoveryCandidate;
use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectRanking;
use App\Models\SeoProspectSearch;
use App\Models\User;
use App\Services\OpenStreetMapProspectFinder;
use App\Services\PixelUrlNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('shows local business and SEO opportunity discovery on the same page', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($user)->get(route('admin.prospect-discoveries.index'))
        ->assertSuccessful()
        ->assertSee('Local Businesses')
        ->assertSee('Search public listings')
        ->assertSee('SEO Opportunities')
        ->assertSee('Find ranking opportunities');
});

it('queues an SEO opportunity search with editable keywords and default limits', function () {
    Queue::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($user)->post(route('admin.seo-prospect-searches.store'), [
        'industry' => 'Roofing',
        'location' => 'Barnsley',
        'service_keywords' => "roofer\nroof repairs",
        'keywords' => "roofer barnsley\nroof repairs barnsley",
        'minimum_position' => 20,
        'maximum_position' => 100,
        'maximum_pages' => 20,
    ])->assertRedirect();

    $search = SeoProspectSearch::query()->sole();
    expect($search->owner->is($user))->toBeTrue()
        ->and($search->service_keywords)->toBe(['roofer', 'roof repairs'])
        ->and($search->keywords)->toBe(['roofer barnsley', 'roof repairs barnsley'])
        ->and($search->minimum_position)->toBe(20)
        ->and($search->maximum_position)->toBe(100)
        ->and($search->maximum_pages)->toBe(20);
    Queue::assertPushed(DiscoverSeoProspects::class, fn (DiscoverSeoProspects $job): bool => $job->search->is($search));
});

it('deduplicates SEO candidates by domain and stores provider-backed rankings', function () {
    $prospect = Prospect::factory()->create(['website_url' => 'https://www.acme-roofing.example/contact']);
    $search = SeoProspectSearch::factory()->create([
        'keywords' => ['roofer barnsley', 'roof repairs barnsley'],
        'maximum_position' => 100,
    ]);
    $provider = Mockery::mock(SerpProvider::class);
    $provider->shouldReceive('search')->once()->with('roofer barnsley', 'Barnsley', 100)->andReturn(new SerpSearchResponse(
        'test-serp',
        'test/organic',
        collect([new SerpResult(12, 'https://www.acme-roofing.example/roofing', 'www.acme-roofing.example', 'Acme Roofing', 'Roofing services', 'Acme Roofing Ltd')]),
        0.02,
        'task-1',
    ));
    $provider->shouldReceive('search')->once()->with('roof repairs barnsley', 'Barnsley', 100)->andReturn(new SerpSearchResponse(
        'test-serp',
        'test/organic',
        collect([new SerpResult(38, 'https://acme-roofing.example/repairs', 'acme-roofing.example', 'Roof Repairs', 'Local roof repairs', 'Acme Roofing Ltd')]),
        0.02,
        'task-2',
    ));

    (new DiscoverSeoProspects($search))->handle($provider, app(PixelUrlNormalizer::class));

    $candidate = $search->refresh()->candidates()->sole();
    expect($search->status)->toBe('discovered')
        ->and($search->candidate_count)->toBe(1)
        ->and($search->api_cost)->toBe('0.040000')
        ->and($candidate->domain)->toBe('acme-roofing.example')
        ->and($candidate->prospect->is($prospect))->toBeTrue()
        ->and($candidate->rankings()->orderBy('position')->pluck('position')->all())->toBe([12, 38])
        ->and(ExternalApiUsage::query()->count())->toBe(2)
        ->and(ExternalApiUsage::query()->first()->metadata['seo_prospect_search_id'])->toBe($search->id);
});

it('shows stored SEO ranking evidence without enabling import', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($user, 'owner')->create();
    $search = SeoProspectSearch::factory()->for($user, 'owner')->create(['status' => 'discovered', 'candidate_count' => 1]);
    $candidate = SeoProspectCandidate::factory()->for($search, 'search')->for($prospect)->create(['domain' => 'acme-roofing.example', 'business_name' => 'Acme Roofing Ltd']);
    SeoProspectRanking::factory()->for($candidate, 'candidate')->create(['keyword' => 'roofer barnsley', 'position' => 38]);

    $this->actingAs($user)->get(route('admin.seo-prospect-searches.show', $search))
        ->assertSuccessful()
        ->assertSee('Acme Roofing Ltd')
        ->assertSee('roofer barnsley')
        ->assertSee('#38')
        ->assertSee('Already in Outreach')
        ->assertDontSee('Import selected');
});

it('allows administrators to queue a prospect finder search', function () {
    Queue::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($user)->post(route('admin.prospect-discoveries.store'), ['area' => 'Bristol', 'business_type' => 'tradespeople'])
        ->assertRedirect();

    $discovery = ProspectDiscovery::query()->sole();
    expect($discovery->user_id)->toBe($user->id)->and($discovery->area)->toBe('Bristol');
    Queue::assertPushed(DiscoverProspects::class, fn (DiscoverProspects $job): bool => $job->discovery->is($discovery));
});

it('keeps the prospect finder restricted to Sitewell administrators', function () {
    $user = User::factory()->create();
    $discovery = ProspectDiscovery::factory()->create();

    $this->actingAs($user)->get(route('admin.prospect-discoveries.index'))->assertForbidden();
    $this->get(route('admin.prospect-discoveries.show', $discovery))->assertForbidden();
    $this->post(route('admin.prospect-discoveries.store'), ['area' => 'Bristol', 'business_type' => 'tradespeople'])->assertForbidden();
});

it('imports selected candidates and queues research without sending outreach', function () {
    Queue::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $discovery = ProspectDiscovery::factory()->for($user, 'owner')->create(['status' => 'completed']);
    $candidate = ProspectDiscoveryCandidate::factory()->for($discovery, 'discovery')->create(['business_name' => 'Acme Plumbing', 'website_url' => 'https://acme.example']);

    $this->actingAs($user)->post(route('admin.prospect-discoveries.import', $discovery), ['candidate_ids' => [$candidate->id]])->assertRedirect();

    $candidate->refresh();
    expect($candidate->status)->toBe('imported')->and($candidate->prospect)->not->toBeNull();
    Queue::assertPushed(AnalyzeProspect::class, fn (AnalyzeProspect $job): bool => $job->prospect->is($candidate->prospect));
});

it('imports businesses without websites as website opportunities and skips the audit', function () {
    Queue::fake();
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $discovery = ProspectDiscovery::factory()->for($user, 'owner')->create(['status' => 'completed']);
    $candidate = ProspectDiscoveryCandidate::factory()->for($discovery, 'discovery')->create([
        'business_name' => 'Bristol Builders',
        'website_url' => null,
        'phone' => '0117 123 4567',
        'address' => 'High Street, Bristol',
        'source_key' => 'node/43',
        'source_data' => ['tags' => ['contact:email' => 'hello@bristol-builders.example']],
    ]);

    $this->actingAs($user)->post(route('admin.prospect-discoveries.import', $discovery), ['candidate_ids' => [$candidate->id]])->assertRedirect();

    $prospect = $candidate->refresh()->prospect;
    expect($prospect->website_url)->toBeNull()
        ->and($prospect->analysis_status)->toBe('skipped')
        ->and($prospect->status)->toBe('drafted')
        ->and($prospect->email)->toBe('hello@bristol-builders.example')
        ->and(data_get($prospect->contact_details, 'phones.0.value'))->toBe('0117 123 4567')
        ->and(data_get($prospect->contact_details, 'addresses.0.value'))->toBe('High Street, Bristol')
        ->and($prospect->outreach_body)->toContain("couldn't see a website linked from the business listing")
        ->and($prospect->outreach_body)->toContain('quick video below');
    Queue::assertNotPushed(AnalyzeProspect::class);
});

it('renders completed discovery results for review', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $discovery = ProspectDiscovery::factory()->for($user, 'owner')->create(['status' => 'completed', 'candidate_count' => 1]);
    ProspectDiscoveryCandidate::factory()->for($discovery, 'discovery')->create([
        'business_name' => 'Acme Plumbing',
        'website_url' => 'https://acme.example',
        'address' => 'High Street, Bristol',
    ]);

    $this->actingAs($user)->get(route('admin.prospect-discoveries.show', $discovery))
        ->assertSuccessful()
        ->assertSee('Acme Plumbing')
        ->assertSee('Import selected');
});

it('maps public OpenStreetMap listings with and without websites and caches the search', function () {
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake(['https://overpass-api.de/api/interpreter' => Http::response(['elements' => [
        ['type' => 'node', 'id' => 42, 'tags' => ['name' => 'Acme Plumbing', 'website' => 'acme.example', 'phone' => '0117 123 4567', 'addr:street' => 'High Street', 'addr:city' => 'Bristol']],
        ['type' => 'node', 'id' => 43, 'tags' => ['name' => 'No Website Ltd', 'phone' => '0117 555 0101']],
    ]])]);

    $finder = app(OpenStreetMapProspectFinder::class);
    $candidates = $finder->find('Bristol', 'tradespeople');
    $finder->find('Bristol', 'tradespeople');

    expect($candidates)->toHaveCount(2)
        ->and($candidates[0])->toMatchArray(['source_key' => 'node/42', 'business_name' => 'Acme Plumbing', 'website_url' => 'https://acme.example', 'address' => 'High Street, Bristol'])
        ->and($candidates[1])->toMatchArray(['source_key' => 'node/43', 'business_name' => 'No Website Ltd', 'website_url' => null, 'phone' => '0117 555 0101']);
    Http::assertSentCount(1);
});
