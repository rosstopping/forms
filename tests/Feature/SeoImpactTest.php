<?php

use App\Ai\Agents\ContentRequestPixelWriter;
use App\Jobs\MeasureSeoImpact;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\Optimisation;
use App\Models\SearchConsoleConnection;
use App\Models\SearchOpportunity;
use App\Models\SeoImpact;
use App\Models\SeoImpactReview;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Website;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\ContentOpportunityQueuer;
use App\Services\ContentRequestPixelOptimisationGenerator;
use App\Services\ContentWorkSelector;
use App\Services\SearchConsoleClient;
use App\Services\SeoImpactEvaluator;
use App\Services\SeoImpactTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-17 12:00:00', 'UTC'));
    config(['forms.pixel_ui_enabled' => false]);
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $this->impact = SeoImpact::factory()->for($this->website)->create();
    $this->brief = [
        'title' => 'Improve the service page', 'hypothesis' => 'Explain the offer more clearly to increase relevant clicks.',
        'target_urls' => "https://example.com/services\nhttps://example.com/about", 'target_queries' => "garden offices\ngarden rooms",
        'primary_metric' => 'clicks', 'business_value' => 5, 'confidence' => 4, 'effort' => 2,
        'country' => 'GBR', 'device' => 'DESKTOP', 'control_url' => 'https://example.com/another-service',
    ];
});

function impactPerformanceSample(float $clicks = 100, float $impressions = 1000, float $position = 8): array
{
    return ['complete' => true, 'rows' => [['date' => '2026-08-10', 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $clicks / $impressions, 'position' => $position]],
        'totals' => ['clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $clicks / $impressions, 'position' => $position, 'reported_days' => 28]];
}

function impactMeasurementSample(float $clicks = 100, float $impressions = 1000, float $position = 8): array
{
    return ['complete' => true, 'target' => impactPerformanceSample($clicks, $impressions, $position)];
}

test('queuing a recommendation creates one linked measurable brief with source evidence', function (): void {
    $opportunity = SearchOpportunity::factory()->for($this->website)->create(['page' => 'https://example.com/services', 'query' => 'garden offices', 'type' => 'low_ctr']);
    $request = app(ContentOpportunityQueuer::class)->queueSearch($opportunity, $this->owner);
    $impact = app(SeoImpactTracker::class)->forRequest($request);
    expect($impact->content_request_id)->toBe($request->id)
        ->and($impact->target_urls)->toBe(['https://example.com/services'])
        ->and($impact->target_queries)->toBe(['garden offices'])
        ->and($impact->primary_metric)->toBe('ctr')
        ->and($impact->evidence['source'])->toBe('search_console')
        ->and(SeoImpact::where('content_request_id', $request->id)->count())->toBe(1);
});

test('managers can save a bounded brief and see impact pages', function (): void {
    $this->actingAs($this->owner)->put(route('admin.seo-impacts.update', [$this->website, $this->impact]), $this->brief)->assertSessionDoesntHaveErrors()->assertRedirect();
    expect($this->impact->fresh()->target_queries)->toBe(['garden offices', 'garden rooms'])
        ->and($this->impact->fresh()->country)->toBe('gbr')
        ->and($this->impact->fresh()->priorityScore())->toBe(10.0);
    $this->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact']))->assertSuccessful()->assertSee('Improve the service page')->assertSee('Bump to top');
    $this->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $this->impact->id]))->assertSuccessful()->assertSee('Confirm live and start measuring');
});

test('viewers can read impact evidence but cannot change briefs or confirm delivery', function (): void {
    $viewer = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($viewer)->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $this->impact->id]))->assertSuccessful()->assertDontSee('Confirm live and start measuring');
    foreach (['update' => 'put', 'live' => 'post', 'review' => 'post'] as $action => $method) {
        $this->$method(route('admin.seo-impacts.'.$action, [$this->website, $this->impact]), $this->brief)->assertForbidden();
    }
});

