<?php

use App\Jobs\BackfillSeoHistory;
use App\Jobs\GenerateSeoIntelligence;
use App\Models\SeoCompetitor;
use App\Models\SeoKeyword;
use App\Models\SeoOpportunity;
use App\Models\SeoReferringDomain;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Website;
use App\Services\SeoIntelligence\SeoSnapshotService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('services.dataforseo.login', 'api-login');
    config()->set('services.dataforseo.password', 'api-password');
    config()->set('services.dataforseo.refresh_days', 7);
    config()->set('services.dataforseo.pending_timeout_minutes', 30);
    config()->set('services.dataforseo.location_code', 2826);
    config()->set('services.dataforseo.language_code', 'en');
});

test('the website workspace exposes seo intelligence with an empty state', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);

    $this->actingAs($owner)
        ->get(route('admin.websites.show', [$website, 'tab' => 'seo']))
        ->assertSuccessful()
        ->assertSee('data-tab="seo"', false)
        ->assertSee('data-tab-panel="seo"', false)
        ->assertSee('data-tabs-key="seo-intelligence"', false)
        ->assertSee('SEO Intelligence')
        ->assertSee('Estimated data')
        ->assertSee('Automatic weekly snapshots')
        ->assertDontSee('DataForSEO')
        ->assertSee('No SEO snapshot yet')
        ->assertSee('Generate intelligence');
});

test('a manager can queue the first seo intelligence snapshot', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $website->domains()->create(['domain' => 'offline-example.com', 'is_primary' => true]);

    $this->actingAs($manager)
        ->post(route('admin.seo-intelligence.store', $website))
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'seo']))
        ->assertSessionHas('status', 'SEO intelligence generation has been queued. Historical SEO data import has also been queued.');

    $snapshot = $website->seoSnapshots()->sole();
    expect($snapshot->status)->toBe(SeoSnapshot::STATUS_PENDING)
        ->and($snapshot->domain)->toBe('offline-example.com')
        ->and($snapshot->location_code)->toBe(2826)
        ->and($snapshot->language_code)->toBe('en');
    Queue::assertPushed(GenerateSeoIntelligence::class, fn (GenerateSeoIntelligence $job): bool => $job->snapshot->is($snapshot));
    Queue::assertPushed(BackfillSeoHistory::class, fn (BackfillSeoHistory $job): bool => $job->website->is($website));
});

test('refresh protection returns a recent snapshot without spending again', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $snapshot = SeoSnapshot::factory()->for($website)->create([
        'completed_at' => now()->subDays(2),
        'metadata' => ['data_source' => 'third_party_estimate', 'keyword_sample_version' => SeoSnapshotService::KEYWORD_SAMPLE_VERSION],
    ]);

    $this->actingAs($owner)
        ->post(route('admin.seo-intelligence.store', $website))
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'seo']))
        ->assertSessionHas('status', 'The latest SEO intelligence is still within the seven-day refresh window. Historical SEO data import has been queued.');

    expect($website->seoSnapshots()->sole()->is($snapshot))->toBeTrue();
    Queue::assertPushed(BackfillSeoHistory::class, fn (BackfillSeoHistory $job): bool => $job->website->is($website));
    Queue::assertNotPushed(GenerateSeoIntelligence::class);
});

test('refreshing a sparse recent snapshot derives foundation actions without another provider request', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'small.example', 'is_primary' => true]);
    $snapshot = SeoSnapshot::factory()->for($website)->create([
        'completed_at' => now()->subDay(),
        'metadata' => ['data_source' => 'third_party_estimate', 'keyword_sample_version' => SeoSnapshotService::KEYWORD_SAMPLE_VERSION],
    ]);
    $snapshot->keywords()->create([
        'website_id' => $website->id,
        'fingerprint' => hash('sha256', 'specialist sparse phrase'),
        'keyword' => 'specialist sparse phrase',
        'position' => 62,
        'ranking_url' => 'https://small.example/service',
        'search_volume' => 5,
        'search_intent' => 'informational',
        'location_code' => 2826,
        'language_code' => 'en',
    ]);

    $this->actingAs($owner)
        ->post(route('admin.seo-intelligence.store', $website))
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'seo']));

    expect($snapshot->opportunities()->sole()->type)->toBe(SeoOpportunity::TYPE_FOUNDATION)
        ->and($snapshot->opportunities()->sole()->metrics['uses_adaptive_threshold'])->toBeTrue();
    Queue::assertNotPushed(GenerateSeoIntelligence::class);
});

test('a recent snapshot with the legacy keyword sample can be replaced', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $legacySnapshot = SeoSnapshot::factory()->for($website)->create([
        'completed_at' => now()->subDays(2),
        'metadata' => ['data_source' => 'third_party_estimate', 'datasets' => ['domain_overview', 'ranked_keywords']],
    ]);

    $this->actingAs($owner)
        ->post(route('admin.seo-intelligence.store', $website))
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'seo']))
        ->assertSessionHas('status', 'SEO intelligence generation has been queued. Historical SEO data import has also been queued.');

    expect($website->seoSnapshots()->count())->toBe(2);
    Queue::assertPushed(GenerateSeoIntelligence::class, fn (GenerateSeoIntelligence $job): bool => ! $job->snapshot->is($legacySnapshot));
});

test('refresh protection reuses an active snapshot', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $snapshot = SeoSnapshot::factory()->pending()->for($website)->create();

    $this->actingAs($owner)
        ->post(route('admin.seo-intelligence.store', $website))
        ->assertSessionHas('status', 'SEO intelligence is already being generated. Historical SEO data import has also been queued.');

    expect($website->seoSnapshots()->sole()->is($snapshot))->toBeTrue();
    Queue::assertPushed(BackfillSeoHistory::class, fn (BackfillSeoHistory $job): bool => $job->website->is($website));
    Queue::assertNotPushed(GenerateSeoIntelligence::class);
});

