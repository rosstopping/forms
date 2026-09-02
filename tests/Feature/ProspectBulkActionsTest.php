<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Jobs\AnalyzeProspect;
use App\Jobs\SendScheduledProspectOutreach;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectLifecycleManager;
use App\Services\ProspectOutreachSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function approvedProspect(User $owner, array $attributes = []): Prospect
{
    return Prospect::factory()->for($owner, 'owner')->create(array_merge([
        'status' => 'approved',
        'analysis_status' => 'completed',
        'outreach_subject' => 'Quick one',
        'outreach_body' => 'Hi there.',
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ], $attributes));
}

it('shows the outreach bulk selection and its actions', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    Prospect::factory()->count(21)->for($admin, 'owner')->create();

    $this->actingAs($admin)->get(route('admin.prospects.index'))
        ->assertSuccessful()
        ->assertSee('data-bulk-prospects-form', false)
        ->assertSee('Select all 21 matching prospects')
        ->assertSeeInOrder([
            'Approve Draft', 'Research Again', 'Schedule Approved Email', 'Cancel Scheduled Send', 'Mark as Draft Again', 'Send Approved Email',
            'Manual control', 'Pause automation', 'Resume automation', 'Force Warm', 'Force Hot', 'Use automatic score', 'Stop outreach', 'Mark replied', 'Not interested', 'Customer', 'Pilot', 'Delete',
        ]);
});

it('applies manual lifecycle controls in bulk', function (string $action, ProspectLifecycleState $expectedLifecycleState, ProspectAutomationStatus $expectedAutomationStatus, string $expectedTemperature): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['lead_temperature' => 'cold']);

    if ($action === 'resume') {
        app(ProspectLifecycleManager::class)->pause($prospect, $admin);
    }

    if ($action === 'clear_temperature_override') {
        app(ProspectLifecycleManager::class)->forceTemperature($prospect, 'warm', $admin);
    }

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => $action,
        'selection_scope' => 'page',
        'prospect_ids' => [$prospect->id],
    ])->assertRedirect()->assertSessionHas('status', fn (string $status): bool => str_starts_with($status, '1 prospect'));

    $prospect->refresh();

    expect($prospect->outreachState->lifecycle_state)->toBe($expectedLifecycleState)
        ->and($prospect->outreachState->automation_status)->toBe($expectedAutomationStatus)
        ->and($prospect->lead_temperature)->toBe($expectedTemperature);
})->with([
    'pause automation' => ['pause', ProspectLifecycleState::New, ProspectAutomationStatus::Paused, 'cold'],
    'resume automation' => ['resume', ProspectLifecycleState::New, ProspectAutomationStatus::Active, 'cold'],
    'force warm' => ['force_warm', ProspectLifecycleState::Warm, ProspectAutomationStatus::Active, 'warm'],
    'force hot' => ['force_hot', ProspectLifecycleState::NeedsPersonalisedVideo, ProspectAutomationStatus::Paused, 'hot'],
    'automatic score' => ['clear_temperature_override', ProspectLifecycleState::Warm, ProspectAutomationStatus::Active, 'cold'],
    'stop outreach' => ['stop', ProspectLifecycleState::New, ProspectAutomationStatus::Stopped, 'cold'],
    'mark replied' => ['mark_replied', ProspectLifecycleState::Replied, ProspectAutomationStatus::Stopped, 'cold'],
    'mark not interested' => ['mark_not_interested', ProspectLifecycleState::NotInterested, ProspectAutomationStatus::Stopped, 'cold'],
    'mark customer' => ['mark_customer', ProspectLifecycleState::Customer, ProspectAutomationStatus::Stopped, 'cold'],
    'mark pilot' => ['mark_pilot', ProspectLifecycleState::Pilot, ProspectAutomationStatus::Stopped, 'cold'],
]);

it('skips an ineligible manual lifecycle control without failing the batch', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create();
    app(ProspectLifecycleManager::class)->transitionManually($prospect, ProspectLifecycleState::Customer, $admin);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'resume',
        'selection_scope' => 'page',
        'prospect_ids' => [$prospect->id],
    ])->assertRedirect()->assertSessionHas('status', '0 prospects automation resumed. 1 skipped because they were not eligible.');

    expect($prospect->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::Customer);
});

it('applies a manual lifecycle control to every matching search result', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $firstMatch = Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Matching First', 'lead_temperature' => 'cold']);
    $secondMatch = Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Matching Second', 'lead_temperature' => 'cold']);
    $unrelated = Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Unrelated', 'lead_temperature' => 'cold']);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'force_hot',
        'selection_scope' => 'all',
        'search' => 'Matching',
        'temperature' => 'cold',
    ])->assertRedirect()->assertSessionHas('status', '2 prospects forced hot.');

    expect($firstMatch->refresh()->lead_temperature)->toBe('hot')
        ->and($secondMatch->refresh()->lead_temperature)->toBe('hot')
        ->and($unrelated->refresh()->lead_temperature)->toBe('cold');
});

