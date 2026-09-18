<?php

use App\Models\ContentRequest;
use App\Models\SearchOpportunity;
use App\Models\SeoImpact;
use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteActionCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    config(['forms.pixel_ui_enabled' => false]);
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->first = Website::factory()->create(['name' => 'Alpha Gardens']);
    $this->second = Website::factory()->create(['name' => 'Bravo Homes']);
    $this->first->domains()->create(['domain' => 'alpha.example', 'is_primary' => true]);
    $this->second->domains()->create(['domain' => 'bravo.example', 'is_primary' => true]);
    $this->low = SearchOpportunity::factory()->for($this->first)->create(['page' => 'https://alpha.example/services', 'priority_score' => 40, 'recommendation' => 'Explain the Alpha service.']);
    $this->high = SearchOpportunity::factory()->for($this->second)->create(['page' => 'https://bravo.example/services', 'priority_score' => 85, 'recommendation' => 'Clarify the Bravo service.']);
});

test('the admin hub ranks website actions together and preserves the selected website', function (): void {
    $this->admin->update(['current_website_id' => $this->first->id]);
    $response = $this->actingAs($this->admin)->get(route('admin.overview'))->assertSuccessful()
        ->assertSee('Your prioritised action list')->assertSeeInOrder(['Clarify the Bravo service.', 'Explain the Alpha service.'])
        ->assertViewHas('priorityActions', fn ($rows) => $rows->total() === 2 && $rows->first()['website']->is($this->second));
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@id="hub-priorities" and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="hub-automation" and @hidden]')->length)->toBe(1)
        ->and($this->admin->fresh()->current_website_id)->toBe($this->first->id);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

test('website filters and section navigation stay in the admin hub', function (): void {
    $response = $this->actingAs($this->admin)->get(route('admin.overview', ['site_id' => $this->first->id, 'hub' => 'websites']))->assertSuccessful()
        ->assertViewHas('websites', fn ($rows) => $rows->modelKeys() === [$this->first->id])
        ->assertViewHas('priorityActions', fn ($rows) => $rows->total() === 1)
        ->assertSee('Explain the Alpha service.')->assertDontSee('Clarify the Bravo service.');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@id="hub-websites" and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="hub-tab-websites" and @aria-current="page"]')->length)->toBe(1);
    $this->from(route('admin.overview'))->get(route('admin.overview', ['site_id' => 999999]))->assertRedirect()->assertSessionHasErrors('site_id');
});

test('queueing from the hub is idempotent and returns to the same website filter', function (): void {
    $this->admin->update(['current_website_id' => $this->second->id]);
    $key = app(WebsiteActionCenter::class)->forWebsite($this->first)->first()['key'];
    $this->actingAs($this->admin);
    foreach ([1, 2] as $attempt) {
        $this->post(route('admin.website-actions.queue', $this->first), ['action_key' => $key, 'return_to' => 'overview', 'overview_site_id' => $this->first->id])
            ->assertRedirect(route('admin.overview', ['hub' => 'priorities', 'site_id' => $this->first->id]));
    }
    expect(ContentRequest::count())->toBe(1)->and(ContentRequest::sole()->website_id)->toBe($this->first->id)
        ->and($this->admin->fresh()->current_website_id)->toBe($this->second->id);
    $this->get(route('admin.overview', ['action_state' => 'queued']))->assertSuccessful()->assertViewHas('priorityActions', fn ($rows) => $rows->total() === 1);
});

test('the hub stays admin only and its return target cannot be used by a website manager', function (): void {
    $owner = $this->first->owner;
    $key = app(WebsiteActionCenter::class)->forWebsite($this->first)->first()['key'];
    $this->actingAs($owner)->get(route('admin.overview'))->assertForbidden();
    $this->post(route('admin.website-actions.queue', $this->first), ['action_key' => $key, 'return_to' => 'overview'])->assertForbidden();
    expect(ContentRequest::count())->toBe(0);
});

test('priority pagination and all unread results are available with filters preserved', function (): void {
    SearchOpportunity::factory()->count(14)->for($this->first)->sequence(fn ($sequence) => ['page' => 'https://alpha.example/page-'.$sequence->index])->create();
    SeoImpact::factory()->count(12)->for($this->first)->create(['status' => 'completed', 'review_available_at' => now()]);
    $this->actingAs($this->admin)->get(route('admin.overview', ['site_id' => $this->first->id, 'actions_page' => 2]))->assertSuccessful()
        ->assertViewHas('priorityActions', fn ($rows) => $rows->count() === 3 && $rows->total() === 15);
    $this->get(route('admin.overview', ['hub' => 'results', 'site_id' => $this->first->id, 'results_page' => 2]))->assertSuccessful()
        ->assertViewHas('impactReviews', fn ($rows) => $rows->count() === 2 && $rows->total() === 12);
});

test('portfolio recommendations load in batches rather than a query per website', function (): void {
    $sites = Website::factory()->count(8)->create();
    DB::enableQueryLog();
    app(WebsiteActionCenter::class)->forWebsites($sites->take(1));
    $single = count(DB::getQueryLog());
    DB::flushQueryLog();
    app(WebsiteActionCenter::class)->forWebsites($sites);
    $multiple = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($multiple)->toBeLessThanOrEqual($single + 1);
});
