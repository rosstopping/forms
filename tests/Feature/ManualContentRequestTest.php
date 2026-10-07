<?php

use App\Jobs\GenerateContentRequestPixelOptimisations;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\SeoImpact;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\ContentRequestPixelOptimisationGenerator;
use App\Services\ContentWorkSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->create();
    WebsiteRepository::factory()->for($this->website)->create(['project_path' => 'site']);
    $this->plan = ContentPlan::factory()->for($this->website)->create(['audience' => 'Local families', 'guidance' => 'Preserve verified prices.']);
    $this->request = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Improve the services page for families.']);
    $this->actingAs($this->admin);
});

test('admins preview a scoped full content prompt without changing the queue or starting automation', function (): void {
    SeoImpact::factory()->for($this->website)->create(['content_request_id' => $this->request->id, 'hypothesis' => 'Use the family services brief.']);
    SeoImpact::factory()->create(['hypothesis' => 'PRIVATE OTHER WEBSITE BRIEF', 'content_generation_id' => null]);
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'UNRELATED QUEUED REQUEST']);

    $this->get(route('admin.websites.section', [$this->website, 'content']))
        ->assertSuccessful()->assertSee('Show AI prompt')->assertDontSee('Copy prompt');
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_prompt' => $this->request->id]))
        ->assertSuccessful()->assertSee('Copy prompt')->assertSee('Take for manual work')
        ->assertSee('Local families')->assertSee('Preserve verified prices.')
        ->assertSee('Use the family services brief.')->assertSee('Work in site.')
        ->assertSee('14-day')->assertDontSee('PRIVATE OTHER WEBSITE BRIEF');

    expect($this->request->fresh()->picked_up_at)->toBeNull();
    expect(ContentGeneration::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('manual work is reserved excluded from selection and can return with its original queue position', function (): void {
    $originalDate = $this->request->created_at->toDateTimeString();
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertRedirect();
    $request = $this->request->fresh();
    expect($request->manual_taken_by)->toBe($this->admin->id)
        ->and($request->manual_prompt)->toContain('Improve the services page for families.')
        ->and($request->manual_started_at)->not->toBeNull()
        ->and($this->website->contentRequests()->pendingInQueueOrder()->exists())->toBeFalse();

    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->make(['trigger' => 'manual']);
    expect(app(ContentWorkSelector::class)->select($generation)['requests'])->toBeEmpty();
    $this->get(route('admin.websites.section', [$this->website, 'content']))->assertSuccessful()->assertSee('Manual work (1)')->assertSee('Return to queue');
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertUnprocessable();
    $this->post(route('admin.content-requests.manual.release', [$this->website, $this->request]))->assertRedirect();
    expect($this->request->fresh()->picked_up_at)->toBeNull()
        ->and($this->request->fresh()->manual_started_at)->toBeNull()
        ->and($this->request->fresh()->created_at->toDateTimeString())->toBe($originalDate)
        ->and($this->website->contentRequests()->pendingInQueueOrder()->exists())->toBeTrue();
});

test('manual completion records activity without publishing or marking an impact live', function (): void {
    $impact = SeoImpact::factory()->for($this->website)->create(['content_request_id' => $this->request->id, 'live_at' => null]);
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertRedirect();
    $snapshot = $this->request->fresh()->manual_prompt;
    $this->post(route('admin.content-requests.manual.complete', [$this->website, $this->request]))
        ->assertRedirect(route('admin.websites.section', [$this->website, 'content', 'content_section' => 'activity']));
    expect($this->request->fresh()->manual_completed_at)->not->toBeNull()
        ->and($this->request->fresh()->manual_prompt)->toBe($snapshot)
        ->and($impact->fresh()->live_at)->toBeNull()
        ->and($this->website->contentRequests()->pendingInQueueOrder()->exists())->toBeFalse();
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_section' => 'activity']))->assertSuccessful()->assertSee('Manual work complete');
    $this->post(route('admin.content-requests.manual.release', [$this->website, $this->request]))->assertUnprocessable();
});

test('manual controls are admin only and reject requests from another website', function (): void {
    $other = ContentRequest::factory()->create();
    foreach (['take', 'release', 'complete'] as $action) {
        $this->post(route('admin.content-requests.manual.'.$action, [$this->website, $other]))->assertNotFound();
    }
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_prompt' => $other->id]))->assertNotFound();
    $owner = User::factory()->create(['admin_membership_tier' => 'growth']);
    $this->website->update(['user_id' => $owner->id]);
    $this->actingAs($owner);
    foreach (['take', 'release', 'complete'] as $action) {
        $this->post(route('admin.content-requests.manual.'.$action, [$this->website, $this->request]))->assertForbidden();
    }
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_prompt' => $this->request->id]))->assertForbidden();
    $this->get(route('admin.websites.section', [$this->website, 'content']))->assertSuccessful()->assertDontSee('Show AI prompt');
});

test('manual reservation cannot steal a request from a running or already accepted task', function (): void {
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create(['status' => ContentGeneration::STATUS_RUNNING]);
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertUnprocessable();
    expect($this->request->fresh()->picked_up_at)->toBeNull();
    $generation->update(['status' => ContentGeneration::STATUS_COMPLETED]);
    $this->request->update(['picked_up_at' => now(), 'content_generation_id' => $generation->id]);
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertUnprocessable();
});

test('an already queued Pixel job skips manually reserved work', function (): void {
    $job = new GenerateContentRequestPixelOptimisations($this->request, $this->admin);
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertRedirect();
    $generator = $this->mock(ContentRequestPixelOptimisationGenerator::class);
    $generator->shouldNotReceive('generate');
    $job->handle($generator);
});

test('manual prompts work without a content plan or GitHub credentials', function (): void {
    $this->plan->delete();
    $this->website->repository->delete();
    $this->website->forceFill(['pixel_last_seen_at' => now(), 'pixel_enabled' => true])->save();
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_prompt' => $this->request->id]))
        ->assertSuccessful()->assertSee('Copy prompt')->assertSee('Work in repository root.');
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertRedirect();
    expect(ContentPlan::count())->toBe(0)->and(ContentGeneration::count())->toBe(0);
});

test('reserved prompts remain a snapshot when editorial guidance changes', function (): void {
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertRedirect();
    $this->plan->update(['guidance' => 'UPDATED EDITORIAL GUIDANCE']);
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_prompt' => $this->request->id]))
        ->assertSuccessful()->assertViewHas('contentPrompt', fn (string $prompt): bool => str_contains($prompt, 'Preserve verified prices.') && ! str_contains($prompt, 'UPDATED EDITORIAL GUIDANCE'));
});

test('automation avoids duplicate manual work and resumes after its cooldown', function (): void {
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertRedirect();
    $duplicate = ContentRequest::factory()->for($this->website)->create(['instructions' => $this->request->instructions]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->make(['trigger' => 'manual']);
    expect(app(ContentWorkSelector::class)->select($generation)['requests'])->toBeEmpty();
    $this->post(route('admin.content-requests.manual.complete', [$this->website, $this->request]))->assertRedirect();
    expect(app(ContentWorkSelector::class)->select($generation)['requests'])->toBeEmpty();
    $this->travel(15)->days();
    expect(app(ContentWorkSelector::class)->select($generation)['requests']->modelKeys())->toBe([$duplicate->id]);
});

test('manual previews include only recent saved search evidence for this website', function (): void {
    ContentGeneration::factory()->for($this->plan, 'plan')->create([
        'started_at' => now()->subDays(2),
        'search_performance' => [['query' => 'family services', 'page' => 'https://example.test/services', 'clicks' => 25, 'impressions' => 300, 'ctr' => 0.08, 'position' => 6]],
    ]);
    ContentGeneration::factory()->create([
        'started_at' => now(),
        'search_performance' => [['query' => 'PRIVATE OTHER WEBSITE QUERY']],
    ]);
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_prompt' => $this->request->id]))
        ->assertSuccessful()->assertViewHas('contentPrompt', fn (string $prompt): bool => str_contains($prompt, 'family services') && ! str_contains($prompt, 'PRIVATE OTHER WEBSITE QUERY'));
});

test('manual reservation rejects a request while Pixel is drafting it', function (): void {
    $lock = Cache::lock('content-request-work-'.$this->request->id, 180);
    expect($lock->get())->toBeTrue();
    try {
        $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertUnprocessable();
        expect($this->request->fresh()->picked_up_at)->toBeNull();
    } finally {
        $lock->release();
    }
});