it('keeps all-matching actions on the recent replies tab scoped to replied prospects', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $reply = Prospect::factory()->for($admin, 'owner')->create();
    $other = Prospect::factory()->for($admin, 'owner')->create();
    app(ProspectLifecycleManager::class)->transitionManually($reply, ProspectLifecycleState::Replied, $admin);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'mark_customer',
        'selection_scope' => 'all',
        'lifecycle_state' => ProspectLifecycleState::Replied->value,
    ])->assertRedirect()->assertSessionHas('status', '1 prospect moved to customer status.');

    expect($reply->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::Customer)
        ->and($other->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::New);
});

it('approves eligible selected drafts and skips ineligible prospects', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $draft = Prospect::factory()->for($admin, 'owner')->create(['status' => 'drafted', 'outreach_subject' => 'Quick one', 'outreach_body' => 'Hi there.']);
    $missingDraft = Prospect::factory()->for($admin, 'owner')->create(['status' => 'new', 'outreach_subject' => null, 'outreach_body' => null]);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'approve', 'selection_scope' => 'page', 'prospect_ids' => [$draft->id, $missingDraft->id],
    ])->assertRedirect()->assertSessionHas('status', '1 prospect approved. 1 skipped because they were not eligible.');

    expect($draft->refresh()->status)->toBe('approved')
        ->and($draft->approved_at)->not->toBeNull()
        ->and($missingDraft->refresh()->approved_at)->toBeNull();
});

it('researches all matching prospects and clears an existing schedule', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $matching = Prospect::factory()->for($admin, 'owner')->create([
        'business_name' => 'Matching Roofer', 'status' => 'drafted', 'analysis_status' => 'completed', 'scheduled_send_at' => now()->addDay(),
    ]);
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Different Plumber', 'status' => 'drafted', 'analysis_status' => 'completed']);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'research_again', 'selection_scope' => 'all', 'search' => 'Roofer', 'status' => 'drafted',
    ])->assertRedirect()->assertSessionHas('status', '1 prospect queued for research.');

    expect($matching->refresh()->analysis_status)->toBe('pending')->and($matching->scheduled_send_at)->toBeNull();
    Queue::assertPushed(AnalyzeProspect::class, fn (AnalyzeProspect $job): bool => $job->prospect->is($matching));
    Queue::assertPushed(AnalyzeProspect::class, 1);
});

it('applies bulk actions to all prospects matching comma-separated search terms', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $firstMatch = Prospect::factory()->for($admin, 'owner')->create(['email' => 'first@example.com', 'analysis_status' => 'completed']);
    $secondMatch = Prospect::factory()->for($admin, 'owner')->create(['email' => 'second@example.com', 'analysis_status' => 'completed']);
    $unrelated = Prospect::factory()->for($admin, 'owner')->create(['email' => 'other@example.com', 'analysis_status' => 'completed']);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'research_again',
        'selection_scope' => 'all',
        'search' => 'first@example.com, second@example.com, first@example.com',
    ])->assertRedirect()->assertSessionHas('status', '2 prospects queued for research.');

    expect($firstMatch->refresh()->analysis_status)->toBe('pending')
        ->and($secondMatch->refresh()->analysis_status)->toBe('pending')
        ->and($unrelated->refresh()->analysis_status)->toBe('completed');
    Queue::assertPushed(AnalyzeProspect::class, 2);
});

it('applies a bulk action to a long pasted list of escaped email addresses without touching unrelated prospects', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $firstMatch = Prospect::factory()->for($admin, 'owner')->create(['email' => 'info@optimakitchens.co.uk']);
    $secondMatch = Prospect::factory()->for($admin, 'owner')->create(['email' => 'enquiries@beesureroofing.co.uk']);
    $thirdMatch = Prospect::factory()->for($admin, 'owner')->create(['email' => 'dd0a55ccb8124b9c9d938e3acf41f8aa@sentry.wixpress.com']);
    $unrelated = Prospect::factory()->for($admin, 'owner')->create(['email' => 'keep@example.com']);
    $search = collect([
        'info\@optimakitchens.co.uk',
        'not-provided\@modal.form',
        'enquiries\@beesureroofing.co.uk',
        'dd0a55ccb8124b9c9d938e3acf41f8aa\@sentry.wixpress.com',
        ...collect(range(1, 20))->map(fn (int $number): string => "non-matching-address-{$number}\@example.test"),
    ])->join(', ');

    expect(mb_strlen($search))->toBeGreaterThan(255);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'delete', 'selection_scope' => 'all', 'search' => $search,
    ])->assertRedirect()->assertSessionHas('status', '3 prospects deleted.');

    $this->assertModelMissing($firstMatch);
    $this->assertModelMissing($secondMatch);
    $this->assertModelMissing($thirdMatch);
    $this->assertModelExists($unrelated);
});

