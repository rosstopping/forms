<?php

use App\Jobs\AnalyzeProspect;
use App\Jobs\DiscoverSeoProspects;
use App\Jobs\ImportAutomaticSeoProspects;
use App\Models\Prospect;
use App\Models\ProspectingIndustryProfile;
use App\Models\ProspectingLocation;
use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectRanking;
use App\Models\SeoProspectSearch;
use App\Models\User;
use App\Services\SeoOpportunityScoringService;
use App\Services\SeoProspectImporter;
use Database\Seeders\ProspectingStrategySeeder;
use Illuminate\Support\Facades\Queue;

it('seeds the requested high-value industry and location strategy idempotently', function (): void {
    $this->seed(ProspectingStrategySeeder::class);
    $this->seed(ProspectingStrategySeeder::class);

    expect(ProspectingIndustryProfile::query()->count())->toBe(14)
        ->and(ProspectingLocation::query()->count())->toBe(7);

    $kitchens = ProspectingIndustryProfile::query()->where('slug', 'kitchen-companies')->sole();
    $roofing = ProspectingIndustryProfile::query()->where('slug', 'roofing-companies')->sole();
    expect($kitchens->priority)->toBeGreaterThan($roofing->priority)
        ->and($kitchens->minimum_position)->toBe(8)
        ->and($kitchens->maximum_position)->toBe(50)
        ->and($kitchens->maximum_site_size)->toBe(30)
        ->and($kitchens->search_keywords)->toContain('fitted kitchens')
        ->and(ProspectingLocation::query()->where('slug', 'doncaster')->sole()->enabled)->toBeTrue();
});

it('queues bounded automatic discovery from the outreach UI and skips monthly duplicates', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $profile = ProspectingIndustryProfile::factory()->create([
        'name' => 'Kitchen companies',
        'priority' => 100,
        'search_keywords' => ['fitted kitchens', 'kitchen fitters', 'bespoke kitchens', 'luxury kitchens'],
    ]);
    ProspectingLocation::factory()->create(['name' => 'Doncaster', 'slug' => 'doncaster', 'priority' => 100]);
    ProspectingLocation::factory()->create(['name' => 'Sheffield', 'slug' => 'sheffield', 'priority' => 90]);

    $this->actingAs($admin)->post(route('admin.prospect-discoveries.automatic'), ['limit' => 3])
        ->assertRedirect(route('admin.prospect-discoveries.index').'#automatic-prospecting')
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Queued 1 automatic searches covering 3 keyword operations. Estimated provider cost:'));

    $search = SeoProspectSearch::query()->sole();
    expect($search->industryProfile->is($profile))->toBeTrue()
        ->and($search->automated)->toBeTrue()
        ->and($search->keywords)->toBe(['fitted kitchens doncaster', 'kitchen fitters doncaster', 'bespoke kitchens doncaster'])
        ->and($search->minimum_position)->toBe(8)
        ->and($search->maximum_position)->toBe(50);
    Queue::assertPushed(DiscoverSeoProspects::class, 1);

    $this->post(route('admin.prospect-discoveries.automatic'), ['limit' => 3])->assertRedirect();
    expect(SeoProspectSearch::query()->count())->toBe(2)
        ->and(SeoProspectSearch::query()->where('prospecting_location_id', $search->prospecting_location_id)->count())->toBe(1)
        ->and(SeoProspectSearch::query()->pluck('automation_key')->unique()->count())->toBe(2);
});

it('renders and validates the automatic prospecting controls in Outreach', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->get(route('admin.prospect-discoveries.index'))
        ->assertSuccessful()
        ->assertSee('Automatic prospecting')
        ->assertSee('Run automatic prospecting')
        ->assertSee('Keyword operation limit')
        ->assertSee('qualifying prospects still require outreach approval')
        ->assertSee('data-tabs-key="prospect-discovery"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('data-tab="automatic-prospecting"', false)
        ->assertSee('data-tab="seo-opportunities"', false)
        ->assertSee('data-tab-panel="seo-opportunities" hidden', false)
        ->assertDontSee('data-tab="local-businesses"', false)
        ->assertDontSee('id="local-businesses"', false);

    $this->post(route('admin.prospect-discoveries.automatic'), ['limit' => 201])->assertInvalid('limit');
});

it('explains when automatic prospecting has no enabled strategy', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->post(route('admin.prospect-discoveries.automatic'), ['limit' => 50])
        ->assertRedirect(route('admin.prospect-discoveries.index').'#automatic-prospecting')
        ->assertSessionHas('status', 'Automatic prospecting needs at least one enabled industry. Add or enable one in Automation strategy.');
});

it('scores commercial opportunity from ranking value intent and site manageability', function (): void {
    $profile = ProspectingIndustryProfile::factory()->create([
        'estimated_customer_value' => 15000,
        'customer_value_band' => 'very_high',
        'search_keywords' => ['fitted kitchens'],
    ]);
    $search = SeoProspectSearch::factory()->for($profile, 'industryProfile')->create();
    $candidate = SeoProspectCandidate::factory()->for($search, 'search')->create([
        'qualification_status' => 'suitable',
        'page_count' => 8,
        'audit_score' => 50,
        'migration_difficulty' => 'easy',
    ]);
    SeoProspectRanking::factory()->for($candidate, 'candidate')->create(['keyword' => 'fitted kitchens doncaster', 'position' => 18]);

    $score = app(SeoOpportunityScoringService::class)->score($candidate->fresh());

    expect($score['commercial_opportunity_score'])->toBe(100)
        ->and(data_get($score, 'commercial_score_breakdown.ranking_opportunity.score'))->toBe(40)
        ->and(data_get($score, 'commercial_score_breakdown.customer_value.score'))->toBe(25)
        ->and(data_get($score, 'commercial_score_breakdown.site_manageability.score'))->toBe(20)
        ->and(data_get($score, 'commercial_score_breakdown.commercial_intent.score'))->toBe(15);
});

it('automatically imports only qualifying candidates with outreach provenance and no send', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $profile = ProspectingIndustryProfile::factory()->create();
    $location = ProspectingLocation::factory()->create(['name' => 'Doncaster']);
    $search = SeoProspectSearch::factory()->for($admin, 'owner')->for($profile, 'industryProfile')->for($location, 'prospectingLocation')->create([
        'automated' => true,
        'automatic_import_score' => 70,
        'industry' => $profile->name,
        'location' => $location->name,
    ]);
    $candidate = SeoProspectCandidate::factory()->for($search, 'search')->create([
        'qualification_status' => 'suitable',
        'commercial_opportunity_score' => 88,
        'commercial_score_breakdown' => ['ranking_opportunity' => ['explanation' => 'Ranks in the strongest opportunity band.']],
        'page_count' => 9,
    ]);
    SeoProspectRanking::factory()->for($candidate, 'candidate')->create(['keyword' => 'fitted kitchens doncaster', 'position' => 16]);

    (new ImportAutomaticSeoProspects($search))->handle(app(SeoProspectImporter::class));

    $prospect = Prospect::query()->sole();
    expect($prospect->industryProfile->is($profile))->toBeTrue()
        ->and($prospect->prospectingLocation->is($location))->toBeTrue()
        ->and($prospect->commercial_opportunity_score)->toBe(88)
        ->and(data_get($prospect->prospecting_context, 'search_query'))->toBe('fitted kitchens doncaster')
        ->and(data_get($prospect->prospecting_context, 'google_position'))->toBe(16)
        ->and($prospect->approved_at)->toBeNull()
        ->and($prospect->sent_at)->toBeNull();
    Queue::assertPushed(AnalyzeProspect::class);
});