test('impact access is website scoped and paid mutations remain gated', function (): void {
    $other = Website::factory()->for($this->owner, 'owner')->create();
    $this->actingAs($this->owner)->get(route('admin.seo-impacts.show', [$other, $this->impact]))->assertNotFound();
    $this->put(route('admin.seo-impacts.update', [$other, $this->impact]), $this->brief)->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact']))->assertForbidden();
    $this->owner->update(['membership_tier' => 'essential']);
    $this->actingAs($this->owner)->post(route('admin.seo-impacts.live', [$this->website, $this->impact]), [])->assertRedirect(route('admin.billing.index'));
});

test('briefs reject foreign urls overlapping controls and invalid scopes', function (array $invalid): void {
    $this->actingAs($this->owner)->put(route('admin.seo-impacts.update', [$this->website, $this->impact]), [...$this->brief, ...$invalid])->assertSessionHasErrors();
})->with([
    [['target_urls' => ['https://other.test/services']]],
    [['target_urls' => ['https://example.com.evil.test/services']]],
    [['control_url' => 'https://www.example.com/services/']],
    [['target_queries' => array_fill(0, 11, 'query')]],
    [['effort' => 0]],
    [['device' => 'watch']],
    [['country' => ['gbr']]],
    [['target_urls' => ['http://[::1]/service']]],
]);

test('delivery needs explicit evidence and freezes measurement scope without claiming a merge is live', function (): void {
    $generation = ContentGeneration::factory()->create(['merged_at' => now()]);
    $this->impact->update(['content_generation_id' => $generation->id]);
    $this->actingAs($this->owner)->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact']))->assertSee('Live confirmation still needed');
    $this->post(route('admin.seo-impacts.live', [$this->website, $this->impact]), ['live_date' => '2026-09-18'])->assertSessionHasErrors();
    $this->post(route('admin.seo-impacts.live', [$this->website, $this->impact]), [
        'live_date' => '2026-09-17', 'actual_changes' => 'Updated the service title and linked from the homepage.',
        'deployment_evidence' => 'Checked release 123 and inspected the public service page.', 'confirmed_live' => 1,
    ])->assertSessionDoesntHaveErrors()->assertRedirect();
    expect($this->impact->fresh()->status)->toBe('measuring')->and($this->impact->fresh()->confirmed_by)->toBe($this->owner->id);
    $this->put(route('admin.seo-impacts.update', [$this->website, $this->impact]), $this->brief)->assertUnprocessable();
});

test('daily measurement freezes the baseline and produces idempotent 28 and 56 day reviews', function (): void {
    SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
    $this->impact->update(['live_at' => Carbon::parse('2026-08-17', 'America/Los_Angeles')->utc(), 'status' => 'measuring']);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('impactPerformance')->once()->withArgs(fn ($connection, $start, $end): bool => $start->toDateString() === '2026-07-20' && $end->toDateString() === '2026-08-16')->andReturn(impactPerformanceSample());
    $client->shouldReceive('impactPerformance')->twice()->withArgs(fn ($connection, $start, $end): bool => $start->toDateString() === '2026-08-18' && $end->toDateString() === '2026-09-14')->andReturn(impactPerformanceSample(140));
    $job = new MeasureSeoImpact($this->impact);
    $job->handle($client, app(SeoImpactEvaluator::class));
    $job->handle($client, app(SeoImpactEvaluator::class));
    expect($this->impact->fresh()->outcome)->toBe('improved')->and($this->impact->fresh()->review_after_days)->toBe(56)
        ->and($this->impact->reviews()->count())->toBe(1)->and($this->impact->fresh()->baseline['target']['totals']['clicks'])->toEqual(100);
    $this->travelTo(Carbon::parse('2026-10-15 12:00', 'UTC'));
    $client->shouldReceive('impactPerformance')->once()->withArgs(fn ($connection, $start, $end): bool => $start->toDateString() === '2026-09-15' && $end->toDateString() === '2026-10-12')->andReturn(impactPerformanceSample(160));
    $job->handle($client, app(SeoImpactEvaluator::class));
    expect($this->impact->fresh()->status)->toBe('review_required')->and($this->impact->reviews()->count())->toBe(2);
});

