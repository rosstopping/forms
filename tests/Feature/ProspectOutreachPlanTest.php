<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Models\User;
use App\Services\ProspectOutreachContent;
use App\Services\ProspectOutreachPlan;
use App\Services\ProspectOutreachSender;
use App\Services\ProspectOutreachSequence;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Mail;

it('chooses the video follow up from the sent initial delivery rather than later draft edits', function (bool $includedVideo): void {
    Mail::fake();
    $this->freezeTime();
    $prospect = Prospect::factory()->create([
        'outreach_subject' => 'Your leaflet came through my door',
        'outreach_body' => 'My original introduction.',
        'approved_at' => now(),
        'showcase_video_url' => $includedVideo ? 'https://video.example/original' : null,
    ]);
    app(ProspectOutreachSender::class)->send($prospect);
    $prospect->update(['showcase_video_url' => $includedVideo ? null : 'https://video.example/added-later']);
    $this->travel(4)->days();

    $plan = app(ProspectOutreachPlan::class)->forProspect($prospect->fresh());
    app(ProspectOutreachSequence::class)->evaluate($prospect->fresh());
    $delivery = $prospect->outreachDeliveries()->where('message_type', ProspectOutreachMessageType::ColdFollowUp)->sole();
    $expected = $includedVideo
        ? "Hi there,\n\nJust checking you got my last email and had a chance to watch the quick video.\n\nLet me know what you think!\n\nCheers,\nRoss"
        : 'My original introduction.';
    expect($delivery->body)->toBe($expected)
        ->and($delivery->subject)->toBe('Your leaflet came through my door')
        ->and($plan['rows'][1]['body'])->toBe($expected)
        ->and($plan['video_in_initial'])->toBe($includedVideo);
})->with([true, false]);

it('does not infer a sent video from an unsent or legacy draft', function (): void {
    $prospect = Prospect::factory()->create(['showcase_video_url' => 'https://video.example/draft', 'sent_at' => now()]);
    expect(app(ProspectOutreachContent::class)->initialIncludedVideo($prospect, true))->toBeFalse();
});

it('shows future dates and completion checks without promising another email', function (): void {
    Mail::fake();
    $this->freezeTime();
    $prospect = Prospect::factory()->create(['approved_at' => now(), 'outreach_subject' => 'Hello', 'outreach_body' => 'My draft']);
    app(ProspectOutreachSender::class)->send($prospect);
    $plan = app(ProspectOutreachPlan::class)->forProspect($prospect->fresh());
    expect($plan['next'])->toBe('First follow-up · '.now()->addDays(4)->setTimezone('Europe/London')->format('j M Y, H:i').' UK')
        ->and($plan['rows'][2]['status'])->toBe('Conditional');

    $prospect->outreachState()->update(['sequence_step' => ProspectSequenceStep::FinalFollowUp, 'follow_up_attempts' => 2, 'next_action_at' => now()->addDays(6)]);
    expect(app(ProspectOutreachPlan::class)->forProspect($prospect->fresh())['next'])->toBe('No automatic email planned');
});

it('does not promise a follow up for paused stopped suppressed or unapproved prospects', function (string $condition): void {
    Mail::fake();
    $prospect = Prospect::factory()->create(['approved_at' => now(), 'outreach_subject' => 'Hello', 'outreach_body' => 'My draft']);
    app(ProspectOutreachSender::class)->send($prospect);
    match ($condition) {
        'paused' => $prospect->outreachState()->update(['automation_status' => ProspectAutomationStatus::Paused]),
        'replied' => $prospect->outreachState()->update(['lifecycle_state' => ProspectLifecycleState::Replied]),
        'suppressed' => $prospect->update(['suppressed_at' => now()]),
        'unapproved' => $prospect->update(['approved_at' => null]),
        'engaged' => $prospect->outreachState()->update(['engagement_score' => 5]),
        'disabled' => config()->set('outreach.automatic_follow_ups_enabled', false),
    };
    $plan = app(ProspectOutreachPlan::class)->forProspect($prospect->fresh());
    expect($plan['next'])->toBe('No automatic email planned')
        ->and($plan['rows'][1]['status'])->toBe('On hold');
})->with(['paused', 'replied', 'suppressed', 'unapproved', 'engaged', 'disabled']);