test('refresh does not repeat a completed historical import', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create(['seo_history_backfilled_at' => now()]);
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    SeoSnapshot::factory()->for($website)->create([
        'completed_at' => now()->subDays(2),
        'metadata' => ['data_source' => 'third_party_estimate', 'keyword_sample_version' => SeoSnapshotService::KEYWORD_SAMPLE_VERSION],
    ]);

    $this->actingAs($owner)
        ->post(route('admin.seo-intelligence.store', $website))
        ->assertSessionHas('status', 'The latest SEO intelligence is still within the seven-day refresh window.');

    Queue::assertNotPushed(BackfillSeoHistory::class);
    Queue::assertNotPushed(GenerateSeoIntelligence::class);
});

test('viewers cannot request a paid seo refresh', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);

    $this->actingAs($viewer)
        ->post(route('admin.seo-intelligence.store', $website))
        ->assertForbidden();

    expect($website->seoSnapshots()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('the seo tab displays and filters locally stored keyword estimates', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $snapshot = SeoSnapshot::factory()->for($website)->create([
        'organic_keywords' => 139,
        'top_10_keywords' => 18,
        'top_20_keywords' => 42,
        'estimated_organic_traffic' => 720,
        'backlinks' => 872,
        'referring_domains' => 74,
        'broken_backlinks' => 9,
        'domain_rank' => 42,
    ]);
    SeoKeyword::factory()->for($website)->for($snapshot, 'snapshot')->create([
        'keyword' => 'garden rooms doncaster',
        'position' => 12,
        'search_volume' => 390,
        'search_intent' => 'commercial',
    ]);
    $rankingKeyword = $snapshot->keywords()->where('keyword', 'garden rooms doncaster')->firstOrFail();
    SeoOpportunity::factory()->for($website)->for($snapshot, 'snapshot')->for($rankingKeyword, 'keyword')->create([
        'type' => SeoOpportunity::TYPE_STRIKING_DISTANCE,
        'title' => 'Move “garden rooms doncaster” towards page one',
        'summary' => 'The domain ranks at position 12 for an estimated 390 monthly searches.',
        'recommendation' => 'Strengthen the ranking page and its relevant internal links.',
        'metrics' => [
            'data_source' => 'dataforseo_estimate',
            'ranking_url' => 'https://example.com/garden-rooms',
            'position' => 12,
            'search_volume' => 390,
            'search_intent' => 'commercial',
        ],
        'priority_score' => 82,
    ]);
    SeoReferringDomain::factory()->for($website)->for($snapshot, 'snapshot')->create([
        'domain' => 'publisher.example',
        'domain_rank' => 71,
        'backlinks_count' => 14,
    ]);
    SeoCompetitor::factory()->for($website)->for($snapshot, 'snapshot')->create([
        'domain' => 'competitor-one.example',
        'common_keywords' => 63,
        'organic_keywords' => 842,
        'estimated_traffic' => 4200.75,
    ]);
    SeoKeyword::factory()->for($website)->for($snapshot, 'snapshot')->create([
        'keyword' => 'unrelated informational query',
        'position' => 55,
        'search_intent' => 'informational',
    ]);

    $this->actingAs($owner)
        ->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_filter' => 'positions_11_20']))
        ->assertSuccessful()
        ->assertSeeInOrder(['Overview', 'Recommended Actions', 'Keywords', 'Backlinks', 'Competitors'])
        ->assertSee('data-default-tab="keywords"', false)
        ->assertSee('data-tab-panel="overview"', false)
        ->assertSee('data-tab-panel="actions"', false)
        ->assertSee('data-tab-panel="keywords"', false)
        ->assertSee('data-tab-panel="backlinks"', false)
        ->assertSee('data-tab-panel="competitors"', false)
        ->assertSee('data-seo-history-charts class="grid gap-4 border-t border-slate-200 p-4"', false)
        ->assertSee('139')
        ->assertSee('~720')
        ->assertSee('Backlinks')
        ->assertSee('872')
        ->assertSee('publisher.example')
        ->assertSee('Recommended actions')
        ->assertSee('Move “garden rooms doncaster” towards page one')
        ->assertSee('Strengthen the ranking page and its relevant internal links.')
        ->assertSee('Organic competitors')
        ->assertSee('competitor-one.example')
        ->assertSee('63')
        ->assertSee('garden rooms doncaster')
        ->assertDontSee('unrelated informational query')
        ->assertSee('Third-party market intelligence')
        ->assertSee('Locally stored third-party estimates')
        ->assertDontSee('DataForSEO');
});

test('website users can drill into locally observed keyword history', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $olderSnapshot = SeoSnapshot::factory()->for($website)->create(['snapshot_date' => '2026-07-31']);
    $latestSnapshot = SeoSnapshot::factory()->for($website)->create(['snapshot_date' => '2026-08-31']);
    $olderKeyword = SeoKeyword::factory()->for($website)->for($olderSnapshot, 'snapshot')->create([
        'keyword' => 'luxury train journeys',
        'position' => 14,
        'estimated_traffic' => 20,
    ]);
    SeoKeyword::factory()->for($website)->for($latestSnapshot, 'snapshot')->create([
        'keyword' => 'luxury train journeys',
        'position' => 9,
        'estimated_traffic' => 45,
    ]);

    $this->actingAs($owner)
        ->get(route('admin.seo-keywords.show', [$website, $olderKeyword]))
        ->assertSuccessful()
        ->assertSee('luxury train journeys')
        ->assertSee('Keyword performance over time')
        ->assertSee('31 Jul 2026: 14.0', false)
        ->assertSee('31 Aug 2026: 9.0', false);
});