test('missing connections and changed properties do not fabricate zero measurements', function (): void {
    $this->impact->update(['status' => 'measuring', 'live_at' => now()->subDays(40), 'property_url' => 'sc-domain:old.example']);
    SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldNotReceive('impactPerformance');
    (new MeasureSeoImpact($this->impact))->handle($client, app(SeoImpactEvaluator::class));
    expect($this->impact->fresh()->baseline)->toBeNull()->and($this->impact->fresh()->outcome)->toBeNull()
        ->and($this->impact->fresh()->measurement_error)->toContain('original Search Console property');
});

test('reviews distinguish sparse data noise declines position changes and wider trends', function (): void {
    $evaluator = app(SeoImpactEvaluator::class);
    expect($evaluator->assess(impactMeasurementSample(2), impactMeasurementSample(4), 'clicks')['outcome'])->toBe('insufficient_data')
        ->and($evaluator->assess(impactMeasurementSample(), impactMeasurementSample(105), 'clicks')['outcome'])->toBe('inconclusive')
        ->and($evaluator->assess(impactMeasurementSample(), impactMeasurementSample(60), 'clicks')['outcome'])->toBe('declined')
        ->and($evaluator->assess(impactMeasurementSample(), impactMeasurementSample(150, 1000, 5), 'ctr')['outcome'])->toBe('inconclusive')
        ->and($evaluator->assess(impactMeasurementSample(), impactMeasurementSample(150), 'clicks', true)['outcome'])->toBe('inconclusive')
        ->and($evaluator->assess([...impactMeasurementSample(), 'control' => impactPerformanceSample()], [...impactMeasurementSample(150), 'control' => impactPerformanceSample(150)], 'clicks')['outcome'])->toBe('inconclusive');
});

test('measurement protection applies beyond the old cooldown and learns from review decisions', function (): void {
    $plan = ContentPlan::factory()->for($this->website)->create();
    $generation = ContentGeneration::factory()->for($plan, 'plan')->create();
    $this->impact->update(['status' => 'measuring', 'live_at' => now()->subDays(40)]);
    $blocked = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Rewrite https://www.example.com/services/']);
    $eligible = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Improve https://example.com/contact']);
    $work = app(ContentWorkSelector::class)->select($generation);
    expect($work['requests']->values()->modelKeys())->toBe([$eligible->id]);
    $this->actingAs($this->owner)->post(route('admin.seo-impacts.review', [$this->website, $this->impact]), ['decision' => 'iterate', 'decision_notes' => 'Clicks declined; check the search intent before changing the page again.'])->assertRedirect();
    expect($this->impact->fresh()->status)->toBe('completed')->and($this->website->contentRequests()->count())->toBe(3);
    $followup = $this->website->contentRequests()->latest('id')->first();
    expect($followup->seoImpact->target_urls)->toBe($this->impact->target_urls);
    $this->post(route('admin.seo-impacts.review', [$this->website, $this->impact]), ['decision' => 'iterate', 'decision_notes' => 'Repeat the same follow-up request.'])->assertUnprocessable();
    expect(app(SeoImpactTracker::class)->promptContext($generation))->toContain('check the search intent');
});

test('low traffic reviews can be extended without losing prior evidence', function (): void {
    $this->impact->update(['status' => 'review_required', 'live_at' => now()->subDays(60), 'review_after_days' => 56, 'outcome' => 'insufficient_data']);
    SeoImpactReview::factory()->for($this->impact, 'impact')->create(['checkpoint' => 56]);
    $this->actingAs($this->owner)->post(route('admin.seo-impacts.review', [$this->website, $this->impact]), ['decision' => 'extend', 'decision_notes' => 'Low traffic needs more time to observe a useful signal.'])->assertRedirect();
    expect($this->impact->fresh()->status)->toBe('measuring')->and($this->impact->fresh()->review_after_days)->toBe(84)->and($this->impact->reviews()->count())->toBe(1);
});

