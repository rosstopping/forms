<?php

use App\Jobs\SendReviewInvitation;
use App\Mail\CustomerReviewInvitation;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ReviewInvitation;
use App\Models\User;
use App\Models\Website;
use App\Services\ReviewInvitationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create(['name' => 'Example Services', 'review_url' => 'https://example.com/review']);
    $form = Form::factory()->for($this->website)->create();
    $this->lead = FormSubmission::factory()->for($this->website)->for($form)->create([
        'status' => 'work_completed', 'data' => ['name' => 'Alex Customer', 'email' => 'customer@example.com'],
    ]);
    $this->actingAs($this->owner);
    $this->reviews = app(ReviewInvitationService::class);
    $this->previewHash = fn (): string => $this->reviews->fingerprint($this->reviews->snapshot($this->lead->fresh()));
    $this->queueInvitation = function (): ReviewInvitation {
        $this->post(route('admin.form-submissions.review-invitations.store', $this->lead), ['preview_hash' => ($this->previewHash)()])->assertSessionDoesntHaveErrors();

        return $this->lead->reviewInvitation()->sole();
    };
});

it('lets managers configure a website review link without sending an email', function () {
    $manager = User::factory()->create(['membership_tier' => 'essential']);
    $this->website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $this->actingAs($manager)->put(route('admin.form-submissions.review-link', $this->lead), ['review_url' => 'https://example.com/new-review-link'])->assertSessionDoesntHaveErrors();
    expect($this->website->fresh()->review_url)->toBe('https://example.com/new-review-link');
    Queue::assertNothingPushed();
    Mail::assertNothingOutgoing();
    $this->put(route('admin.form-submissions.review-link', $this->lead), ['review_url' => ''])->assertSessionDoesntHaveErrors();
    expect($this->website->fresh()->review_url)->toBeNull();
});

it('rejects invalid and unsafe review links', function (mixed $url) {
    $this->put(route('admin.form-submissions.review-link', $this->lead), ['review_url' => $url])->assertSessionHasErrors('review_url');
    expect($this->website->fresh()->review_url)->toBe('https://example.com/review');
})->with(['javascript:alert(1)', 'not a url', 'http://example.com', [['https://example.com']]]);

it('previews the recipient subject and honest review message without sending', function () {
    $this->get(route('admin.form-submissions.show', $this->lead))->assertSuccessful()
        ->assertSee('Preview review invitation')->assertSee('customer@example.com')->assertSee('Share your experience with Example Services')
        ->assertSee('honest review')->assertSee('https://example.com/review')->assertSee('Send review invitation')
        ->assertDontSee('Sent via Sitewell.');
    Queue::assertNothingPushed();
    Mail::assertNothingOutgoing();
});

it('requires the new work completed status and does not treat won as completion', function (string $status) {
    $this->lead->update(['status' => $status]);
    $this->post(route('admin.form-submissions.review-invitations.store', $this->lead), ['preview_hash' => ($this->previewHash)()])->assertSessionHasErrors('review_invitation');
    $this->get(route('admin.form-submissions.show', $this->lead))->assertDontSee('Send review invitation')->assertSee('Mark this lead as Work completed');
    Queue::assertNothingPushed();
})->with(['new', 'contacted', 'qualified', 'won', 'lost']);

it('supports work completed in individual updates bulk actions filters and status labels', function () {
    $this->lead->update(['status' => 'won', 'follow_up_at' => now()->subDay()]);
    $this->put(route('admin.form-submissions.update', $this->lead), ['status' => 'work_completed'])->assertSessionDoesntHaveErrors();
    expect($this->lead->fresh()->resolvedStatusLabel())->toBe('Work completed');
    $this->get(route('admin.form-submissions.index', ['status' => 'work_completed']))->assertSuccessful()->assertSee('Alex Customer')->assertSee('Work completed');
    $this->get(route('admin.form-submissions.index', ['follow_up' => 'overdue']))->assertSuccessful()->assertDontSee('Alex Customer')->assertDontSee('aria-label="1 lead follow-ups due"', false);
    $this->lead->update(['status' => 'won']);
    $this->patch(route('admin.form-submissions.bulk'), ['selection_scope' => 'page', 'submission_ids' => [$this->lead->id], 'action' => 'update_status', 'status' => 'work_completed'])->assertSessionDoesntHaveErrors();
    expect($this->lead->fresh()->status)->toBe('work_completed');
    Queue::assertNothingPushed();
});

it('queues one immutable invitation and ignores repeated clicks', function () {
    $invitation = ($this->queueInvitation)();
    ($this->queueInvitation)();
    Queue::assertPushed(SendReviewInvitation::class, 1);
    expect(ReviewInvitation::count())->toBe(1)->and($invitation->recipient)->toBe('customer@example.com')
        ->and($invitation->review_url)->toBe('https://example.com/review')
        ->and($invitation->requested_by)->toBe($this->owner->id)
        ->and($this->lead->activities()->where('type', 'review_invitation_queued')->count())->toBe(1);
    $this->get(route('admin.form-submissions.show', $this->lead))->assertSuccessful()->assertSee('Invitation history')->assertSee('Queued')->assertDontSee('Send review invitation');
    Mail::assertNothingOutgoing();
});

