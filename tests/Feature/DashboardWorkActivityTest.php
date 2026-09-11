<?php

use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\RemediationRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Services\DashboardWorkActivity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('flags failed and overdue work without mutating records or starting jobs', function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->startOfSecond());
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $failed = WebsiteHealthReport::factory()->for($website)->create(['status' => 'failed', 'error' => 'The website timed out.']);
    WebsiteHealthReport::factory()->for($website)->create(['status' => 'running', 'started_at' => now()->subMinutes(16)]);
    WebsiteHealthReport::factory()->for($website)->create(['status' => 'running', 'started_at' => now()->subMinutes(14)]);
    WebsiteHealthReport::factory()->for($website)->create(['status' => 'pending', 'created_at' => now()->subMinutes(61)]);
    WebsiteHealthReport::factory()->for($website)->create(['status' => 'pending', 'created_at' => now()->subMinutes(59)]);
    $plan = ContentPlan::factory()->for($website)->create(['enabled' => false]);
    ContentGeneration::factory()->for($plan, 'plan')->create(['status' => 'running', 'started_at' => now()->subHours(3)]);
    ContentGeneration::factory()->for($plan, 'plan')->create(['status' => 'pending', 'scheduled_for' => today()->addDay(), 'created_at' => now()->subHours(3)]);
    RemediationRun::factory()->for($failed, 'report')->create(['status' => 'awaiting_runner', 'created_at' => now()->subHours(2)]);

    $this->actingAs($admin)->get(route('admin.overview'))->assertSuccessful()
        ->assertSee('Needs attention')->assertSee('Recently completed')->assertSee('The website timed out.')
        ->assertViewHas('workActivity', fn ($activity): bool => $activity['attention']->count() === 5
            && $activity['completed']->isEmpty()
            && $activity['attention']->where('status', 'Possibly stalled')->count() === 4);
    expect($failed->fresh()->status)->toBe('failed');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('shows newest completions across sources and distinguishes merged from manually completed fixes', function (): void {
    $website = Website::factory()->create();
    $report = WebsiteHealthReport::factory()->for($website)->create(['completed_at' => now()->subHours(3)]);
    $plan = ContentPlan::factory()->for($website)->create();
    ContentGeneration::factory()->for($plan, 'plan')->create(['status' => 'completed', 'merged_at' => now()->subHour()]);
    RemediationRun::factory()->for($report, 'report')->create(['status' => 'completed', 'completed_at' => now()->subHours(2), 'merged_at' => null]);
    WebsiteHealthReport::factory()->create(['status' => 'failed']);
    WebsiteHealthReport::factory()->create();

    $activity = app(DashboardWorkActivity::class)->forWebsites([$website->id]);
    expect($activity['attention'])->toBeEmpty()
        ->and($activity['completed']->pluck('type')->all())->toBe(['Content generation', 'Website fixes', 'Health report'])
        ->and($activity['completed']->pluck('status')->all())->toBe(['Merged', 'Completed', 'Completed'])
        ->and($activity['completed'][0]['url'])->toBe(route('admin.websites.section', [$website, 'content']))
        ->and($activity['completed'][1]['url'])->toBe(route('admin.website-health-reports.show', [$website, $report]));
});

it('limits completion lists by completion time rather than creation time', function (): void {
    $website = Website::factory()->create();
    WebsiteHealthReport::factory()->count(11)->for($website)->create(['completed_at' => now()->subDays(2)]);
    $recent = WebsiteHealthReport::factory()->for($website)->create(['created_at' => now()->subMonth(), 'completed_at' => now()]);
    $activity = app(DashboardWorkActivity::class)->forWebsites([$website->id]);
    expect($activity['completed'])->toHaveCount(10)
        ->and($activity['completed'][0]['url'])->toBe(route('admin.website-health-reports.show', [$website, $recent]));
});