test('scheduler queues only due active entitled impacts and failure retains prior evidence', function (): void {
    Queue::fake();
    $this->impact->update(['next_measurement_at' => now()->subMinute()]);
    $future = SeoImpact::factory()->for($this->website)->create(['next_measurement_at' => now()->addDay()]);
    $closed = SeoImpact::factory()->for($this->website)->create(['status' => 'completed', 'next_measurement_at' => now()->subMinute()]);
    $this->artisan('seo:measure-impacts')->assertSuccessful();
    Queue::assertPushed(MeasureSeoImpact::class, 1);
    (new MeasureSeoImpact($this->impact))->failed(new RuntimeException('secret-token'));
    expect($this->impact->fresh()->measurement_error)->not->toContain('secret-token')->and($this->impact->fresh()->outcome)->toBeNull();
});

test('Search Console measurement sends exact bounded filters final data and weighted totals', function (): void {
    $connection = SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
    Http::fake(['*searchAnalytics/query' => Http::response(['rows' => [
        ['keys' => ['2026-09-01'], 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 2],
        ['keys' => ['2026-09-02'], 'clicks' => 30, 'impressions' => 300, 'ctr' => 0.1, 'position' => 6],
    ]])]);
    $result = app(SearchConsoleClient::class)->impactPerformance($connection, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-02'), ['https://example.com/service?a=b'], ['garden (office)'], 'gbr', 'MOBILE');
    expect($result['complete'])->toBeTrue()->and($result['totals']['position'])->toBe(5.0)->and($result['totals']['clicks'])->toBe(40.0);
    Http::assertSent(fn ($request): bool => $request['dataState'] === 'final' && $request['dimensions'] === ['date']
        && data_get($request->data(), 'dimensionFilterGroups.0.filters.1.expression') === '^(garden \\(office\\))$'
        && data_get($request->data(), 'dimensionFilterGroups.0.filters.3.expression') === 'MOBILE');
    Http::assertSentCount(2);
});

test('incomplete and empty Search Console responses stay unavailable', function (): void {
    $connection = SearchConsoleConnection::factory()->for($this->website)->create();
    Http::fake(['*searchAnalytics/query' => Http::response(['rows' => [], 'metadata' => ['first_incomplete_date' => '2026-09-02']])]);
    $result = app(SearchConsoleClient::class)->impactPerformance($connection, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-02'), ['https://example.com/services']);
    expect($result['complete'])->toBeFalse()->and($result['totals'])->toBeNull();
});

test('partial final data never becomes a frozen baseline', function (): void {
    SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
    $this->impact->update(['live_at' => now()->subDays(10), 'status' => 'measuring']);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('impactPerformance')->once()->andReturn([...impactPerformanceSample(), 'finalized' => false]);
    expect(fn () => (new MeasureSeoImpact($this->impact))->handle($client, app(SeoImpactEvaluator::class)))->toThrow(RuntimeException::class, 'still finalising');
    expect($this->impact->fresh()->baseline)->toBeNull()->and($this->impact->reviews()->count())->toBe(0);
});

test('an edited scope cannot be overwritten by an in flight measurement', function (): void {
    SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('impactPerformance')->once()->andReturnUsing(function (): array {
        $this->impact->update(['target_urls' => ['https://example.com/new-service']]);

        return impactPerformanceSample();
    });
    (new MeasureSeoImpact($this->impact))->handle($client, app(SeoImpactEvaluator::class));
    expect($this->impact->fresh()->baseline)->toBeNull()->and($this->impact->fresh()->target_urls)->toBe(['https://example.com/new-service']);
});

test('an overlapping tracked deployment makes the review inconclusive', function (): void {
    SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
    $this->impact->update(['live_at' => Carbon::parse('2026-08-17', 'America/Los_Angeles')->utc(), 'status' => 'measuring']);
    SeoImpact::factory()->for($this->website)->create(['live_at' => now()->subDays(10), 'status' => 'completed']);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('impactPerformance')->twice()->andReturn(impactPerformanceSample(), impactPerformanceSample(150));
    (new MeasureSeoImpact($this->impact))->handle($client, app(SeoImpactEvaluator::class));
    expect($this->impact->fresh()->outcome)->toBe('inconclusive')->and($this->impact->reviews()->first()->assessment['reason'])->toContain('Another tracked change');
});

test('review pages render measured evidence and preserve viewer restrictions', function (): void {
    $plan = ContentPlan::factory()->for($this->website)->create();
    $generation = ContentGeneration::factory()->for($plan, 'plan')->create(['pull_request_url' => 'https://github.com/example/repo/pull/1']);
    $request = ContentRequest::factory()->for($this->website)->create();
    Optimisation::factory()->for($this->website)->create(['content_request_id' => $request->id]);
    $this->impact->update(['content_request_id' => $request->id, 'content_generation_id' => $generation->id, 'live_at' => now()->subDays(60), 'status' => 'review_required', 'review_after_days' => 56,
        'baseline' => [...impactMeasurementSample(), 'start' => '2026-07-01', 'end' => '2026-07-28'],
        'observations' => [...impactMeasurementSample(150), 'start' => '2026-08-26', 'end' => '2026-09-22']]);
    SeoImpactReview::factory()->for($this->impact, 'impact')->create(['checkpoint' => 56]);
    $this->actingAs($this->owner)->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $this->impact->id]))->assertSuccessful()->assertSee('Day 56')->assertSee('Measure for another 28 days')->assertSee('Linked Pixel changes');
    $viewer = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($viewer)->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $this->impact->id]))->assertSuccessful()->assertDontSee('Save decision')->assertDontSee('https://github.com/example/repo/pull/1');
});

