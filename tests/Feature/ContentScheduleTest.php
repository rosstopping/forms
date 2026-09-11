<?php

use App\Jobs\StartContentGeneration;
use App\Jobs\SyncContentGeneration;
use App\Mail\ContentSuggestionReminder;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\GithubUserAuthorization;
use App\Models\SearchOpportunity;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\ContentSchedule;
use App\Services\ContentWorkSelector;
use App\Services\CopilotAgentClient;
use App\Services\DashboardSchedule;
use App\Services\SearchConsoleClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-07 08:00', 'Europe/London'));
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($this->admin)->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    $this->repository = WebsiteRepository::factory()->for($this->website)->create();
    $this->plan = ContentPlan::factory()->for($this->website)->for($this->admin, 'creator')->create(['weekday' => 1, 'hour' => 8]);
    $this->schedule = app(ContentSchedule::class);
    $this->payload = ['enabled' => true, 'weekday' => 1, 'additional_weekdays' => [3, 5], 'hour' => 8, 'timezone' => 'Europe/London'];
});

test('website managers can choose three days without changing automation credentials', function () {
    $manager = User::factory()->create(['membership_tier' => 'essential']);
    $this->website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $this->actingAs($manager)->put(route('admin.content-plans.update', $this->website), $this->payload)
        ->assertSessionDoesntHaveErrors();
    expect($this->plan->fresh()->additional_weekdays)->toBe([3, 5])
        ->and($this->plan->fresh()->created_by)->toBe($this->admin->id);
    $this->actingAs($manager)->get(route('admin.websites.section', [$this->website, 'section' => 'content']))
        ->assertSuccessful()->assertSee('Extra days')->assertSee('Next scheduled run:')->assertDontSee('Generate now');
});

test('viewers and unrelated users cannot change schedules', function (bool $viewer) {
    $user = User::factory()->create();
    if ($viewer) {
        $this->website->members()->attach($user, ['role' => Website::MEMBER_ROLE_VIEWER]);
    }
    $this->actingAs($user)->put(route('admin.content-plans.update', $this->website), $this->payload)->assertForbidden();
    expect($this->plan->fresh()->additional_weekdays)->toBeNull();
})->with([true, false]);

test('extra weekdays reject duplicates primary day invalid days and more than two', function (array $days) {
    $this->actingAs($this->owner)->put(route('admin.content-plans.update', $this->website), [...$this->payload, 'additional_weekdays' => $days])
        ->assertSessionHasErrors();
    expect($this->plan->fresh()->additional_weekdays)->toBeNull();
})->with([[[3, 3]], [[1]], [[8]], [[2, 3, 4]]]);

test('growth schedules retain paused extra days and cannot add any even through staff', function () {
    $this->plan->update(['additional_weekdays' => [3, 5]]);
    $this->owner->update(['membership_tier' => 'growth']);
    $this->actingAs($this->admin)->put(route('admin.content-plans.update', $this->website), $this->payload)
        ->assertSessionHasErrors('additional_weekdays');
    unset($this->payload['additional_weekdays']);
    $this->actingAs($this->owner)->put(route('admin.content-plans.update', $this->website), $this->payload)
        ->assertSessionDoesntHaveErrors();
    expect($this->plan->fresh()->additional_weekdays)->toBe([3, 5])
        ->and($this->schedule->weekdays($this->plan->fresh()))->toBe([1]);
    $this->owner->update(['membership_tier' => 'complete']);
    expect($this->schedule->weekdays($this->plan->fresh()))->toBe([1, 3, 5]);
});

test('existing complete schedules stay weekly and extra days can be cleared', function () {
    expect($this->schedule->weekdays($this->plan))->toBe([1]);
    $this->plan->update(['additional_weekdays' => [3, 5]]);
    unset($this->payload['additional_weekdays']);
    $this->actingAs($this->owner)->put(route('admin.content-plans.update', $this->website), $this->payload)->assertSessionDoesntHaveErrors();
    expect($this->plan->fresh()->additional_weekdays)->toBe([]);
});

test('managers cannot enable automation using their own github credentials', function () {
    GithubUserAuthorization::factory()->for($this->owner)->create();
    $this->plan->update(['created_by' => null, 'enabled' => false]);
    $this->actingAs($this->owner)->put(route('admin.content-plans.update', $this->website), $this->payload)->assertSessionHasErrors('enabled');
    expect($this->plan->fresh()->enabled)->toBeFalse()->and($this->plan->fresh()->created_by)->toBeNull();
});

