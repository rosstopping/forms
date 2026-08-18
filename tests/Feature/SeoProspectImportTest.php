<?php

use App\Jobs\AnalyzeProspect;
use App\Models\Prospect;
use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectRanking;
use App\Models\SeoProspectSearch;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('adds a suitable SEO candidate to Outreach with traceable discovery evidence', function (): void {
    Queue::fake();
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $search = SeoProspectSearch::factory()->for($admin, 'owner')->create(['status' => 'analyzed']);
    $candidate = SeoProspectCandidate::factory()->for($search, 'search')->create([
        'domain' => 'acme-roofing.example',
        'website_url' => 'https://acme-roofing.example',
        'business_name' => 'Acme Roofing',
        'qualification_status' => 'suitable',
        'opportunity_score' => 82,
        'score_breakdown' => ['ranking' => ['score' => 35, 'maximum' => 40]],
        'observations' => ['outreach' => [['type' => 'ranking', 'summary' => 'Ranks #32 for roofer barnsley.']]],
        'contact_details' => ['emails' => [['value' => 'hello@acme-roofing.example']]],
    ]);
    $ranking = SeoProspectRanking::factory()->for($candidate, 'candidate')->create([
        'keyword' => 'roofer barnsley',
        'position' => 32,
    ]);

    $this->actingAs($admin)->post(route('admin.seo-prospect-searches.import', $search), [
        'candidate_ids' => [$candidate->id],
    ])->assertRedirect()->assertSessionHas('status');

    $prospect = Prospect::query()->sole();
    $activity = $prospect->activities()->where('type', 'seo_opportunity_imported')->sole();
    expect($candidate->refresh()->prospect->is($prospect))->toBeTrue()
        ->and($prospect->business_name)->toBe('Acme Roofing')
        ->and($prospect->email)->toBe('hello@acme-roofing.example')
        ->and($prospect->opportunity_score)->toBe(82)
        ->and($prospect->approved_at)->toBeNull()
        ->and($prospect->sent_at)->toBeNull()
        ->and($activity->metadata['seo_prospect_candidate_id'])->toBe($candidate->id)
        ->and(data_get($activity->metadata, 'rankings.0.id'))->toBe($ranking->id)
        ->and($search->refresh()->imported_count)->toBe(1);
    Queue::assertPushed(AnalyzeProspect::class, fn (AnalyzeProspect $job): bool => $job->prospect->is($prospect));
    Mail::assertNothingOutgoing();
});

it('links a normalized existing Outreach domain without creating or reanalysing it', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['website_url' => 'https://www.example.com/contact']);
    $search = SeoProspectSearch::factory()->for($admin, 'owner')->create(['status' => 'analyzed']);
    $candidate = SeoProspectCandidate::factory()->for($search, 'search')->create([
        'domain' => 'example.com',
        'website_url' => 'https://example.com',
        'qualification_status' => 'suitable',
    ]);

    $this->actingAs($admin)->post(route('admin.seo-prospect-searches.import', $search), [
        'candidate_ids' => [$candidate->id],
    ])->assertRedirect();

    expect(Prospect::query()->count())->toBe(1)
        ->and($candidate->refresh()->prospect->is($prospect))->toBeTrue()
        ->and($prospect->activities()->where('type', 'seo_opportunity_imported')->exists())->toBeTrue();
    Queue::assertNotPushed(AnalyzeProspect::class);
});

it('rejects unsuitable or cross-search SEO candidates', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $search = SeoProspectSearch::factory()->for($admin, 'owner')->create(['status' => 'analyzed']);
    $otherSearch = SeoProspectSearch::factory()->for($admin, 'owner')->create(['status' => 'analyzed']);
    $unsuitable = SeoProspectCandidate::factory()->for($search, 'search')->create(['qualification_status' => 'too_large']);
    $other = SeoProspectCandidate::factory()->for($otherSearch, 'search')->create(['qualification_status' => 'suitable']);

    $this->actingAs($admin)->post(route('admin.seo-prospect-searches.import', $search), ['candidate_ids' => [$unsuitable->id]])->assertInvalid('candidate_ids');
    $this->post(route('admin.seo-prospect-searches.import', $search), ['candidate_ids' => [$other->id]])->assertInvalid('candidate_ids');

    expect(Prospect::query()->count())->toBe(0);
});

it('renders candidate selection controls and the SERP freshness policy', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $search = SeoProspectSearch::factory()->for($admin, 'owner')->create([
        'status' => 'analyzed',
        'serp_freshness' => [
            'roofer barnsley' => ['source' => 'cache', 'fetched_at' => now()->subDay()->toIso8601String()],
        ],
    ]);
    SeoProspectCandidate::factory()->for($search, 'search')->create(['business_name' => 'Suitable Ltd', 'qualification_status' => 'suitable']);
    SeoProspectCandidate::factory()->for($search, 'search')->create(['business_name' => 'Too Large Ltd', 'qualification_status' => 'too_large']);

    $this->actingAs($admin)->get(route('admin.seo-prospect-searches.show', $search))
        ->assertSuccessful()
        ->assertSee('Add selected to Outreach')
        ->assertSee('Select Suitable Ltd')
        ->assertSee('Select Too Large Ltd')
        ->assertSee('SERP freshness by keyword')
        ->assertSee('roofer barnsley')
        ->assertSee('Cached SERPs are reused for 7 days');
});