test('expired entitlements and inactive websites never collect impact data', function (): void {
    Queue::fake();
    $this->impact->update(['next_measurement_at' => now()->subMinute()]);
    $this->owner->update(['membership_status' => 'canceled']);
    $this->artisan('seo:measure-impacts')->assertSuccessful();
    Queue::assertNothingPushed();
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldNotReceive('impactPerformance');
    (new MeasureSeoImpact($this->impact))->handle($client, app(SeoImpactEvaluator::class));
    $this->owner->update(['membership_status' => 'active']);
    $this->website->update(['is_active' => false]);
    $this->artisan('seo:measure-impacts')->assertSuccessful();
    Queue::assertNothingPushed();
});

test('removing unstarted work cancels its tracking but cannot remove a live measurement', function (): void {
    $request = ContentRequest::factory()->for($this->website)->create();
    $this->impact->update(['content_request_id' => $request->id]);
    $this->actingAs($this->owner)->delete(route('admin.content-requests.destroy', [$this->website, $request]))->assertRedirect();
    expect($this->impact->fresh()->status)->toBe('cancelled');
    $liveRequest = ContentRequest::factory()->for($this->website)->create();
    SeoImpact::factory()->for($this->website)->create(['content_request_id' => $liveRequest->id, 'status' => 'measuring']);
    $this->delete(route('admin.content-requests.destroy', [$this->website, $liveRequest]))->assertUnprocessable();
});

test('a request under measurement cannot create Pixel drafts', function (): void {
    config(['forms.pixel_ui_enabled' => true]);
    $this->impact->update(['status' => 'measuring', 'live_at' => now()->subDays(20)]);
    $request = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Improve https://example.com/services']);
    ContentRequestPixelWriter::fake()->preventStrayPrompts();
    expect(app(ContentRequestPixelOptimisationGenerator::class)->generate($request, $this->owner))->toBe(0);
    ContentRequestPixelWriter::assertNeverPrompted();
    expect($request->fresh()->pixel_error)->toContain('being measured');
});

test('generation briefs include frozen measurement goals and prior learning', function (): void {
    $plan = ContentPlan::factory()->for($this->website)->create();
    $generation = ContentGeneration::factory()->for($plan, 'plan')->create();
    $this->impact->update(['status' => 'completed', 'live_at' => now()->subDays(70), 'decision_notes' => 'Clearer buying information helped; avoid another broad rewrite.']);
    $tracker = app(SeoImpactTracker::class);
    $tracker->forGeneration($generation);
    $prompt = app(ContentGenerationPromptGenerator::class)->generate($generation);
    expect($prompt)->toContain('Measurable SEO briefs and previous results')->toContain('Clearer buying information helped')->toContain('not proof of causation');
    expect(SeoImpact::where('content_generation_id', $generation->id)->count())->toBe(1);
});