test('complete dispatches selected days once each and respects weekly limits after edits', function () {
    Queue::fake();
    $this->plan->update(['additional_weekdays' => [3, 5]]);
    foreach ([7, 9, 11] as $day) {
        $this->travelTo(CarbonImmutable::parse("2026-09-{$day} 08:00", 'Europe/London'));
        $this->artisan('content:dispatch')->assertSuccessful();
        $this->artisan('content:dispatch')->assertSuccessful();
        $this->plan->generations()->update(['status' => ContentGeneration::STATUS_COMPLETED]);
    }
    $this->plan->update(['weekday' => 6]);
    $this->travelTo(CarbonImmutable::parse('2026-09-12 08:00', 'Europe/London'));
    $this->artisan('content:dispatch')->assertSuccessful();
    Queue::assertPushed(StartContentGeneration::class, 3);
    expect($this->plan->generations()->count())->toBe(3)
        ->and($this->schedule->nextRunAt($this->plan->fresh())->setTimezone('Europe/London')->toDateString())->toBe('2026-09-16');
});

test('growth cannot schedule again by changing its weekly day', function () {
    Queue::fake();
    $this->owner->update(['membership_tier' => 'growth']);
    $this->artisan('content:dispatch')->assertSuccessful();
    $this->plan->generations()->update(['status' => ContentGeneration::STATUS_COMPLETED]);
    $this->plan->update(['weekday' => 2]);
    $this->travelTo(CarbonImmutable::parse('2026-09-08 08:00', 'Europe/London'));
    $this->artisan('content:dispatch')->assertSuccessful();
    Queue::assertPushed(StartContentGeneration::class, 1);
});

test('inactive and lower tier sites cannot dispatch or send reminders', function (array $attributes) {
    Queue::fake();
    Mail::fake();
    $this->owner->update($attributes);
    $this->artisan('content:dispatch')->assertSuccessful();
    expect($this->schedule->nextRunAt($this->plan->fresh()))->toBeNull();
    $this->travelBack();
    Queue::assertNothingPushed();
})->with([
    [['membership_tier' => 'essential']],
    [['membership_status' => 'canceled']],
    [['membership_status' => 'trialing', 'membership_current_period_end' => '2026-09-06']],
]);

test('consecutive days each receive one day-before reminder', function () {
    Mail::fake();
    $this->plan->update(['weekday' => 2, 'additional_weekdays' => [3, 4]]);
    SearchOpportunity::factory()->for($this->website)->create();
    foreach ([7, 8, 9] as $day) {
        $this->travelTo(CarbonImmutable::parse("2026-09-{$day} 08:00", 'Europe/London'));
        $this->artisan('content:send-suggestion-reminders')->assertSuccessful();
        $this->artisan('content:send-suggestion-reminders')->assertSuccessful();
    }
    Mail::assertQueued(ContentSuggestionReminder::class, 3);
});

test('paused extra days do not receive reminders', function () {
    Mail::fake();
    $this->plan->update(['additional_weekdays' => [2, 3]]);
    $this->owner->update(['membership_tier' => 'growth']);
    SearchOpportunity::factory()->for($this->website)->create();
    $this->artisan('content:send-suggestion-reminders')->assertSuccessful();
    Mail::assertNothingOutgoing();
});

test('daylight saving preserves local reminder time and dispatches repeated hours only once', function () {
    Queue::fake();
    $this->plan->update(['weekday' => 0, 'hour' => 1]);
    $this->travelTo(CarbonImmutable::parse('2026-10-24 01:00', 'Europe/London'));
    expect($this->schedule->reminderRunAt($this->plan)->format('Y-m-d H:i'))->toBe('2026-10-25 01:00');
    foreach (['2026-10-25 00:00 UTC', '2026-10-25 01:00 UTC'] as $time) {
        $this->travelTo(CarbonImmutable::parse($time));
        $this->artisan('content:dispatch')->assertSuccessful();
        $this->plan->generations()->update(['status' => ContentGeneration::STATUS_COMPLETED]);
    }
    Queue::assertPushed(StartContentGeneration::class, 1);
});

test('missing spring clock hour is skipped by the shared schedule', function () {
    $this->plan->update(['weekday' => 0, 'hour' => 1]);
    $this->travelTo(CarbonImmutable::parse('2026-03-28 01:00', 'Europe/London'));
    expect($this->schedule->reminderRunAt($this->plan))->toBeNull()
        ->and($this->schedule->nextRunAt($this->plan)->setTimezone('Europe/London')->format('Y-m-d H:i'))->toBe('2026-04-05 01:00');
});

test('dashboard uses the same next selected day', function () {
    $this->plan->update(['additional_weekdays' => [3, 5]]);
    $items = app(DashboardSchedule::class)->forWebsites(collect([$this->website->fresh()]));
    expect($items->sole()['next_run_at']->equalTo($this->schedule->nextRunAt($this->plan->fresh())))->toBeTrue();
});

