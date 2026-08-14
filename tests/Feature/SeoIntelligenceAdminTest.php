<?php

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
        ->assertSessionHas('status', 'SEO intelligence generation has been queued.');

    $snapshot = $website->seoSnapshots()->sole();
    expect($snapshot->status)->toBe(SeoSnapshot::STATUS_PENDING)
        ->and($snapshot->domain)->toBe('offline-example.com')
        ->and($snapshot->location_code)->toBe(2826)
        ->and($snapshot->language_code)->toBe('en');
    Queue::assertPushed(GenerateSeoIntelligence::class, fn (GenerateSeoIntelligence $job): bool => $job->snapshot->is($snapshot));
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
        ->assertSessionHas('status', 'The latest SEO intelligence is still within the seven-day refresh window.');

    expect($website->seoSnapshots()->sole()->is($snapshot))->toBeTrue();
    Queue::assertNothingPushed();
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
        ->assertSessionHas('status', 'SEO intelligence generation has been queued.');

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
        ->assertSessionHas('status', 'SEO intelligence is already being generated.');

    expect($website->seoSnapshots()->sole()->is($snapshot))->toBeTrue();
    Queue::assertNothingPushed();
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