test('outside reference links never become automatic target pages', function (): void {
    $request = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Compare https://competitor.test/service and improve https://example.com/services']);
    $impact = app(SeoImpactTracker::class)->forRequest($request);
    expect($impact->target_urls)->toBe(['https://example.com/services']);
    $this->impact->update(['target_urls' => ['https://competitor.test/service']]);
    $this->actingAs($this->owner)->post(route('admin.seo-impacts.live', [$this->website, $this->impact]), [
        'live_date' => '2026-09-17', 'actual_changes' => 'Updated the service title and description.',
        'deployment_evidence' => 'Checked the published release on the live site.', 'confirmed_live' => 1,
    ])->assertUnprocessable();
});

test('comparison pages are protected while their baseline is used', function (): void {
    $this->impact->update(['status' => 'measuring', 'control_url' => 'https://example.com/comparison']);
    $request = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Rewrite https://www.example.com/comparison/']);
    expect(app(SeoImpactTracker::class)->requestIsProtected($request))->toBeTrue();
});

test('SEO impact stays inside SEO Intelligence after recommended actions', function (): void {
    SeoSnapshot::factory()->for($this->website)->create(['status' => 'completed']);
    $url = route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact']);
    $response = $this->actingAs($this->owner)->get($url)->assertSuccessful()
        ->assertViewIs('admin.websites.show')
        ->assertSeeInOrder(['id="seo-section-tab-overview"', 'id="seo-section-tab-targets"', 'id="seo-section-tab-actions"', 'id="seo-section-tab-impact"'], false)
        ->assertSee($this->impact->title)->assertDontSee('Back to Content');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@id="seo-section-tab-impact" and @aria-current="page"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="website-panel-seo" and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="seo-section-panel-impact" and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="seo-section-panel-overview" and @hidden]')->length)->toBe(1);
    $this->get(route('admin.seo-impacts.index', $this->website))->assertRedirect($url);
});

test('impact details and saved briefs retain their SEO tab and back destination', function (): void {
    $list = route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact']);
    $detail = route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $this->impact->id]);
    $this->actingAs($this->owner)->get(route('admin.seo-impacts.show', [$this->website, $this->impact]))->assertRedirect($detail);
    $response = $this->get($detail)->assertSuccessful()->assertViewIs('admin.websites.show')->assertSee('All SEO impact')->assertDontSee('Back to Content');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $link = $xpath->query('//a[contains(., "All SEO impact")]')->item(0);
    expect($link->getAttribute('href'))->toBe($list)
        ->and($xpath->query('//*[@id="seo-section-tab-impact" and @aria-current="page"]')->length)->toBe(1);
    $this->put(route('admin.seo-impacts.update', [$this->website, $this->impact]), $this->brief)->assertRedirect($detail);
});

test('impact pagination keeps the SEO context without needing a paid snapshot', function (): void {
    SeoImpact::factory()->count(20)->for($this->website)->create();
    $response = $this->actingAs($this->owner)->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact']))->assertSuccessful();
    $next = $response->viewData('impacts')->nextPageUrl();
    expect($next)->toContain('/section/seo')->toContain('seo_section=impact')->toContain('impact_page=2');
    $this->get($next)->assertSuccessful()->assertViewHas('impacts', fn ($impacts): bool => $impacts->currentPage() === 2);
});

test('embedded impacts remain website scoped and hidden from lower tiers', function (): void {
    $other = SeoImpact::factory()->create(['title' => 'Private impact evidence']);
    $this->actingAs($this->owner)->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $other->id]))->assertNotFound();
    $this->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact', 'seo_impact' => ['invalid']]))->assertNotFound();
    $this->owner->update(['membership_tier' => 'essential']);
    $this->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $this->impact->id]))
        ->assertSuccessful()->assertDontSee($this->impact->title)->assertDontSee('Save brief');
});
