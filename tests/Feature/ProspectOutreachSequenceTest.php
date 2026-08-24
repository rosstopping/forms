<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectOutreachStopReason;
use App\Enums\ProspectSequenceStep;
use App\Jobs\EvaluateProspectOutreach;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectOutreachSender;
use App\Services\ProspectOutreachSequence;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function coldSequenceProspect(array $attributes = []): Prospect
{
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(array_merge([
        'status' => 'contacted',
        'outreach_subject' => 'Website opportunities',
        'outreach_body' => 'Hi Alex, here is the website audit I mentioned.',
        'approved_at' => now()->subWeek(),
        'sent_at' => now()->subDays(4),
        'next_follow_up_at' => now(),
    ], $attributes));
    $prospect->outreachState()->update([
        'lifecycle_state' => ProspectLifecycleState::InitialEmailSent,
        'automation_status' => ProspectAutomationStatus::Active,
        'sequence_step' => ProspectSequenceStep::InitialEmail,
        'initial_email_sent_at' => $prospect->sent_at,
        'last_outreach_at' => $prospect->sent_at,
        'next_action_at' => now(),
    ]);

    return $prospect->refresh();
}

it('schedules the first cold evaluation using the configured delay', function (): void {
    Mail::fake();
    $this->freezeTime();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'status' => 'approved',
        'outreach_subject' => 'Website opportunities',
        'outreach_body' => 'Hi Alex, here is the website audit I mentioned.',
        'approved_at' => now(),
    ]);

    app(ProspectOutreachSender::class)->send($prospect);

    $state = $prospect->outreachState->fresh();
    $delivery = $prospect->outreachDeliveries()->sole();

    expect($state->sequence_step)->toBe(ProspectSequenceStep::InitialEmail)
        ->and($state->next_action_at->equalTo(now()->addDays(4)->startOfSecond()))->toBeTrue()
        ->and($delivery->message_type)->toBe(ProspectOutreachMessageType::Initial)
        ->and($delivery->subject)->toBe($prospect->outreach_subject)
        ->and($delivery->body)->toBe($prospect->outreach_body)
        ->and($delivery->status)->toBe('sent');
});

it('dispatches unique evaluation jobs only for due active prospects', function (): void {
    Queue::fake();
    $due = coldSequenceProspect();
    $future = coldSequenceProspect();
    $future->outreachState()->update(['next_action_at' => now()->addHour()]);
    $paused = coldSequenceProspect();
    $paused->outreachState()->update(['automation_status' => ProspectAutomationStatus::Paused]);

    $this->artisan('outreach:dispatch-due')->assertSuccessful();

    Queue::assertPushed(EvaluateProspectOutreach::class, fn (EvaluateProspectOutreach $job): bool => $job->prospectId === $due->id);
    Queue::assertPushed(EvaluateProspectOutreach::class, 1);
});