it('sends the saved email once and records its timestamp and history', function () {
    $invitation = ($this->queueInvitation)();
    $this->website->update(['name' => 'New business name']);
    $job = new SendReviewInvitation($invitation->id);
    $job->handle($this->reviews);
    $job->handle($this->reviews);
    Mail::assertSent(CustomerReviewInvitation::class, 1);
    Mail::assertSent(CustomerReviewInvitation::class, fn ($mail) => $mail->hasTo('customer@example.com') && $mail->invitation->from_name === 'Example Services');
    expect($invitation->fresh()->status)->toBe('sent')->and($invitation->fresh()->sent_at)->not->toBeNull()
        ->and($this->lead->activities()->where('type', 'review_invitation_sent')->count())->toBe(1);
    $this->get(route('admin.form-submissions.show', $this->lead))->assertSee('Sent:')->assertSee('Review submissions are not tracked.');
});

it('refuses stale previews rather than sending changed details', function () {
    $hash = ($this->previewHash)();
    $this->website->update(['review_url' => 'https://example.com/changed']);
    $this->post(route('admin.form-submissions.review-invitations.store', $this->lead), ['preview_hash' => $hash])->assertSessionHasErrors('review_invitation');
    expect(ReviewInvitation::count())->toBe(0);
});

it('does not allow viewers or unrelated users to send or configure invitations', function (bool $viewer) {
    $user = User::factory()->create();
    if ($viewer) {
        $this->website->members()->attach($user, ['role' => Website::MEMBER_ROLE_VIEWER]);
    }
    $this->actingAs($user)->post(route('admin.form-submissions.review-invitations.store', $this->lead), ['preview_hash' => ($this->previewHash)()])->assertForbidden();
    $this->put(route('admin.form-submissions.review-link', $this->lead), ['review_url' => 'https://example.com/changed'])->assertForbidden();
    if ($viewer) {
        $this->get(route('admin.form-submissions.show', $this->lead))->assertSuccessful()->assertDontSee('Save review link')->assertDontSee('Send review invitation');
    }
})->with([true, false]);

it('blocks spam missing emails missing links and inactive memberships before queueing', function (string $reason) {
    match ($reason) {
        'spam' => $this->lead->update(['is_spam' => true]),
        'email' => $this->lead->update(['data' => ['name' => 'No email']]),
        'link' => $this->website->update(['review_url' => null]),
        'website' => $this->website->update(['is_active' => false]),
        'membership' => $this->owner->update(['membership_status' => 'canceled']),
    };
    $this->post(route('admin.form-submissions.review-invitations.store', $this->lead), ['preview_hash' => ($this->previewHash)()])->assertSessionHasErrors('review_invitation');
    Queue::assertNothingPushed();
})->with(['spam', 'email', 'link', 'website', 'membership']);

it('rechecks eligibility and cancels stale invitations before sending', function (string $change) {
    $invitation = ($this->queueInvitation)();
    match ($change) {
        'spam' => $this->lead->update(['is_spam' => true]),
        'reopened' => $this->lead->update(['status' => 'won']),
        'email' => $this->lead->update(['data' => ['email' => 'different@example.com']]),
        'link' => $this->website->update(['review_url' => 'https://example.com/different']),
        'website' => $this->website->update(['is_active' => false]),
        'membership' => $this->owner->update(['membership_status' => 'canceled']),
        'permission' => $this->website->members()->attach($this->owner, ['role' => Website::MEMBER_ROLE_VIEWER]),
    };
    (new SendReviewInvitation($invitation->id))->handle($this->reviews);
    expect($invitation->fresh()->status)->toBe('cancelled')->and($invitation->fresh()->cancelled_at)->not->toBeNull();
    Mail::assertNothingOutgoing();
})->with(['spam', 'reopened', 'email', 'link', 'website', 'membership', 'permission']);

it('records delivery failures without automatically repeating a potentially sent email', function () {
    $invitation = ($this->queueInvitation)();
    Mail::shouldReceive('to')->once()->with('customer@example.com')->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Email transport unavailable'));
    $job = new SendReviewInvitation($invitation->id);
    expect(fn () => $job->handle($this->reviews))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('Email transport unavailable'));
    $job->handle($this->reviews);
    expect($invitation->fresh()->status)->toBe('failed')->and($invitation->fresh()->failed_at)->not->toBeNull()
        ->and($this->lead->activities()->where('type', 'review_invitation_failed')->count())->toBe(1);
});

it('renders escaped customer content and the saved link in HTML and plain text', function () {
    $this->lead->update(['data' => ['name' => '<script>alert(1)</script>', 'email' => 'customer@example.com']]);
    $invitation = ($this->queueInvitation)();
    $mail = new CustomerReviewInvitation($invitation);
    $mail->assertSeeInHtml('&lt;script&gt;', false)->assertDontSeeInHtml('<script>', false)
        ->assertSeeInHtml('Leave an honest review')->assertSeeInText('https://example.com/review');
    expect($mail->envelope()->from->address)->toBe(config('forms.autoresponder_from_address'));
});

it('brands review emails with the saved website name instead of Sitewell', function () {
    $this->website->update(['name' => 'Example & Partners']);
    $invitation = ($this->queueInvitation)();
    $this->website->update(['name' => 'Renamed Business']);

    $mail = new CustomerReviewInvitation($invitation);
    $mail->assertSeeInHtml('<title>Example &amp; Partners</title>', false)
        ->assertSeeInHtml('Example & Partners')
        ->assertSeeInText('Example & Partners')
        ->assertDontSeeInHtml('Sitewell')
        ->assertDontSeeInText('Sitewell')
        ->assertDontSeeInHtml('Renamed Business')
        ->assertDontSeeInHtml('Your website, well looked after.')
        ->assertDontSeeInHtml(route('marketing.home'));

    expect($mail->envelope()->from->name)->toBe('Example & Partners');
});
