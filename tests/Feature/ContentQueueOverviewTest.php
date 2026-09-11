<?php

use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\GithubUserAuthorization;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Support\WebsiteNavigation;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->travelTo(now()->setDate(2026, 9, 14)->setTime(12, 0));
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($this->admin)->create();
    $this->website = Website::factory()->create(['name' => 'Scheduled site']);
    $this->repository = WebsiteRepository::factory()->for($this->website)->create();
    $this->plan = ContentPlan::factory()->for($this->website)->for($this->admin, 'creator')->create(['weekday' => 2, 'hour' => 9]);
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'Improve https://example.com/services']);
});

it('shows queue totals with blocked websites first and a scheduled run', function (): void {
    $blocked = Website::factory()->create(['name' => 'Setup needed']);
    ContentRequest::factory()->count(2)->for($blocked)->create();
    ContentRequest::factory()->for($blocked)->create(['picked_up_at' => now()]);
    $this->actingAs($this->admin)->get(route('admin.overview'))->assertSuccessful()
        ->assertSee('href="#content-queue"', false)
        ->assertSee('Requests awaiting preparation')
        ->assertSeeInOrder(['id="approvals-heading"', 'id="content-queue-heading"', 'id="schedule-heading"'], false)
        ->assertViewHas('contentQueue', function ($rows) use ($blocked): bool {
            return $rows->count() === 2 && $rows->sum('count') === 3
                && $rows[0]['website']->is($blocked) && $rows[0]['state'] === 'Needs setup'
                && $rows[0]['url'] === WebsiteNavigation::routeFor($blocked, 'content')
                && $rows[1]['state'] === 'Scheduled' && $rows[1]['next_run_at']->isFuture();
        });
    Http::assertNothingSent();
});

it('explains setup blockers and provides the relevant action', function (string $condition, string $reason, string $action): void {
    match ($condition) {
        'inactive' => $this->website->update(['is_active' => false]),
        'subscription' => $this->website->owner->update(['membership_status' => 'cancelled', 'membership_current_period_end' => now()->subDay()]),
        'disabled' => $this->plan->update(['enabled' => false]),
        'repository' => $this->repository->delete(),
        'authorization' => $this->admin->githubAuthorization()->delete(),
    };
    $this->actingAs($this->admin)->get(route('admin.overview'))->assertSuccessful()
        ->assertViewHas('contentQueue', fn ($rows): bool => $rows->sole()['state'] === 'Needs setup'
            && str_contains($rows->sole()['reason'], $reason)
            && $rows->sole()['action'] === $action && $rows->sole()['next_run_at'] === null);
})->with([
    ['inactive', 'inactive', 'View settings'],
    ['subscription', 'subscription', 'View account'],
    ['disabled', 'switched off', 'Configure schedule'],
    ['repository', 'repository', 'Connect GitHub'],
    ['authorization', 'connect content automation', 'Connect automation'],
]);

it('pauses queued content while a generation is pending or running', function (string $status): void {
    ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->create(['status' => $status]);
    $this->actingAs($this->admin)->get(route('admin.overview'))->assertSuccessful()
        ->assertViewHas('contentQueue', fn ($rows): bool => $rows->sole()['state'] === 'Paused'
            && $rows->sole()['action'] === 'View generation' && $rows->sole()['next_run_at'] === null);
})->with([ContentGeneration::STATUS_PENDING, ContentGeneration::STATUS_RUNNING]);

it('only pauses for open review when every waiting request overlaps', function (): void {
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->create([
        'status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN, 'pull_request_state' => 'open',
    ]);
    ContentRequest::factory()->for($this->website)->for($generation, 'generation')->create([
        'instructions' => 'Improve https://example.com/services', 'picked_up_at' => now(),
    ]);
    $this->actingAs($this->admin)->get(route('admin.overview'))->assertSuccessful()
        ->assertViewHas('contentQueue', fn ($rows): bool => $rows->sole()['state'] === 'Paused' && $rows->sole()['action'] === 'Review pull requests');
    ContentRequest::factory()->for($this->website)->create(['instructions' => 'Write a new about page']);
    $this->get(route('admin.overview'))->assertSuccessful()
        ->assertViewHas('contentQueue', fn ($rows): bool => $rows->sole()['state'] === 'Scheduled' && $rows->sole()['count'] === 2);
});

it('renders an empty queue after all requests have been picked up', function (): void {
    $this->website->contentRequests()->update(['picked_up_at' => now()]);
    $this->actingAs($this->admin)->get(route('admin.overview'))->assertSuccessful()
        ->assertSee('No content requests awaiting preparation.')
        ->assertViewHas('contentQueue', fn ($rows): bool => $rows->isEmpty());
});
