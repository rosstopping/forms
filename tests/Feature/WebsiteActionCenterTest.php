<?php

use App\Models\ContentRequest;
use App\Models\SearchOpportunity;
use App\Models\SeoImpact;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\WebsiteActionCenter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    config(['forms.pixel_ui_enabled' => false]);
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $this->report = WebsiteHealthReport::factory()->for($this->website)->create();
    $this->page = WebsiteHealthReportPage::factory()->for($this->report, 'report')->create(['url' => 'https://example.com/services', 'checks' => [
        ['key' => 'page_title', 'label' => 'Missing title', 'status' => 'failed', 'message' => 'Add a descriptive title.'],
    ]]);
    $this->opportunity = SearchOpportunity::factory()->for($this->website)->create(['page' => 'https://www.example.com/services/', 'query' => 'garden offices']);
});

test('the action list combines evidence by page and queueing is idempotent', function (): void {
    $actions = app(WebsiteActionCenter::class)->forWebsite($this->website);
    expect($actions)->toHaveCount(1)->and($actions[0]['sources'])->toContain('audit', 'search')->and($actions[0]['stage'])->toBe('open');
    $this->actingAs($this->owner);
    foreach ([1, 2] as $attempt) {
        $this->post(route('admin.website-actions.queue', $this->website), ['action_key' => $actions[0]['key']])->assertRedirect()->assertSessionHasNoErrors();
    }
    expect(ContentRequest::count())->toBe(1)->and(SeoImpact::count())->toBe(1)
        ->and(ContentRequest::sole()->seoImpact->target_queries)->toBe(['garden offices'])
        ->and(ContentRequest::sole()->seoImpact->evidence['checks'])->toHaveKey('https://example.com/services')
        ->and(app(WebsiteActionCenter::class)->forWebsite($this->website)[0]['stage'])->toBe('queued');
});

test('new findings on a completed page can be queued without reviving old recommendations', function (): void {
    $service = app(WebsiteActionCenter::class);
    $request = $service->queue($this->website, $this->owner, $service->forWebsite($this->website)[0]['key']);
    $request->seoImpact->update(['status' => 'completed', 'verified_at' => now(), 'verification' => ['pages' => [['url' => $this->page->url, 'checks' => [['key' => 'page_title', 'status' => 'passed']]]]]]);
    expect($service->forWebsite($this->website)->where('stage', 'open'))->toHaveCount(0);
    $this->travel(1)->days();
    $newReport = WebsiteHealthReport::factory()->for($this->website)->create();
    WebsiteHealthReportPage::factory()->for($newReport, 'report')->create(['url' => $this->page->url, 'checks' => $this->page->checks]);
    $newAction = $service->forWebsite($this->website)->firstWhere('stage', 'open');
    expect($newAction)->not->toBeNull()->and($newAction['key'])->not->toBe($request->action_fingerprint);
    $service->queue($this->website, $this->owner, $newAction['key']);
    expect(ContentRequest::count())->toBe(2);
});

test('measuring and unread results remain visible even without a recommendation source', function (): void {
    $impact = SeoImpact::factory()->for($this->website)->create(['target_urls' => ['https://example.com/about'], 'status' => 'measuring', 'live_at' => now()]);
    expect(app(WebsiteActionCenter::class)->forWebsite($this->website)->where('stage', 'measuring'))->toHaveCount(1);
    $impact->update(['status' => 'completed', 'review_available_at' => now(), 'automatic_summary' => 'Week 8: improved. Keep this change.']);
    expect(app(WebsiteActionCenter::class)->forWebsite($this->website)->where('stage', 'review'))->toHaveCount(1);
    $this->actingAs($this->owner)->get(route('admin.dashboard', ['website' => $this->website->id]))->assertSuccessful()->assertSee('SEO results ready to review');
});

test('actions and page workspace work without a paid SEO snapshot and issue no external requests', function (): void {
    $this->actingAs($this->owner)->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'actions']))->assertSuccessful()->assertSee('Your prioritised action list')->assertSee('Missing title');
    $this->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'pages', 'page_url' => $this->page->url]))->assertSuccessful()->assertSee('Missing title')->assertSee('garden offices');
    $this->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'pages', 'page_url' => 'https://other.com/private']))->assertNotFound();
    Http::assertNothingSent();
});

test('viewers can inspect evidence but cannot queue or acknowledge and foreign actions stay scoped', function (): void {
    $viewer = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $action = app(WebsiteActionCenter::class)->forWebsite($this->website)[0];
    $impact = SeoImpact::factory()->for($this->website)->create(['review_available_at' => now()]);
    $this->actingAs($viewer)->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'actions']))->assertSuccessful()->assertDontSee('Add to content queue');
    $this->post(route('admin.website-actions.queue', $this->website), ['action_key' => $action['key']])->assertForbidden();
    $this->post(route('admin.seo-impacts.acknowledge', [$this->website, $impact]))->assertForbidden();
    $this->post(route('admin.seo-impacts.followup', [$this->website, $impact]))->assertForbidden();
    $other = Website::factory()->for($this->owner, 'owner')->create();
    $this->actingAs($this->owner)->post(route('admin.website-actions.queue', $other), ['action_key' => $action['key']])->assertNotFound();
    $this->post(route('admin.seo-impacts.acknowledge', [$other, $impact]))->assertNotFound();
    expect(ContentRequest::count())->toBe(0);
});

test('removing grouped work reopens every linked observation', function (): void {
    $second = SearchOpportunity::factory()->for($this->website)->create(['page' => $this->page->url, 'query' => 'garden rooms']);
    $service = app(WebsiteActionCenter::class);
    $request = $service->queue($this->website, $this->owner, $service->forWebsite($this->website)[0]['key']);
    $this->actingAs($this->owner)->delete(route('admin.content-requests.destroy', [$this->website, $request]))->assertRedirect();
    expect($this->opportunity->fresh()->status)->toBe('open')->and($second->fresh()->status)->toBe('open')
        ->and($service->forWebsite($this->website)->where('stage', 'open'))->toHaveCount(1);
});

test('fresh search evidence after a completed measurement can create a new action on the same page', function (): void {
    $service = app(WebsiteActionCenter::class);
    $request = $service->queue($this->website, $this->owner, $service->forWebsite($this->website)[0]['key']);
    $request->seoImpact->update(['status' => 'completed', 'reviewed_at' => now(), 'verified_at' => now(), 'verification' => ['pages' => [['url' => $this->page->url, 'checks' => [['key' => 'page_title', 'status' => 'passed']]]]]]);
    $this->travel(2)->days();
    $this->opportunity->update(['last_detected_at' => now()]);
    $new = $service->forWebsite($this->website)->firstWhere('stage', 'open');
    expect($new)->not->toBeNull()->and($new['key'])->not->toBe($request->action_fingerprint);
    $service->queue($this->website, $this->owner, $new['key']);
    expect(ContentRequest::count())->toBe(2);
});
