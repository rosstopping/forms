<?php

use App\Jobs\SendLeadFollowUpReminder;
use App\Mail\LeadFollowUpReminder;
use App\Models\FormSubmission;
use App\Models\FormSubmissionFollowUpReminder;
use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteMailRecipients;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Mail::fake();
    $this->freezeTime();
    $this->website = Website::factory()->create();
    $this->lead = FormSubmission::factory()->for($this->website)->create(['status' => 'new']);
    $this->actingAs($this->website->owner);
});

it('creates reminders only for changed dates and cancels previous schedules', function (): void {
    $this->lead->update(['follow_up_at' => now()->subDay()]);
    $this->artisan('leads:dispatch-follow-up-reminders')->assertSuccessful();
    Mail::assertNothingSent();
    expect(FormSubmissionFollowUpReminder::count())->toBe(0);

    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'new', 'notes' => 'Updated notes'])->assertRedirect();
    expect(FormSubmissionFollowUpReminder::count())->toBe(0);
    $date = now()->addHour()->toDateTimeString();
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'new', 'follow_up_at' => $date])->assertRedirect();
    $first = $this->lead->followUpReminders()->sole();
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'contacted', 'follow_up_at' => $date])->assertRedirect();
    expect($this->lead->followUpReminders()->count())->toBe(1);
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'contacted', 'follow_up_at' => now()->subMinute()->toDateTimeString()])->assertRedirect();
    expect($first->fresh()->status)->toBe('cancelled');
    $second = $this->lead->followUpReminders()->latest('id')->first();
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'contacted', 'follow_up_at' => null])->assertRedirect();
    expect($second->fresh()->status)->toBe('cancelled');
    app()->call([new SendLeadFollowUpReminder($second->id), 'handle']);
    Mail::assertNothingSent();
});

it('sends one due email to an eligible assignee despite repeat scheduler runs', function (): void {
    $assignee = User::factory()->create();
    $this->website->members()->attach($assignee, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $this->lead->update(['assigned_to' => $assignee->id]);
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'new', 'follow_up_at' => now()->subMinute()->toDateTimeString()])->assertRedirect();
    $this->artisan('leads:dispatch-follow-up-reminders')->assertSuccessful();
    $this->artisan('leads:dispatch-follow-up-reminders')->assertSuccessful();
    $reminder = $this->lead->followUpReminders()->sole();
    app()->call([new SendLeadFollowUpReminder($reminder->id), 'handle']);
    Mail::assertSent(LeadFollowUpReminder::class, 1);
    Mail::assertSent(LeadFollowUpReminder::class, fn ($mail): bool => $mail->hasTo($assignee->email) && ! $mail->hasTo($this->lead->replyToEmail()));
    expect($reminder->fresh()->status)->toBe('sent')
        ->and($reminder->fresh()->recipient)->toBe($assignee->email)
        ->and($reminder->fresh()->sent_at)->not->toBeNull()
        ->and($this->lead->activities()->where('type', 'follow_up_reminder_sent')->count())->toBe(1);
});

it('falls back to the owner when an assignee is absent or no longer eligible', function (string $access): void {
    $assignee = User::factory()->create();
    if ($access === 'viewer') {
        $this->website->members()->attach($assignee, ['role' => Website::MEMBER_ROLE_VIEWER]);
    }
    $this->lead->update(['assigned_to' => $access === 'unassigned' ? null : $assignee->id, 'follow_up_at' => now()]);
    $reminder = FormSubmissionFollowUpReminder::factory()->for($this->lead, 'submission')->create(['due_at' => now()]);
    app()->call([new SendLeadFollowUpReminder($reminder->id), 'handle']);
    Mail::assertSent(LeadFollowUpReminder::class, fn ($mail): bool => $mail->hasTo($this->website->owner->email) && ! $mail->hasTo($assignee->email));
})->with(['unassigned', 'removed', 'viewer']);

