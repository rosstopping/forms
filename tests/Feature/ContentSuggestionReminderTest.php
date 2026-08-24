<?php

use App\Mail\ContentSuggestionReminder;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\SearchOpportunity;
use App\Models\SeoOpportunity;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('an empty content queue receives suggestions 24 hours before its weekly run only once', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13 06:00:00', 'Europe/London'));
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    WebsiteRepository::factory()->for($website)->create();
    $plan = ContentPlan::factory()->for($website)->for($admin, 'creator')->create(['weekday' => 5, 'hour' => 6, 'timezone' => 'Europe/London']);
    SearchOpportunity::factory()->for($website)->create(['status' => SearchOpportunity::STATUS_OPEN, 'title' => 'Improve a Search Console landing page']);
    SeoOpportunity::factory()->for($website)->create(['status' => SeoOpportunity::STATUS_OPEN, 'title' => 'Target a striking-distance keyword']);

    $this->artisan('content:send-suggestion-reminders')->assertSuccessful();
    $this->artisan('content:send-suggestion-reminders')->assertSuccessful();

    Mail::assertQueued(ContentSuggestionReminder::class, fn (ContentSuggestionReminder $mail): bool => $mail->hasTo($admin->email)
        && $mail->searchOpportunities->count() === 1
        && $mail->seoOpportunities->count() === 1);
    Mail::assertQueuedCount(1);
    expect($plan->fresh()->suggestion_reminder_sent_for)->not->toBeNull();
});

test('a reminder is not sent when the content queue already has a pending todo', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13 06:00:00', 'Europe/London'));
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    WebsiteRepository::factory()->for($website)->create();
    ContentPlan::factory()->for($website)->for($admin, 'creator')->create(['weekday' => 5, 'hour' => 6]);
    ContentRequest::factory()->for($website)->create(['picked_up_at' => null]);
    SearchOpportunity::factory()->for($website)->create(['status' => SearchOpportunity::STATUS_OPEN]);

    $this->artisan('content:send-suggestion-reminders')->assertSuccessful();

    Mail::assertNothingOutgoing();
});

test('a signed email suggestion link adds the opportunity to the content queue', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    WebsiteRepository::factory()->for($website)->create();
    ContentPlan::factory()->for($website)->for($admin, 'creator')->create();
    $opportunity = SearchOpportunity::factory()->for($website)->create([
        'status' => SearchOpportunity::STATUS_OPEN,
        'query' => 'roof repairs doncaster',
    ]);
    $url = URL::temporarySignedRoute('admin.content-suggestions.store', now()->addHour(), [$website, 'type' => 'search', 'opportunity' => $opportunity->id]);

    $this->get($url)
        ->assertSuccessful()
        ->assertSee('Added to the content queue')
        ->assertSee($website->name);

    expect($opportunity->fresh()->status)->toBe(SearchOpportunity::STATUS_QUEUED)
        ->and($website->contentRequests()->sole()->instructions)->toContain('roof repairs doncaster')
        ->and($website->contentRequests()->sole()->created_by)->toBe($admin->id);
});

test('an unsigned suggestion link is rejected', function () {
    $website = Website::factory()->create();
    WebsiteRepository::factory()->for($website)->create();
    $opportunity = SearchOpportunity::factory()->for($website)->create();

    $this->get(route('admin.content-suggestions.store', [$website, 'type' => 'search', 'opportunity' => $opportunity->id]))->assertForbidden();

    expect($opportunity->fresh()->status)->toBe(SearchOpportunity::STATUS_OPEN);
});
