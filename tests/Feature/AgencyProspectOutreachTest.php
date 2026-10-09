<?php

use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectSequenceStep;
use App\Jobs\AnalyzeProspect;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\User;
use App\Services\InitialProspectOutreachGenerator;
use App\Services\ProspectLifecycleManager;
use App\Services\ProspectOutreachContent;
use App\Services\ProspectOutreachPlan;
use App\Services\ProspectOutreachSender;
use App\Services\ProspectOutreachSequence;
use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('creates a partner draft without research or sending', function (string $type, string $subject): void {
    Queue::fake();
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->post(route('admin.prospects.store'), [
        'prospect_type' => $type, 'business_name' => 'Example Studio',
        'contact_name' => 'Alex Smith', 'email' => 'alex@example.com', 'website_url' => 'https://example.com',
    ])->assertRedirect();
    $prospect = Prospect::query()->sole();
    expect($prospect->prospect_type)->toBe($type)->and($prospect->analysis_status)->toBe('skipped')
        ->and($prospect->status)->toBe('drafted')->and($prospect->approved_at)->toBeNull()
        ->and($prospect->outreach_subject)->toBe($subject)->and($prospect->outreach_body)->toStartWith("Hi Alex,\n")
        ->and($prospect->include_site_audit)->toBeFalse();
    Queue::assertNothingPushed();
    Mail::assertNothingOutgoing();
    $this->get(route('admin.prospects.show', $prospect))->assertSuccessful()->assertSee('Partner follow-up')->assertDontSee('Website opportunities');
    $templates = app(ProspectOutreachContent::class)->draftTemplates($prospect);
    expect($templates)->toHaveKeys(['saved', 'initial', 'partner_follow_up'])->not->toHaveKey('final_follow_up');
})->with([
    ['web_design_agency', 'Fancy offering SEO without doing the work?'],
    ['freelance_web_developer', 'Fancy making a bit more from your web clients?'],
]);

it('defaults old creation to the unchanged potential client research workflow', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->post(route('admin.prospects.store'), ['business_name' => 'Client', 'website_url' => 'https://example.com'])->assertRedirect();
    $prospect = Prospect::query()->sole();
    expect($prospect->prospect_type)->toBe('potential_client');
    Queue::assertPushed(AnalyzeProspect::class);
});

it('uses a fallback greeting and skips queued or manual partner analysis', function (): void {
    $prospect = Prospect::factory()->create(['prospect_type' => 'web_design_agency', 'contact_name' => null]);
    expect(app(InitialProspectOutreachGenerator::class)->generate($prospect)['body'])->toStartWith("Hi there,\n");
    $analyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $analyzer->shouldNotReceive('analyze');
    (new AnalyzeProspect($prospect))->handle($analyzer, app(ProspectLifecycleManager::class), app(InitialProspectOutreachGenerator::class));
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->post(route('admin.prospects.analyse', $prospect))->assertStatus(422);
});

it('sends a tracked personal partner email and exactly one follow-up after six working days', function (): void {
    Mail::fake();
    $this->travelTo(now()->setDate(2026, 10, 9)->setTime(10, 0));
    $prospect = Prospect::factory()->create(['prospect_type' => 'web_design_agency', 'contact_name' => 'Alex Smith', 'website_url' => null, 'showcase_video_url' => null, 'approved_at' => now(), 'status' => 'approved']);
    $draft = app(InitialProspectOutreachGenerator::class)->generate($prospect);
    $prospect->update(['outreach_subject' => $draft['subject'], 'outreach_body' => $draft['body']]);
    app(ProspectOutreachSender::class)->send($prospect);
    $prospect->refresh();
    expect($prospect->next_follow_up_at->toDateString())->toBe('2026-10-19');
    $delivery = $prospect->outreachDeliveries()->sole();
    expect($delivery->links()->sole()->kind)->toBe('sitewell');
    $html = (new ProspectOutreach($prospect, $delivery))->render();
    expect($html)->toContain('/outreach/click/', 'Unsubscribe')->not->toContain('Full disclosure', 'Watch your video', 'Want to have a quick chat?', 'We’re Digizu');
    $this->travelTo($prospect->next_follow_up_at->addMinute());
    app(ProspectOutreachSequence::class)->evaluate($prospect);
    $prospect->refresh();
    expect($prospect->outreachDeliveries()->count())->toBe(2)
        ->and($prospect->outreachState->follow_up_attempts)->toBe(1)
        ->and($prospect->outreachState->sequence_step)->toBe(ProspectSequenceStep::Complete)
        ->and($prospect->next_follow_up_at)->toBeNull();
    $followUp = $prospect->outreachDeliveries()->where('message_type', ProspectOutreachMessageType::ColdFollowUp->value)->sole();
    expect($followUp->body)->toContain('in case it got buried')->not->toContain('audit');
    app(ProspectOutreachSequence::class)->evaluate($prospect);
    Mail::assertSentCount(2);
});