test('scheduled jobs skip empty work without calling external services', function () {
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'scheduled']);
    $search = $this->mock(SearchConsoleClient::class)->shouldNotReceive('performance')->getMock();
    $copilot = $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask')->getMock();
    (new StartContentGeneration($generation))->handle($search, app(ContentGenerationPromptGenerator::class), $copilot);
    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_SKIPPED)
        ->and($generation->fresh()->skip_reason)->toContain('No eligible work');
    $this->actingAs($this->owner)->get(route('admin.websites.section', [$this->website, 'section' => 'content']))->assertSee('No eligible work');
});

test('queued jobs recheck downgrade and disabled scheduling', function (array $changes) {
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'scheduled']);
    SeoTargetKeyword::factory()->for($this->website)->create();
    if (isset($changes['enabled'])) {
        $this->plan->update($changes);
    } else {
        $this->owner->update($changes);
    }
    $copilot = $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask')->getMock();
    (new StartContentGeneration($generation))->handle(app(SearchConsoleClient::class), app(ContentGenerationPromptGenerator::class), $copilot);
    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_SKIPPED);
})->with([[['enabled' => false]], [['membership_tier' => 'essential']]]);

test('automatic targets respect cooldown but explicit requests can revise sooner', function () {
    $target = SeoTargetKeyword::factory()->for($this->website)->create(['last_selected_at' => now()->subDays(13)]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create();
    $selector = app(ContentWorkSelector::class);
    expect($selector->select($generation)['target'])->toBeNull();
    $target->update(['last_selected_at' => now()->subDays(14)]);
    expect($selector->select($generation)['target']->id)->toBe($target->id);
    $request = ContentRequest::factory()->for($this->website)->create();
    $target->update(['last_selected_at' => now()]);
    expect($selector->select($generation)['requests']->sole()->id)->toBe($request->id);
});

test('open reviews block the same target and known page but allow unrelated work', function () {
    $target = SeoTargetKeyword::factory()->for($this->website)->create(['priority' => 'high']);
    $samePage = SeoTargetKeyword::factory()->for($this->website)->create();
    $other = SeoTargetKeyword::factory()->for($this->website)->create();
    SeoTargetKeywordRanking::factory()->create(['seo_target_keyword_id' => $samePage->id, 'status' => 'ranked', 'ranking_url' => 'https://example.com/service/']);
    ContentGeneration::factory()->for($this->plan, 'plan')->create([
        'scheduled_for' => now()->subDays(20), 'status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN,
        'seo_target_keyword_id' => $target->id,
        'target_keyword_context' => [['id' => $target->id, 'term' => $target->term, 'ranking_url' => 'https://example.com/service']],
    ]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create();
    $blocked = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Improve https://example.com/service/']);
    $work = app(ContentWorkSelector::class)->select($generation);
    expect($work['target']->id)->toBe($other->id)->and($work['requests'])->toBeEmpty()
        ->and($blocked->fresh()->picked_up_at)->toBeNull();
});

test('manual generation cannot overlap an active scheduled generation', function () {
    Queue::fake();
    ContentGeneration::factory()->for($this->plan, 'plan')->create(['scheduled_for' => now()->subDay(), 'status' => ContentGeneration::STATUS_RUNNING]);
    $this->actingAs($this->admin)->post(route('admin.content-generations.store', $this->website))->assertSessionHas('status', 'A content generation already exists today or is still running.');
    Queue::assertNothingPushed();
});

test('marketing explains the distinct content allowances and review requirement', function () {
    $this->get(route('marketing.feature', 'content-planning-and-generation'))
        ->assertSuccessful()->assertSee('Complete includes up to three')->assertSee('prepared for review before publishing');
    expect(config('memberships.plans.complete.features'))->toContain('Up to three scheduled content improvements per week, prepared for review');
});

test('accepted scheduled work updates rotation and request pickup exactly once', function () {
    Queue::fake([SyncContentGeneration::class]);
    $request = ContentRequest::factory()->for($this->website)->create();
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'scheduled']);
    $copilot = $this->mock(CopilotAgentClient::class)->shouldReceive('startTask')->once()
        ->andReturn(['id' => '00000000-0000-4000-8000-000000000001'])->getMock();
    $job = new StartContentGeneration($generation);
    $job->handle(app(SearchConsoleClient::class), app(ContentGenerationPromptGenerator::class), $copilot);
    $job->handle(app(SearchConsoleClient::class), app(ContentGenerationPromptGenerator::class), $copilot);
    expect($request->fresh()->content_generation_id)->toBe($generation->id)
        ->and($generation->fresh()->status)->toBe(ContentGeneration::STATUS_RUNNING);
    Queue::assertPushed(SyncContentGeneration::class, 1);
});

