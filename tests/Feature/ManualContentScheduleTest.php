<?php

use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\User;
use App\Models\Website;
use App\Services\ManualContentSchedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->setDate(2026, 10, 23)->setTime(6, 0));
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->create(['name' => 'Due example']);
    $this->plan = ContentPlan::factory()->for($this->website)->create([
        'content_engine' => 'manual', 'enabled' => false, 'weekday' => 5, 'hour' => 14,
    ]);
    $this->actingAs($this->admin);
});

test('an empty manual queue shows websites due today even before their scheduled time', function (): void {
    $this->get(route('admin.content-queue.index'))->assertSuccessful()->assertSee('Due today')->assertSee('Due example')
        ->assertSee('There’s nothing approved')->assertSee('Choose a suggestion')->assertSee('Add a request')->assertSee('Skip today')
        ->assertDontSee('You’re all caught up');
    expect(app(ManualContentSchedule::class)->forUser($this->admin)->first()['scheduled_at'])->toBe('2026-10-23T14:00:00+01:00');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('due reminders distinguish approved and planned work', function (): void {
    ContentRequest::factory()->for($this->website)->create();
    ContentRequest::factory()->for($this->website)->create(['planning_status' => 'planned']);
    $this->get(route('admin.content-queue.index'))->assertSuccessful()->assertSee('1 approved request waiting')
        ->assertSee('Review planned content (1)')->assertSee('Open queue');
});

test('skipping a reminder does not change tasks or suppress next weeks reminder', function (): void {
    $task = ContentRequest::factory()->for($this->website)->create();
    $this->post(route('admin.content-queue.skip-today', $this->website))->assertRedirect(route('admin.content-queue.index'));
    expect($this->plan->fresh()->manual_skipped_on->toDateString())->toBe('2026-10-23')
        ->and($task->fresh()->picked_up_at)->toBeNull()
        ->and(app(ManualContentSchedule::class)->forUser($this->admin))->toBeEmpty();
    $this->travel(1)->week();
    expect(app(ManualContentSchedule::class)->forUser($this->admin))->toHaveCount(1);
    Queue::assertNothingPushed();
});

test('inactive switched off automated and non due sites do not show manual reminders', function (array $attributes, array $websiteAttributes): void {
    $this->plan->update($attributes);
    $this->website->update($websiteAttributes);
    expect(app(ManualContentSchedule::class)->forUser($this->admin))->toBeEmpty();
})->with([
    'off' => [['manual_reminders_enabled' => false], []],
    'automatic' => [['content_engine' => 'copilot'], []],
    'tomorrow' => [['weekday' => 6], []],
    'inactive' => [[], ['is_active' => false]],
]);

test('active manual work and work completed on the local day handle the reminder', function (bool $completed): void {
    ContentRequest::factory()->for($this->website)->create([
        'manual_started_at' => now()->subHour(), 'picked_up_at' => now()->subHour(),
        'manual_completed_at' => $completed ? now() : null,
    ]);
    expect(app(ManualContentSchedule::class)->forUser($this->admin))->toBeEmpty();
})->with([true, false]);

test('reminders use the websites local date and do not count yesterdays completion', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(23, 30));
    $this->plan->update(['timezone' => 'Asia/Tokyo']);
    $task = ContentRequest::factory()->for($this->website)->create([
        'picked_up_at' => now()->subDay(), 'manual_started_at' => now()->subDay(),
        'manual_completed_at' => now()->subHours(16),
    ]);
    expect(app(ManualContentSchedule::class)->forUser($this->admin))->toHaveCount(1);
    $task->update(['manual_completed_at' => now()->subMinutes(15)]);
    expect(app(ManualContentSchedule::class)->forUser($this->admin))->toBeEmpty();
});

test('assigned admins can only see and skip reminders for their assigned websites', function (): void {
    $assigned = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_site_access' => 'assigned']);
    $this->actingAs($assigned)->get(route('admin.content-queue.index'))->assertSuccessful()->assertDontSee('Due example');
    $this->post(route('admin.content-queue.skip-today', $this->website))->assertForbidden();
    $assigned->assignedWebsites()->attach($this->website);
    $this->get(route('admin.content-queue.index'))->assertSuccessful()->assertSee('Due example');
    $this->post(route('admin.content-queue.skip-today', $this->website))->assertRedirect();
    $this->actingAs($this->website->owner)->post(route('admin.content-queue.skip-today', $this->website))->assertForbidden();
});

test('manual reminders can be switched off without enabling automatic execution', function (): void {
    $this->put(route('admin.content-plans.update', $this->website), [
        'content_engine' => 'manual', 'enabled' => true, 'manual_reminders_enabled' => false,
        'weekday' => 5, 'hour' => 8, 'timezone' => 'Europe/London',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($this->plan->fresh()->enabled)->toBeFalse()->and($this->plan->fresh()->manual_reminders_enabled)->toBeFalse();
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_section' => 'automation']))
        ->assertSuccessful()->assertSee('Show due-today reminders when Manual is selected');
    Queue::assertNothingPushed();
});
