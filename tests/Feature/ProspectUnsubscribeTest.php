<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectOutreachStopReason;
use App\Jobs\SendScheduledProspectOutreach;
use App\Jobs\SendScheduledProspectPersonalisedVideo;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Models\ProspectOutreachLink;
use App\Models\User;
use App\Services\ProspectLifecycleManager;
use App\Services\ProspectOutreachSender;
use App\Services\ProspectOutreachSequence;
use App\Services\ProspectUnsubscriber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('requires confirmation and records unsubscribe once while cancelling only unsent outreach', function () {
    $this->freezeTime();
    Mail::fake();
    $prospect = Prospect::factory()->create([
        'approved_at' => now(), 'scheduled_send_at' => now()->addHour(), 'next_follow_up_at' => now()->addDay(),
    ]);
    $prospect->outreachState->update(['next_action_at' => now()->addDay()]);
    $sent = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $pending = collect(['pending', 'scheduled', 'failed'])->map(fn (string $status) => ProspectOutreachDelivery::factory()->for($prospect)->create([
        'status' => $status, 'sent_at' => null, 'scheduled_at' => now()->addHour(),
    ]));
    $url = URL::signedRoute('prospects.unsubscribe.show', $prospect);
    $this->get($url)->assertSuccessful()->assertSee('Confirm unsubscribe');
    expect($prospect->fresh()->unsubscribed_at)->toBeNull()
        ->and($prospect->activities()->where('type', 'unsubscribed')->count())->toBe(0);

    $this->post($url)->assertRedirect($url);
    $unsubscribedAt = $prospect->fresh()->unsubscribed_at;
    $this->travel(1)->hour();
    $this->post($url)->assertRedirect($url);
    $this->get($url)->assertSuccessful()->assertSee('You’re unsubscribed')->assertDontSee('Confirm unsubscribe');
    expect($prospect->fresh()->unsubscribed_at->equalTo($unsubscribedAt))->toBeTrue()
        ->and($prospect->fresh()->suppressed_at)->not->toBeNull()
        ->and($prospect->fresh()->scheduled_send_at)->toBeNull()
        ->and($prospect->fresh()->next_follow_up_at)->toBeNull()
        ->and($prospect->outreachState->fresh()->next_action_at)->toBeNull()
        ->and($prospect->outreachState->fresh()->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($prospect->outreachState->fresh()->stop_reason)->toBe(ProspectOutreachStopReason::Unsubscribed)
        ->and($prospect->activities()->where('type', 'unsubscribed')->count())->toBe(1)
        ->and($prospect->engagementEvents()->count())->toBe(0)
        ->and($sent->fresh()->status)->toBe('sent');
    foreach ($pending as $delivery) {
        expect($delivery->fresh()->status)->toBe('cancelled')->and($delivery->fresh()->scheduled_at)->toBeNull();
    }
    Mail::assertNothingSent();
});

it('rejects unsigned and tampered unsubscribe requests', function () {
    $prospect = Prospect::factory()->create();
    $other = Prospect::factory()->create();
    $unsigned = route('prospects.unsubscribe.show', $prospect);
    $signed = URL::signedRoute('prospects.unsubscribe.show', $prospect);
    $tampered = str_replace('/outreach/'.$prospect->id.'/', '/outreach/'.$other->id.'/', $signed);
    foreach ([$unsigned, $tampered] as $url) {
        $this->get($url)->assertForbidden();
        $this->post($url)->assertForbidden();
    }
    expect($prospect->fresh()->unsubscribed_at)->toBeNull()->and($other->fresh()->unsubscribed_at)->toBeNull();
});

it('includes the unsubscribe footer in every live outreach message type', function (ProspectOutreachMessageType $type) {
    $prospect = Prospect::factory()->create();
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create(['message_type' => $type]);
    $mail = new ProspectOutreach($prospect, $delivery);
    $mail->assertSeeInHtml('Unsubscribe from outreach emails');
    expect($mail->content()->with['unsubscribeUrl'])->toBe(URL::signedRoute('prospects.unsubscribe.show', $prospect));
})->with(ProspectOutreachMessageType::cases());

it('makes test email unsubscribe links harmless previews', function () {
    $prospect = Prospect::factory()->create();
    $mail = new ProspectOutreach($prospect);
    $url = $mail->content()->with['unsubscribeUrl'];
    $mail->assertSeeInHtml('Unsubscribe from outreach emails');
    $this->get($url)->assertSuccessful()->assertSee('Unsubscribe preview')->assertDontSee('Confirm unsubscribe');
    $this->post($url)->assertForbidden();
    $this->post(str_replace('preview=1&', '', $url))->assertForbidden();
    expect($prospect->fresh()->unsubscribed_at)->toBeNull();
});

it('blocks queued sends and every live send path after unsubscribe', function () {
    Mail::fake();
    $scheduledFor = CarbonImmutable::now()->addHour();
    $prospect = Prospect::factory()->create(['approved_at' => now(), 'scheduled_send_at' => $scheduledFor]);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create([
        'message_type' => ProspectOutreachMessageType::PersonalisedVideo,
        'status' => 'scheduled', 'sent_at' => null, 'scheduled_at' => $scheduledFor,
    ]);
    app(ProspectUnsubscriber::class)->unsubscribe($prospect);
    app()->call([new SendScheduledProspectOutreach($prospect->id, $scheduledFor), 'handle']);
    app()->call([new SendScheduledProspectPersonalisedVideo($delivery->id, $scheduledFor), 'handle']);
    app(ProspectOutreachSequence::class)->evaluate($prospect->fresh());
    $sender = app(ProspectOutreachSender::class);
    expect(fn () => $sender->send($prospect))->toThrow(LogicException::class, 'unsubscribed')
        ->and(fn () => $sender->sendAutomated($prospect, ProspectOutreachMessageType::ColdFollowUp, 'Subject', 'Body', 'follow-up'))->toThrow(LogicException::class, 'unsubscribed')
        ->and(fn () => $sender->sendPersonalisedVideo($prospect, 'Subject', 'Body', 'video'))->toThrow(LogicException::class, 'unsubscribed');
    Mail::assertNothingSent();
});

it('keeps unsubscribe visible and prevents ordinary edits or resuming from clearing it', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['status' => 'approved', 'approved_at' => now()]);
    app(ProspectUnsubscriber::class)->unsubscribe($prospect);
    $this->actingAs($admin)->put(route('admin.prospects.update', $prospect), [
        'business_name' => $prospect->business_name, 'email' => $prospect->email, 'status' => 'drafted',
        'outreach_subject' => 'Edited subject', 'outreach_body' => 'Edited message', 'suppressed' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($prospect->fresh()->unsubscribed_at)->not->toBeNull()
        ->and($prospect->fresh()->suppressed_at)->not->toBeNull()
        ->and(fn () => app(ProspectLifecycleManager::class)->resume($prospect))->toThrow(InvalidArgumentException::class, 'unsubscribed');
    $this->get(route('admin.prospects.show', [$prospect, 'section' => 'activity']))->assertSuccessful()
        ->assertSee('Unsubscribed')->assertSee('Recipient confirmed unsubscribe.');
});

it('keeps outreach stopped after old link clicks and delayed video completion', function () {
    Notification::fake();
    $prospect = Prospect::factory()->create(['lead_temperature' => 'cold']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $link = ProspectOutreachLink::factory()->for($delivery, 'delivery')->create();
    app(ProspectUnsubscriber::class)->unsubscribe($prospect);

    $this->get(URL::signedRoute('prospect-outreach-links.show', $link))->assertRedirect();
    app(ProspectLifecycleManager::class)->markPersonalisedVideoSent($prospect, now());

    expect($prospect->outreachState->fresh()->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($prospect->outreachState->fresh()->stop_reason)->toBe(ProspectOutreachStopReason::Unsubscribed)
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(0)
        ->and($prospect->outreachState->fresh()->manual_follow_up_required_at)->toBeNull()
        ->and($prospect->fresh()->lead_temperature)->toBe('cold');
    Notification::assertNothingSent();
});
