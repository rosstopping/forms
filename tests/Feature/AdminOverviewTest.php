<?php

use App\Enums\OptimisationStatus;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\GithubUserAuthorization;
use App\Models\Optimisation;
use App\Models\RemediationRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Models\WebsiteRepository;
use Illuminate\Support\Facades\Http;

it('shows cross-site schedules and pending reviews without changing the selected website', function (): void {
    Http::preventStrayRequests();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $selected = Website::factory()->create();
    $other = Website::factory()->create(['health_reports_enabled' => true]);
    $admin->update(['current_website_id' => $selected->id]);
    $report = WebsiteHealthReport::factory()->for($other)->create();
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create();
    $draft = Optimisation::factory()->for($other)->for($page, 'page')->create();
    Optimisation::factory()->for($selected)->create(['status' => OptimisationStatus::PendingApproval]);
    Optimisation::factory()->for($other)->create(['status' => OptimisationStatus::Deployed]);
    $plan = ContentPlan::factory()->for($other)->create(['enabled' => false]);
    ContentGeneration::factory()->for($plan, 'plan')->create(['status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN]);
    ContentGeneration::factory()->for($plan, 'plan')->create(['status' => ContentGeneration::STATUS_COMPLETED, 'scheduled_for' => today()->subDay()]);
    RemediationRun::factory()->for($report, 'report')->create(['status' => RemediationRun::STATUS_PULL_REQUEST_OPEN]);

    $this->actingAs($admin)->get(route('admin.overview'))
        ->assertSuccessful()
        ->assertSee('Upcoming schedule')
        ->assertSee('Actions to review')
        ->assertSee($other->name)
        ->assertSee('href="'.route('admin.website-health-report-pages.show', [$other, $report, $page]).'"', false)
        ->assertViewHas('approvalCount', 4)
        ->assertViewHas('optimisations', fn ($items): bool => $items->total() === 2 && $items->contains('id', $draft->id))
        ->assertViewHas('automationSchedule', fn ($items): bool => $items->count() === 1 && $items->first()['website']->is($other));

    expect($admin->fresh()->current_website_id)->toBe($selected->id);
    Http::assertNothingSent();
});

it('restricts the admin overview and its navigation to administrators', function (): void {
    $this->get(route('admin.overview'))->assertRedirect(route('login'));
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('admin.overview'))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertDontSee('href="'.route('admin.overview').'"', false);
});

it('renders an empty admin overview with desktop and mobile navigation', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $response = $this->actingAs($admin)->get(route('admin.overview'))
        ->assertSuccessful()
        ->assertSee('No upcoming automation is scheduled.')
        ->assertSee('No draft changes awaiting review.')
        ->assertViewHas('approvalCount', 0);

    expect(substr_count($response->getContent(), 'href="'.route('admin.overview').'"'))->toBe(2);
});

it('orders eligible health and content runs and excludes inactive or expired websites', function (): void {
    $this->travelTo(now()->setDate(2026, 9, 14)->setTime(12, 0));
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $website = Website::factory()->create(['health_reports_enabled' => true]);
    WebsiteRepository::factory()->for($website)->create();
    ContentPlan::factory()->for($website)->for($admin, 'creator')->create(['weekday' => 2, 'hour' => 9]);
    Website::factory()->create(['is_active' => false, 'health_reports_enabled' => true]);
    $expired = User::factory()->create(['membership_status' => 'cancelled', 'membership_current_period_end' => now()->subDay()]);
    Website::factory()->for($expired, 'owner')->create(['health_reports_enabled' => true]);

    $this->actingAs($admin)->get(route('admin.overview'))
        ->assertSuccessful()
        ->assertViewHas('automationSchedule', function ($items) use ($website): bool {
            return $items->pluck('type')->all() === ['Health report', 'Content queue']
                && $items->every(fn ($item): bool => $item['website']->is($website))
                && $items->first()['next_run_at']->lessThan($items->last()['next_run_at']);
        });
});
