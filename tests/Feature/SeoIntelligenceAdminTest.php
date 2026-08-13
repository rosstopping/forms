<?php

use App\Jobs\GenerateSeoIntelligence;
use App\Models\SeoKeyword;
use App\Models\SeoReferringDomain;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Website;
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
        ->assertSee('SEO Intelligence')
        ->assertSee('DataForSEO estimate')
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
    $snapshot = SeoSnapshot::factory()->for($website)->create(['completed_at' => now()->subDays(2)]);

    $this->actingAs($owner)
        ->post(route('admin.seo-intelligence.store', $website))
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'seo']))
        ->assertSessionHas('status', 'The latest SEO intelligence is still within the seven-day refresh window.');

    expect($website->seoSnapshots()->sole()->is($snapshot))->toBeTrue();
    Queue::assertNothingPushed();
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
    SeoReferringDomain::factory()->for($website)->for($snapshot, 'snapshot')->create([
        'domain' => 'publisher.example',
        'domain_rank' => 71,
        'backlinks_count' => 14,
    ]);
    SeoKeyword::factory()->for($website)->for($snapshot, 'snapshot')->create([
        'keyword' => 'unrelated informational query',
        'position' => 55,
        'search_intent' => 'informational',
    ]);

    $this->actingAs($owner)
        ->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_filter' => 'positions_11_20']))
        ->assertSuccessful()
        ->assertSee('139')
        ->assertSee('~720')
        ->assertSee('Backlinks')
        ->assertSee('872')
        ->assertSee('publisher.example')
        ->assertSee('garden rooms doncaster')
        ->assertDontSee('unrelated informational query')
        ->assertSee('Third-party market intelligence')
        ->assertSee('Locally stored DataForSEO estimates');
});