it('separates the prospect workspace into focused sections', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['outreach_body' => 'My draft']);
    $this->actingAs($admin)->get(route('admin.prospects.show', $prospect))
        ->assertSuccessful()->assertSee('What sends &amp; when', false)->assertSee('Next email')
        ->assertSee('Include site audit')->assertDontSee('Delete this prospect?');
    foreach (['details' => 'Public contact details', 'activity' => 'Activity timeline', 'controls' => 'Outreach lifecycle'] as $section => $heading) {
        $this->get(route('admin.prospects.show', [$prospect, 'section' => $section]))
            ->assertSuccessful()->assertSee($heading)->assertDontSee('What sends &amp; when', false);
    }
});

it('shows a reserved video schedule while cold automation is paused and ends the plan after sending', function (): void {
    $prospect = Prospect::factory()->create(['approved_at' => now(), 'sent_at' => now()->subWeek()]);
    $prospect->outreachState()->update([
        'lifecycle_state' => ProspectLifecycleState::NeedsPersonalisedVideo,
        'sequence_step' => ProspectSequenceStep::AwaitingPersonalisedVideo,
        'automation_status' => ProspectAutomationStatus::Paused,
        'initial_email_sent_at' => now()->subWeek(),
    ]);
    $scheduled = now()->addDay();
    ProspectOutreachDelivery::factory()->for($prospect)->create(['recipient_email' => $prospect->email,
        'message_type' => ProspectOutreachMessageType::PersonalisedVideo,
        'status' => 'scheduled', 'subject' => 'Reserved video subject', 'body' => 'Reserved video message',
        'scheduled_at' => $scheduled,
    ]);
    $prospect->outreachState()->update(['personalised_video_draft' => ['subject' => 'Unscheduled edit', 'body' => 'Draft only']]);
    $plan = app(ProspectOutreachPlan::class)->forProspect($prospect->fresh());
    expect($plan['next'])->toBe('Personalised video · '.$scheduled->setTimezone('Europe/London')->format('j M Y, H:i').' UK')
        ->and($plan['rows'][3]['body'])->toBe('Reserved video message');

    $prospect->outreachDeliveries()->update(['status' => 'sent', 'sent_at' => now(), 'scheduled_at' => null]);
    $prospect->outreachState()->update([
        'video_sent_at' => now(), 'sequence_step' => ProspectSequenceStep::PersonalisedVideo,
        'automation_status' => ProspectAutomationStatus::Active, 'next_action_at' => null,
    ]);
    expect(app(ProspectOutreachPlan::class)->forProspect($prospect->fresh())['next'])->toBe('No automatic email planned');
});

it('renders usable independent forms and a selected navigation state in each section', function (string $section): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['approved_at' => now(), 'outreach_subject' => 'Hello', 'outreach_body' => 'My draft']);
    $response = $this->actingAs($admin)->get(route('admin.prospects.show', [$prospect, 'section' => $section]))->assertSuccessful();
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    expect($document->querySelector('nav[aria-label="Prospect sections"] a[aria-current="page"]')->getAttribute('href'))
        ->toBe(route('admin.prospects.show', [$prospect, 'section' => $section]));
    $ids = [];
    foreach ($document->querySelectorAll('main [id]') as $node) {
        $ids[] = $node->getAttribute('id');
    }
    expect(count($ids))->toBe(count(array_unique($ids)));
    foreach ($document->querySelectorAll('main form') as $form) {
        expect($form->getAttribute('action'))->not->toBeEmpty()
            ->and($form->querySelector('input[name="_token"]'))->not->toBeNull();
    }
})->with(['emails', 'details', 'activity', 'controls']);