it('sends the cold and final follow ups then exhausts the sequence', function (): void {
    Mail::fake();
    $this->freezeTime();
    $prospect = coldSequenceProspect(['contact_name' => 'Alex']);
    $sequence = app(ProspectOutreachSequence::class);

    $sequence->evaluate($prospect);

    $state = $prospect->outreachState->fresh();
    $coldDelivery = $prospect->outreachDeliveries()->sole();
    expect($state->lifecycle_state)->toBe(ProspectLifecycleState::Cold)
        ->and($state->sequence_step)->toBe(ProspectSequenceStep::ColdFollowUp)
        ->and($state->follow_up_attempts)->toBe(1)
        ->and($state->next_action_at->equalTo(now()->addDays(6)->startOfSecond()))->toBeTrue()
        ->and($coldDelivery->message_type)->toBe(ProspectOutreachMessageType::ColdFollowUp)
        ->and($coldDelivery->body)->toBe($prospect->outreach_body);

    $this->travel(6)->days();
    $sequence->evaluate($prospect);

    $state = $prospect->outreachState->fresh();
    $finalDelivery = $prospect->outreachDeliveries()->where('message_type', ProspectOutreachMessageType::FinalFollowUp)->sole();
    expect($state->sequence_step)->toBe(ProspectSequenceStep::FinalFollowUp)
        ->and($state->follow_up_attempts)->toBe(2)
        ->and($finalDelivery->body)->toContain('Hi Alex,')
        ->and($finalDelivery->body)->toContain('No worries if the timing is not right.');

    $this->travel(6)->days();
    $sequence->evaluate($prospect);

    $state = $prospect->outreachState->fresh();
    expect($state->lifecycle_state)->toBe(ProspectLifecycleState::Exhausted)
        ->and($state->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($state->sequence_step)->toBe(ProspectSequenceStep::Complete)
        ->and($state->stop_reason)->toBe(ProspectOutreachStopReason::Exhausted)
        ->and($state->next_action_at)->toBeNull()
        ->and($prospect->fresh()->next_follow_up_at)->toBeNull()
        ->and($prospect->activities()->where('type', 'outreach_exhausted')->exists())->toBeTrue();
    Mail::assertSent(ProspectOutreach::class, 2);
});

it('does not send the same logical follow up twice when an evaluation is replayed', function (): void {
    Mail::fake();
    $prospect = coldSequenceProspect();
    $sequence = app(ProspectOutreachSequence::class);

    $sequence->evaluate($prospect);
    $prospect->outreachState()->update([
        'sequence_step' => ProspectSequenceStep::InitialEmail,
        'follow_up_attempts' => 0,
        'next_action_at' => now(),
    ]);
    $prospect->update(['next_follow_up_at' => now()]);
    $sequence->evaluate($prospect);

    expect($prospect->outreachDeliveries()->count())->toBe(1)
        ->and($prospect->outreachDeliveries()->sole()->idempotency_key)->toBe('prospect:'.$prospect->id.':cold_follow_up:1');
    Mail::assertSent(ProspectOutreach::class, 1);
});

it('retains and safely retries a failed logical delivery', function (): void {
    $prospect = coldSequenceProspect();
    $sender = app(ProspectOutreachSender::class);
    $sendAttempts = 0;
    Mail::shouldReceive('to')->twice()->andReturnSelf();
    Mail::shouldReceive('send')->twice()->andReturnUsing(function () use (&$sendAttempts): void {
        $sendAttempts++;

        if ($sendAttempts === 1) {
            throw new RuntimeException('Temporary mail transport failure.');
        }
    });

    $send = fn () => $sender->sendAutomated(
        $prospect,
        ProspectOutreachMessageType::ColdFollowUp,
        'Website opportunities',
        'Following up on the audit.',
        'prospect:'.$prospect->id.':cold_follow_up:1',
    );

    expect($send)->toThrow(RuntimeException::class, 'Temporary mail transport failure.');
    $failedDelivery = $prospect->outreachDeliveries()->sole();
    expect($failedDelivery->status)->toBe('failed')
        ->and($failedDelivery->failed_at)->not->toBeNull()
        ->and($failedDelivery->sent_at)->toBeNull();

    $delivery = $send();

    expect($delivery->is($failedDelivery))->toBeTrue()
        ->and($delivery->status)->toBe('sent')
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($prospect->outreachDeliveries()->count())->toBe(1);
});

it('pauses cold advancement when meaningful engagement is present', function (): void {
    Mail::fake();
    $prospect = coldSequenceProspect(['lead_temperature' => 'warm']);
    $prospect->outreachState()->update(['engagement_score' => 5, 'lifecycle_state' => ProspectLifecycleState::Warm]);

    app(ProspectOutreachSequence::class)->evaluate($prospect);

    expect($prospect->outreachDeliveries()->count())->toBe(0)
        ->and($prospect->outreachState->fresh()->next_action_at)->toBeNull()
        ->and($prospect->fresh()->next_follow_up_at)->toBeNull()
        ->and($prospect->activities()->where('type', 'sequence_paused_for_engagement')->exists())->toBeTrue();
    Mail::assertNothingSent();
});

it('honours paused and terminal states immediately before evaluation', function (ProspectAutomationStatus $automationStatus, ProspectLifecycleState $lifecycleState): void {
    Mail::fake();
    $prospect = coldSequenceProspect();
    $prospect->outreachState()->update([
        'automation_status' => $automationStatus,
        'lifecycle_state' => $lifecycleState,
    ]);

    app(ProspectOutreachSequence::class)->evaluate($prospect);

    expect($prospect->outreachDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'paused' => [ProspectAutomationStatus::Paused, ProspectLifecycleState::Cold],
    'replied' => [ProspectAutomationStatus::Stopped, ProspectLifecycleState::Replied],
    'customer' => [ProspectAutomationStatus::Stopped, ProspectLifecycleState::Customer],
]);

it('leaves due actions untouched when automatic follow ups are disabled', function (): void {
    Mail::fake();
    config()->set('outreach.automatic_follow_ups_enabled', false);
    $prospect = coldSequenceProspect();

    app(ProspectOutreachSequence::class)->evaluate($prospect);

    expect($prospect->outreachDeliveries()->count())->toBe(0)
        ->and($prospect->outreachState->fresh()->next_action_at)->not->toBeNull();
    Mail::assertNothingSent();
});