test('scheduled automatic selection rotates after provider acceptance', function () {
    Queue::fake([SyncContentGeneration::class]);
    $target = SeoTargetKeyword::factory()->for($this->website)->create(['last_selected_at' => null]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'scheduled']);
    $copilot = $this->mock(CopilotAgentClient::class)->shouldReceive('startTask')->once()
        ->andReturn(['id' => '00000000-0000-4000-8000-000000000001'])->getMock();
    (new StartContentGeneration($generation))->handle(app(SearchConsoleClient::class), app(ContentGenerationPromptGenerator::class), $copilot);
    expect($target->fresh()->last_selected_at)->not->toBeNull()
        ->and($generation->fresh()->seo_target_keyword_id)->toBe($target->id);
});

test('known pages rest after accepted changes even when a different keyword targets them', function () {
    $target = SeoTargetKeyword::factory()->for($this->website)->create();
    SeoTargetKeywordRanking::factory()->create(['seo_target_keyword_id' => $target->id, 'ranking_url' => 'https://example.com/service']);
    ContentGeneration::factory()->for($this->plan, 'plan')->create([
        'scheduled_for' => now()->subDays(3), 'status' => ContentGeneration::STATUS_COMPLETED,
        'started_at' => now()->subDays(3), 'copilot_task_id' => '00000000-0000-4000-8000-000000000001',
        'seo_target_keyword_id' => $target->id,
        'target_keyword_context' => [['id' => $target->id, 'term' => $target->term, 'ranking_url' => 'https://example.com/service/']],
    ]);
    $other = SeoTargetKeyword::factory()->for($this->website)->create();
    SeoTargetKeywordRanking::factory()->create(['seo_target_keyword_id' => $other->id, 'ranking_url' => 'https://example.com/service']);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create();
    expect(app(ContentWorkSelector::class)->select($generation)['target'])->toBeNull();
    $request = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Urgent correction to https://example.com/service']);
    expect(app(ContentWorkSelector::class)->select($generation)['requests']->sole()->id)->toBe($request->id);
});

test('manual support runs are outside the weekly allowance but still limited to one local date', function () {
    Queue::fake();
    $this->owner->update(['membership_tier' => 'growth']);
    ContentGeneration::factory()->for($this->plan, 'plan')->create(['scheduled_for' => now()->subDay(), 'trigger' => 'manual', 'status' => ContentGeneration::STATUS_COMPLETED]);
    $this->actingAs($this->admin)->post(route('admin.content-generations.store', $this->website))->assertSessionHas('status', 'Content generation queued.');
    $this->plan->generations()->update(['status' => ContentGeneration::STATUS_COMPLETED]);
    $this->actingAs($this->admin)->post(route('admin.content-generations.store', $this->website));
    $this->artisan('content:dispatch')->assertSuccessful();
    Queue::assertPushed(StartContentGeneration::class, 1);
    expect($this->schedule->hasCapacity($this->plan->fresh(), CarbonImmutable::now()))->toBeTrue();
});

test('queued extra run is skipped after downgrade even if it is the new primary day', function () {
    $this->plan->update(['weekday' => 3, 'additional_weekdays' => [1, 5]]);
    ContentGeneration::factory()->for($this->plan, 'plan')->create(['scheduled_for' => '2026-09-07', 'trigger' => 'scheduled', 'status' => ContentGeneration::STATUS_COMPLETED]);
    $this->travelTo(CarbonImmutable::parse('2026-09-09 08:00', 'Europe/London'));
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'scheduled']);
    $this->owner->update(['membership_tier' => 'growth']);
    $copilot = $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask')->getMock();
    (new StartContentGeneration($generation))->handle(app(SearchConsoleClient::class), app(ContentGenerationPromptGenerator::class), $copilot);
    expect($generation->fresh()->skip_reason)->toBe('The weekly content allowance has been used.');
});

test('viewers see no schedule mutation controls', function () {
    $viewer = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($viewer)->get(route('admin.websites.section', [$this->website, 'section' => 'content']))
        ->assertSuccessful()->assertDontSee('Save content plan')->assertDontSee('Generate now');
});

test('non whole hour timezones dispatch at the chosen local time', function () {
    Queue::fake();
    $this->plan->update(['timezone' => 'Asia/Kathmandu']);
    $this->travelTo(CarbonImmutable::parse('2026-09-07 02:15 UTC'));
    $this->artisan('content:dispatch')->assertSuccessful();
    Queue::assertPushed(StartContentGeneration::class, 1);
    expect($this->schedule->occursAt($this->plan->fresh(), CarbonImmutable::parse('2026-09-07 02:16 UTC')))->toBeFalse();
});
