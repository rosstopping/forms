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
        ->assertSee('Import selected for research');
});

it('maps public OpenStreetMap listings with websites and caches the search', function () {
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake(['https://overpass-api.de/api/interpreter' => Http::response(['elements' => [
        ['type' => 'node', 'id' => 42, 'tags' => ['name' => 'Acme Plumbing', 'website' => 'acme.example', 'phone' => '0117 123 4567', 'addr:street' => 'High Street', 'addr:city' => 'Bristol']],
        ['type' => 'node', 'id' => 43, 'tags' => ['name' => 'No Website Ltd']],
    ]])]);

    $finder = app(OpenStreetMapProspectFinder::class);
    $candidates = $finder->find('Bristol', 'tradespeople');
    $finder->find('Bristol', 'tradespeople');

    expect($candidates)->toHaveCount(1)->and($candidates[0])->toMatchArray(['source_key' => 'node/42', 'business_name' => 'Acme Plumbing', 'website_url' => 'https://acme.example', 'address' => 'High Street, Bristol']);
    Http::assertSentCount(1);
});
