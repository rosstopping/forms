<?php

use App\Jobs\AnalyzeProspect;
use App\Jobs\SendScheduledProspectOutreach;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\User;
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

it('shows the outreach bulk selection and all five actions', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    Prospect::factory()->count(21)->for($admin, 'owner')->create();

    $this->actingAs($admin)->get(route('admin.prospects.index'))
        ->assertSuccessful()
        ->assertSee('data-bulk-prospects-form', false)
        ->assertSee('Select all 21 matching prospects')
        ->assertSeeInOrder(['Approve Draft', 'Research Again', 'Schedule Approved Email', 'Send Approved Email', 'Delete']);
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