it('rejects an excessively long bulk search and changes nothing', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create();

    $response = $this->actingAs($admin)
        ->from(route('admin.prospects.index'))
        ->post(route('admin.prospects.bulk'), [
            'action' => 'delete', 'selection_scope' => 'all', 'search' => str_repeat('a', 15001),
        ]);

    $response->assertRedirect(route('admin.prospects.index'))->assertSessionHasErrors('search');
    $this->assertModelExists($prospect);
});

it('applies bulk actions to all prospects matching the missing email filter', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $missingEmail = Prospect::factory()->for($admin, 'owner')->create(['email' => null, 'analysis_status' => 'completed']);
    $hasEmail = Prospect::factory()->for($admin, 'owner')->create(['email' => 'hello@example.com', 'analysis_status' => 'completed']);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'research_again', 'selection_scope' => 'all', 'email_status' => 'missing',
    ])->assertRedirect()->assertSessionHas('status', '1 prospect queued for research.');

    expect($missingEmail->refresh()->analysis_status)->toBe('pending')
        ->and($hasEmail->refresh()->analysis_status)->toBe('completed');
    Queue::assertPushed(AnalyzeProspect::class, 1);
});

it('schedules eligible approved emails in UK time and displays the schedule', function (): void {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-08-19 09:00:00 UTC');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = approvedProspect($admin);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'schedule_approved_email', 'selection_scope' => 'page', 'prospect_ids' => [$prospect->id], 'scheduled_send_at' => '2026-08-20T10:30',
    ])->assertRedirect()->assertSessionHas('status', '1 prospect scheduled.');

    $expected = CarbonImmutable::parse('2026-08-20 10:30', 'Europe/London')->utc();
    expect($prospect->refresh()->scheduled_send_at->equalTo($expected))->toBeTrue();
    Queue::assertPushed(SendScheduledProspectOutreach::class, fn (SendScheduledProspectOutreach $job): bool => $job->prospectId === $prospect->id && $job->scheduledFor->equalTo($expected));
    $this->get(route('admin.prospects.index'))->assertSuccessful()->assertSee('Scheduled 20 Aug, 10:30');
});

it('cancels scheduled emails in bulk without removing their approval', function (): void {
    Mail::fake();
    CarbonImmutable::setTestNow('2026-08-19 09:00:00 UTC');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $scheduledFor = CarbonImmutable::parse('2026-08-20 10:30', 'Europe/London')->utc();
    $scheduled = approvedProspect($admin, ['scheduled_send_at' => $scheduledFor]);
    $unscheduled = approvedProspect($admin);
    app(ProspectLifecycleManager::class)->markScheduled($scheduled);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'cancel_scheduled_email', 'selection_scope' => 'page', 'prospect_ids' => [$scheduled->id, $unscheduled->id],
    ])->assertRedirect()->assertSessionHas('status', '1 prospect schedule cancelled. 1 skipped because they were not eligible.');

    $scheduled->refresh();
    expect($scheduled->status)->toBe('approved')
        ->and($scheduled->approved_at)->not->toBeNull()
        ->and($scheduled->approved_by)->toBe($admin->id)
        ->and($scheduled->scheduled_send_at)->toBeNull()
        ->and($scheduled->outreachState->lifecycle_state->value)->toBe('approved')
        ->and($scheduled->outreachState->next_action_at)->toBeNull()
        ->and($unscheduled->refresh()->status)->toBe('approved');

    (new SendScheduledProspectOutreach($scheduled->id, $scheduledFor))->handle(app(ProspectOutreachSender::class));

    expect($scheduled->refresh()->sent_at)->toBeNull();
    Mail::assertNothingSent();
});

it('marks approved emails as drafts in bulk and cancels any scheduled send', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $scheduledFor = CarbonImmutable::now()->addDay();
    $scheduled = approvedProspect($admin, ['scheduled_send_at' => $scheduledFor]);
    $alreadyDraft = Prospect::factory()->for($admin, 'owner')->create(['status' => 'drafted']);
    app(ProspectLifecycleManager::class)->markScheduled($scheduled);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'mark_as_draft', 'selection_scope' => 'page', 'prospect_ids' => [$scheduled->id, $alreadyDraft->id],
    ])->assertRedirect()->assertSessionHas('status', '1 prospect returned to draft. 1 skipped because they were not eligible.');

    $scheduled->refresh();
    expect($scheduled->status)->toBe('drafted')
        ->and($scheduled->approved_at)->toBeNull()
        ->and($scheduled->approved_by)->toBeNull()
        ->and($scheduled->scheduled_send_at)->toBeNull()
        ->and($scheduled->outreachState->lifecycle_state->value)->toBe('qualified')
        ->and($scheduled->outreachState->next_action_at)->toBeNull();

    (new SendScheduledProspectOutreach($scheduled->id, $scheduledFor))->handle(app(ProspectOutreachSender::class));

    expect($scheduled->refresh()->sent_at)->toBeNull();
    Mail::assertNothingSent();
});