it('does not follow up with replied or suppressed partners', function (string $field): void {
    Mail::fake();
    $prospect = Prospect::factory()->create(['prospect_type' => 'freelance_web_developer', 'approved_at' => now()]);
    $draft = app(InitialProspectOutreachGenerator::class)->generate($prospect);
    $prospect->update(['outreach_subject' => $draft['subject'], 'outreach_body' => $draft['body']]);
    app(ProspectOutreachSender::class)->send($prospect);
    if ($field === 'replied') {
        app(ProspectLifecycleManager::class)->transitionManually($prospect, ProspectLifecycleState::Replied);
    } else {
        $prospect->forceFill([$field => now()])->save();
    }
    $this->travel(14)->days();
    app(ProspectOutreachSequence::class)->evaluate($prospect);
    Mail::assertSentCount(1);
})->with(['replied', 'suppressed_at', 'unsubscribed_at']);

it('filters partner types and preserves their editable follow-up', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $partner = Prospect::factory()->create(['business_name' => 'Partner Studio', 'prospect_type' => 'web_design_agency', 'approved_at' => now()]);
    Prospect::factory()->create(['business_name' => 'Direct Business']);
    $this->actingAs($admin)->get(route('admin.prospects.index', ['prospect_type' => 'web_design_agency']))->assertSuccessful()->assertSee('Partner Studio')->assertDontSee('Direct Business');
    $this->put(route('admin.prospects.update', $partner), [
        'business_name' => $partner->business_name, 'status' => 'drafted',
        'outreach_subject' => $partner->outreach_subject, 'outreach_body' => $partner->outreach_body,
        'partner_follow_up_subject' => 'A quick follow-up', 'partner_follow_up_body' => 'Hi Alex, fancy a chat?',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $partner->refresh();
    expect(app(ProspectOutreachContent::class)->followUp($partner, ProspectOutreachMessageType::ColdFollowUp)['body'])->toBe('Hi Alex, fancy a chat?')->and($partner->approved_at)->toBeNull();
    expect(app(ProspectOutreachPlan::class)->forProspect($partner)['rows'])->toHaveCount(2);
});

it('rejects unsupported types and keeps the existing direct-client templates', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->post(route('admin.prospects.store'), ['business_name' => 'Studio', 'prospect_type' => 'unknown'])->assertSessionHasErrors('prospect_type');
    $this->assertDatabaseCount('prospects', 0);
    $client = Prospect::factory()->create();
    expect(app(ProspectOutreachContent::class)->draftTemplates($client))->toHaveKeys(['saved', 'final_follow_up'])->not->toHaveKey('partner_follow_up');
});

it('allows partner test emails without a website or individual video and escapes editable HTML', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $partner = Prospect::factory()->create(['prospect_type' => 'web_design_agency', 'website_url' => null, 'showcase_video_url' => null, 'outreach_subject' => 'Hello', 'outreach_body' => '<script>alert(1)</script>']);
    $this->actingAs($admin)->post(route('admin.prospects.test-email', $partner))->assertRedirect();
    Mail::assertSent(ProspectOutreach::class, fn ($mail): bool => $mail->hasTo($admin->email));
    expect((new ProspectOutreach($partner))->render())->toContain('&lt;script&gt;')->not->toContain('<script>');
    $this->assertDatabaseCount('prospect_outreach_deliveries', 0);
});

it('keeps sent delivery snapshots and scheduled direct-client drafts unchanged', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $client = Prospect::factory()->create(['outreach_subject' => 'Existing subject', 'outreach_body' => 'Existing body', 'scheduled_send_at' => now()->addDay(), 'approved_at' => now()]);
    $client->outreachDeliveries()->create(['recipient_email' => $client->email, 'message_type' => 'initial', 'subject' => 'Sent subject', 'body' => 'Sent body', 'status' => 'sent', 'sent_at' => now()]);
    Queue::fake();
    $this->actingAs($admin)->post(route('admin.prospects.store'), ['business_name' => 'Partner', 'prospect_type' => 'web_design_agency'])->assertRedirect();
    expect($client->fresh()->outreach_body)->toBe('Existing body')->and($client->fresh()->scheduled_send_at->equalTo($client->scheduled_send_at))->toBeTrue()
        ->and($client->outreachDeliveries()->sole()->body)->toBe('Sent body');
});

it('keeps all-matching bulk actions inside the selected prospect type', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $partner = Prospect::factory()->create(['prospect_type' => 'web_design_agency', 'outreach_subject' => 'Partner', 'outreach_body' => 'Partner draft']);
    $client = Prospect::factory()->create(['outreach_subject' => 'Client', 'outreach_body' => 'Client draft']);
    $this->actingAs($admin)->post(route('admin.prospects.bulk'), ['action' => 'approve', 'selection_scope' => 'all', 'prospect_type' => 'web_design_agency'])->assertRedirect()->assertSessionHasNoErrors();
    expect($partner->fresh()->approved_at)->not->toBeNull()->and($client->fresh()->approved_at)->toBeNull();
});
