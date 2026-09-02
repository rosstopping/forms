<?php

use App\Enums\ProspectEngagementEventType;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Models\Prospect;
use App\Models\ProspectEngagementEvent;
use App\Models\ProspectOutreachDelivery;
use App\Models\User;

it('keeps daily priorities on the dashboard and moves lead queues into temperature tabs', function (): void {
    $this->travelTo('2026-08-28 10:35:00 Europe/London');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warm = Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Warm Roofing', 'lead_temperature' => 'warm']);
    $warm->outreachState()->update([
        'lifecycle_state' => ProspectLifecycleState::Warm,
        'engagement_score' => 6,
        'initial_email_sent_at' => now()->subMinutes(5),
        'last_engagement_at' => now(),
    ]);
    ProspectEngagementEvent::factory()->for($warm)->create(['event_type' => ProspectEngagementEventType::AuditClicked, 'score_delta' => 5, 'occurred_at' => now()]);

    $replied = Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Reply Plumbing', 'status' => 'replied', 'replied_at' => now()]);
    $replied->outreachState()->update(['lifecycle_state' => ProspectLifecycleState::Replied]);
    ProspectEngagementEvent::factory()->for($warm)->create(['event_type' => ProspectEngagementEventType::BookingPageClicked, 'score_delta' => 20, 'occurred_at' => now()]);
    ProspectOutreachDelivery::factory()->for($warm)->create(['message_type' => ProspectOutreachMessageType::ColdFollowUp, 'status' => 'sent', 'sent_at' => now()]);
    $warm->recordActivity('outreach_exhausted', 'Automated cold outreach completed with no meaningful engagement.');

    $dashboardResponse = $this->actingAs($admin)->get(route('admin.prospects.index'))
        ->assertSuccessful()
        ->assertSee('Today’s priorities')
        ->assertSee('Hot Leads')
        ->assertSee('Warm Leads')
        ->assertDontSee('Warm Roofing')
        ->assertSee('Recent Replies')
        ->assertSee('Reply Plumbing')
        ->assertSee('automatically followed up today')
        ->assertSee('moved to nurture today');

    $dashboardResponse->assertSee(route('admin.prospects.index', ['tab' => 'warm']), false);

    $this->actingAs($admin)->get(route('admin.prospects.index', ['tab' => 'warm']))
        ->assertSuccessful()
        ->assertDontSee('Today’s priorities')
        ->assertSee('Warm Leads')
        ->assertSee('Warm Roofing')
        ->assertSee('Email sent')
        ->assertSee('28 Aug 2026, 10:30')
        ->assertSee('28 Aug 2026, 10:35')
        ->assertDontSee('Reply Plumbing');
});

it('shows hot leads in their own tab', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $hot = Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Hot Electric', 'lead_temperature' => 'hot']);
    $hot->outreachState()->update([
        'lifecycle_state' => ProspectLifecycleState::NeedsPersonalisedVideo,
        'engagement_score' => 25,
    ]);

    $this->actingAs($admin)->get(route('admin.prospects.index'))
        ->assertSuccessful()
        ->assertDontSee('Needs Personalised Video')
        ->assertDontSee('Hot Electric');

    $this->actingAs($admin)->get(route('admin.prospects.index', ['tab' => 'hot']))
        ->assertSuccessful()
        ->assertDontSee('Today’s priorities')
        ->assertSee('Needs Personalised Video')
        ->assertSee('Hot Electric');
});

it('shows a detailed queryable activity timeline', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create();
    $prospect->recordActivity('engagement_score_changed', 'Engagement score changed from 1 to 6.');

    $this->actingAs($admin)->get(route('admin.prospects.show', $prospect))
        ->assertSuccessful()
        ->assertSee('Activity timeline')
        ->assertSee('Engagement Score Changed')
        ->assertSee('Engagement score changed from 1 to 6.')
        ->assertSee($prospect->activities()->latest()->first()->created_at->format('j M Y, H:i'));
});

it('explains lifecycle controls and places website opportunities last in a collapsed panel', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create();

    $this->actingAs($admin)->get(route('admin.prospects.show', $prospect))
        ->assertSuccessful()
        ->assertSee('Temporarily prevents scheduled sequence actions')
        ->assertSee('Restarts due sequence actions')
        ->assertSee('Moves the prospect into the personalised-video queue')
        ->assertSee('Records a reply, cancels future automated messages')
        ->assertSee('It will not automatically restart outreach')
        ->assertSee('Add or subtract points to correct the automated score')
        ->assertSee('Returns the score to zero')
        ->assertSee('<details class="group', false)
        ->assertSeeInOrder(['Activity timeline', 'Website opportunities']);
});
