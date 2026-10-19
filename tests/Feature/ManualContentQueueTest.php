<?php

use App\Jobs\GenerateContentRequestPixelOptimisations;
use App\Jobs\StartContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\User;
use App\Models\Website;
use App\Services\ContentRequestPixelOptimisationGenerator;
use App\Services\ContentSchedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->create();
    $this->plan = ContentPlan::factory()->for($this->website)->create(['content_engine' => ContentPlan::ENGINE_MANUAL]);
    $this->task = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Improve the family services page.']);
    $this->actingAs($this->admin);
});

test('manual engine saves without automation credentials and turns off scheduling', function (): void {
    $this->plan->update(['content_engine' => ContentPlan::ENGINE_COPILOT, 'enabled' => true]);
    $this->put(route('admin.content-plans.update', $this->website), [
        'content_engine' => 'manual', 'enabled' => true, 'weekday' => 1, 'hour' => 8, 'timezone' => 'Europe/London',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($this->plan->fresh()->content_engine)->toBe('manual')
        ->and($this->plan->fresh()->enabled)->toBeFalse()
        ->and(app(ContentSchedule::class)->pauseReason($this->plan->fresh()))->toContain('Manual content')
        ->and(app(ContentSchedule::class)->nextRunAt($this->plan->fresh()))->toBeNull();
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_section' => 'automation']))
        ->assertSuccessful()->assertSee('Manual — copy prompts into Codex')->assertSee('My content queue')->assertDontSee('Generate now');
    Queue::assertNothingPushed();
});

test('manual queue shows approved manual tasks and only my existing reservations', function (): void {
    $otherWebsite = Website::factory()->create();
    ContentPlan::factory()->for($otherWebsite)->create(['content_engine' => 'copilot']);
    ContentRequest::factory()->for($otherWebsite)->create(['instructions' => 'AUTOMATED TASK']);
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'PLANNED TASK', 'planning_status' => 'planned']);
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'ALREADY DONE', 'manual_completed_at' => now(), 'picked_up_at' => now()]);
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'OTHER STAFF TASK', 'manual_taken_by' => User::factory()->create()->id, 'manual_started_at' => now(), 'picked_up_at' => now()]);
    ContentRequest::factory()->for($otherWebsite)->create(['instructions' => 'MY EXISTING TASK', 'manual_taken_by' => $this->admin->id, 'manual_started_at' => now(), 'picked_up_at' => now(), 'manual_prompt' => 'Saved prompt']);

    $this->get(route('admin.content-queue.index'))->assertSuccessful()
        ->assertSee('Improve the family services page.')->assertSee('MY EXISTING TASK')
        ->assertDontSee('AUTOMATED TASK')->assertDontSee('PLANNED TASK')->assertDontSee('ALREADY DONE')->assertDontSee('OTHER STAFF TASK');
    expect($this->task->fresh()->picked_up_at)->toBeNull();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('personal queue supports start copy complete and next without leaving the queue', function (): void {
    $next = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Write a different useful article.']);
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->task]), ['return_to' => 'my-queue'])
        ->assertRedirect(route('admin.content-queue.index', ['task' => $this->task->id]));
    $this->get(route('admin.content-queue.index', ['task' => $this->task->id]))->assertSuccessful()
        ->assertSee('Copy AI prompt')->assertSee('Mark done &amp; next', false)
        ->assertSee('Improve the family services page.')->assertSee('Manual content task for your AI coding assistant');
    $this->post(route('admin.content-requests.manual.complete', [$this->website, $this->task]), ['return_to' => 'my-queue'])
        ->assertRedirect(route('admin.content-queue.index'));
    expect($this->task->fresh()->manual_completed_at)->not->toBeNull();
    $this->get(route('admin.content-queue.index'))->assertSuccessful()->assertDontSee('Improve the family services page.')
        ->assertViewHas('selected', fn ($selected) => $selected->is($next));
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('personal queue can return a task and does not allow completing someone elses reservation', function (): void {
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->task]), ['return_to' => 'my-queue'])->assertRedirect();
    $this->post(route('admin.content-requests.manual.release', [$this->website, $this->task]), ['return_to' => 'my-queue'])
        ->assertRedirect(route('admin.content-queue.index'));
    expect($this->task->fresh()->picked_up_at)->toBeNull();
    $this->task->update(['manual_taken_by' => User::factory()->create()->id, 'manual_started_at' => now(), 'picked_up_at' => now()]);
    $this->get(route('admin.content-queue.index', ['task' => $this->task->id]))->assertNotFound();
    foreach (['complete', 'release'] as $action) {
        $this->post(route('admin.content-requests.manual.'.$action, [$this->website, $this->task]), ['return_to' => 'my-queue'])->assertForbidden();
    }
});

test('manual engine blocks direct and already queued automated generation', function (): void {
    $this->post(route('admin.content-generations.store', $this->website))->assertUnprocessable();
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create(['status' => ContentGeneration::STATUS_PENDING, 'requested_by' => $this->admin->id]);
    app()->call([new StartContentGeneration($generation), 'handle']);
    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_SKIPPED)
        ->and($generation->fresh()->skip_reason)->toContain('Manual content');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('personal queue is admin only and respects assigned website access', function (): void {
    $assigned = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_site_access' => 'assigned']);
    $this->actingAs($assigned)->get(route('admin.content-queue.index'))->assertSuccessful()->assertDontSee($this->task->instructions);
    $this->get(route('admin.content-queue.index', ['task' => $this->task->id]))->assertNotFound();
    $assigned->assignedWebsites()->attach($this->website);
    $this->get(route('admin.content-queue.index'))->assertSuccessful()->assertSee($this->task->instructions);
    $this->actingAs($this->website->owner)->get(route('admin.content-queue.index'))->assertForbidden();
});

test('manual tasks can be created without a delivery connection and do not start Pixel drafting', function (): void {
    $this->website->update(['pixel_enabled' => true]);
    config(['forms.pixel_ui_enabled' => true]);
    $this->post(route('admin.content-requests.store', $this->website), ['instructions' => 'New manual request from the content workspace.'])
        ->assertRedirect();
    $task = $this->website->contentRequests()->latest('id')->firstOrFail();
    $this->get(route('admin.content-queue.index', ['task' => $task->id]))->assertSuccessful()->assertSee($task->instructions);
    $generator = $this->mock(ContentRequestPixelOptimisationGenerator::class);
    $generator->shouldNotReceive('generate');
    (new GenerateContentRequestPixelOptimisations($task, $this->admin))->handle($generator);
    Queue::assertNothingPushed();
});

test('held tasks explain their blocker and cannot be started', function (): void {
    $this->task->update(['held_at' => now(), 'hold_reason' => 'Waiting for the owner to approve the brief.']);
    $this->get(route('admin.content-queue.index', ['task' => $this->task->id]))->assertSuccessful()
        ->assertSee('Waiting for the owner to approve the brief.')->assertDontSee('Start task');
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->task]), ['return_to' => 'my-queue'])->assertUnprocessable();
    expect($this->task->fresh()->picked_up_at)->toBeNull();
});

test('finishing the final task shows the empty queue and repeated completion is rejected', function (): void {
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->task]), ['return_to' => 'my-queue'])->assertRedirect();
    $this->post(route('admin.content-requests.manual.complete', [$this->website, $this->task]), ['return_to' => 'my-queue'])->assertRedirect();
    $this->get(route('admin.content-queue.index'))->assertSuccessful()->assertSee('You’re all caught up');
    $this->post(route('admin.content-requests.manual.complete', [$this->website, $this->task]), ['return_to' => 'my-queue'])->assertUnprocessable();
});