it('marks every matching approved prospect as a draft without skipping records as their status changes', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $scheduledFor = CarbonImmutable::now()->addDay();
    $first = approvedProspect($admin, ['business_name' => 'Matching First', 'scheduled_send_at' => $scheduledFor]);
    $second = approvedProspect($admin, ['business_name' => 'Matching Second', 'scheduled_send_at' => $scheduledFor]);
    $unrelated = approvedProspect($admin, ['business_name' => 'Unrelated']);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'mark_as_draft',
        'selection_scope' => 'all',
        'search' => 'Matching',
        'status' => 'approved',
    ])->assertRedirect()->assertSessionHas('status', '2 prospects returned to draft.');

    expect($first->refresh()->status)->toBe('drafted')
        ->and($first->scheduled_send_at)->toBeNull()
        ->and($first->approved_at)->toBeNull()
        ->and($second->refresh()->status)->toBe('drafted')
        ->and($second->scheduled_send_at)->toBeNull()
        ->and($second->approved_at)->toBeNull()
        ->and($unrelated->refresh()->status)->toBe('approved');
});

it('repairs an unsent prospect with stale approval or scheduling data when returning it to draft', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'status' => 'drafted',
        'approved_at' => now(),
        'approved_by' => $admin->id,
        'scheduled_send_at' => now()->addDay(),
    ]);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'mark_as_draft', 'selection_scope' => 'page', 'prospect_ids' => [$prospect->id],
    ])->assertRedirect()->assertSessionHas('status', '1 prospect returned to draft.');

    expect($prospect->refresh()->status)->toBe('drafted')
        ->and($prospect->approved_at)->toBeNull()
        ->and($prospect->approved_by)->toBeNull()
        ->and($prospect->scheduled_send_at)->toBeNull();
});

it('schedules from a prospect page and sends the approved email when the job runs', function (): void {
    Queue::fake();
    Mail::fake();
    CarbonImmutable::setTestNow('2026-08-19 09:00:00 UTC');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = approvedProspect($admin);

    $this->actingAs($admin)->post(route('admin.prospects.schedule', $prospect), ['scheduled_send_at' => '2026-08-20T11:00'])->assertRedirect();
    $job = new SendScheduledProspectOutreach($prospect->id, CarbonImmutable::parse('2026-08-20 11:00', 'Europe/London')->utc());
    $job->handle(app(ProspectOutreachSender::class));

    expect($prospect->refresh()->status)->toBe('contacted')
        ->and($prospect->sent_at)->not->toBeNull()
        ->and($prospect->scheduled_send_at)->toBeNull();
    Mail::assertSent(ProspectOutreach::class, fn (ProspectOutreach $mail): bool => $mail->hasTo($prospect->email));
});

it('sends eligible approved emails immediately in bulk without sending ineligible drafts', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $approved = approvedProspect($admin, ['scheduled_send_at' => now()->addDay()]);
    $draft = Prospect::factory()->for($admin, 'owner')->create(['status' => 'drafted', 'outreach_subject' => 'Quick one', 'outreach_body' => 'Hi there.']);

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'send_approved_email', 'selection_scope' => 'page', 'prospect_ids' => [$approved->id, $draft->id],
    ])->assertRedirect()->assertSessionHas('status', '1 prospect sent. 1 skipped because they were not eligible.');

    expect($approved->refresh()->sent_at)->not->toBeNull()
        ->and($approved->scheduled_send_at)->toBeNull()
        ->and($draft->refresh()->sent_at)->toBeNull();
    Mail::assertSent(ProspectOutreach::class, 1);
});

it('deletes selected prospects in bulk', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospects = Prospect::factory()->count(2)->for($admin, 'owner')->create();

    $this->actingAs($admin)->post(route('admin.prospects.bulk'), [
        'action' => 'delete', 'selection_scope' => 'page', 'prospect_ids' => $prospects->modelKeys(),
    ])->assertRedirect()->assertSessionHas('status', '2 prospects deleted.');

    expect(Prospect::query()->count())->toBe(0);
});

it('rejects bulk outreach actions from non administrators', function (): void {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->for($user, 'owner')->create();

    $this->actingAs($user)->post(route('admin.prospects.bulk'), [
        'action' => 'delete', 'selection_scope' => 'page', 'prospect_ids' => [$prospect->id],
    ])->assertForbidden();

    $this->assertModelExists($prospect);
});
