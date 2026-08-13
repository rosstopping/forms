<?php

use App\Jobs\AnalyzeProspect;
use App\Jobs\DiscoverProspects;
use App\Models\ProspectDiscovery;
use App\Models\ProspectDiscoveryCandidate;
use App\Models\User;
use App\Services\OpenStreetMapProspectFinder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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