it('rechecks eligibility before a queued reminder sends', function (string $change): void {
    $this->lead->update(['follow_up_at' => now()]);
    $reminder = FormSubmissionFollowUpReminder::factory()->for($this->lead, 'submission')->create(['due_at' => now()]);
    match ($change) {
        'spam' => $this->lead->update(['is_spam' => true]),
        'won', 'lost', 'work_completed' => $this->lead->update(['status' => $change]),
        'cleared' => $this->lead->update(['follow_up_at' => null]),
        'rescheduled' => $this->lead->update(['follow_up_at' => now()->addHour()]),
        'inactive website' => $this->website->update(['is_active' => false]),
        'expired membership' => $this->website->owner->update(['membership_status' => 'canceled']),
        'viewer owner' => $this->website->members()->attach($this->website->owner, ['role' => Website::MEMBER_ROLE_VIEWER]),
        'deleted' => $this->lead->delete(),
    };
    app()->call([new SendLeadFollowUpReminder($reminder->id), 'handle']);
    Mail::assertNothingSent();
    if ($change === 'deleted') {
        $this->assertModelMissing($reminder);
    } else {
        expect($reminder->fresh()->status)->toBe('cancelled')
            ->and($this->lead->activities()->where('type', 'follow_up_reminder_cancelled')->count())->toBe(1);
    }
})->with(['spam', 'won', 'lost', 'work_completed', 'cleared', 'rescheduled', 'inactive website', 'expired membership', 'viewer owner', 'deleted']);

it('waits for the due time and uses a unique job per reminder', function (): void {
    Queue::fake();
    $this->lead->update(['follow_up_at' => now()->addHour()]);
    $reminder = FormSubmissionFollowUpReminder::factory()->for($this->lead, 'submission')->create(['due_at' => now()->addHour()]);
    $this->artisan('leads:dispatch-follow-up-reminders')->assertSuccessful();
    Queue::assertNothingPushed();
    app()->call([new SendLeadFollowUpReminder($reminder->id), 'handle']);
    Mail::assertNothingSent();
    $this->travel(1)->hours();
    $this->artisan('leads:dispatch-follow-up-reminders')->assertSuccessful();
    $this->artisan('leads:dispatch-follow-up-reminders')->assertSuccessful();
    Queue::assertPushed(SendLeadFollowUpReminder::class, 1);
    expect((new SendLeadFollowUpReminder($reminder->id))->uniqueId())->toBe((string) $reminder->id);
});

it('records mail failures and retries without marking unsuccessful sends as sent', function (): void {
    $this->lead->update(['follow_up_at' => now()]);
    $reminder = FormSubmissionFollowUpReminder::factory()->for($this->lead, 'submission')->create(['due_at' => now()]);
    $job = new SendLeadFollowUpReminder($reminder->id);
    $mailFake = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));
    expect(fn () => $job->handle(app(WebsiteMailRecipients::class)))->toThrow(RuntimeException::class);
    expect($reminder->fresh()->status)->toBe('pending')->and($reminder->fresh()->sent_at)->toBeNull()->and($reminder->fresh()->error)->toBe('Mail unavailable');
    Mail::swap($mailFake);
    $job->handle(app(WebsiteMailRecipients::class));
    Mail::assertSent(LeadFollowUpReminder::class, 1);
    expect($reminder->fresh()->status)->toBe('sent')->and($reminder->fresh()->error)->toBeNull();
});

it('records terminal failures once and does not dispatch them again', function (): void {
    $reminder = FormSubmissionFollowUpReminder::factory()->for($this->lead, 'submission')->create(['due_at' => now()]);
    $job = new SendLeadFollowUpReminder($reminder->id);
    $job->failed(new RuntimeException('Mail unavailable'));
    $job->failed(new RuntimeException('Mail unavailable'));
    Queue::fake();
    $this->artisan('leads:dispatch-follow-up-reminders')->assertSuccessful();
    Queue::assertNothingPushed();
    expect($reminder->fresh()->status)->toBe('failed')->and($reminder->fresh()->failed_at)->not->toBeNull()
        ->and($this->lead->activities()->where('type', 'follow_up_reminder_failed')->count())->toBe(1);
});

it('renders a branded internal email with lead context and an authenticated link', function (): void {
    $this->lead->update(['data' => ['name' => 'Ada', 'message' => 'Please call about a kitchen quote.']]);
    $reminder = FormSubmissionFollowUpReminder::factory()->for($this->lead, 'submission')->create(['due_at' => now()]);
    $mail = new LeadFollowUpReminder($this->lead, $reminder);
    $mail->assertSeeInHtml('Ada')->assertSeeInHtml('Please call about a kitchen quote.')
        ->assertSeeInHtml(config('app.timezone'))->assertSeeInHtml(route('admin.form-submissions.show', $this->lead))
        ->assertSeeInText('Sitewell')->assertSeeInText($this->website->name);
    $this->actingAs(User::factory()->create())->get(route('admin.form-submissions.show', $this->lead))->assertForbidden();
});

it('schedules reminder dispatch every minute on one server', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command ?? '', 'leads:dispatch-follow-up-reminders'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *')->and($event->onOneServer)->toBeTrue()->and($event->withoutOverlapping)->toBeTrue();
});
