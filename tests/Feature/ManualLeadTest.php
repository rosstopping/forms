<?php

use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;
use App\Services\ReviewInvitationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    $this->owner->update(['current_website_id' => $this->website->id]);
    $this->actingAs($this->owner);
    $this->payload = ['website_id' => $this->website->id, 'name' => 'Telephone Customer', 'email' => 'customer@example.com', 'phone' => '+44 1234 567890', 'message' => 'Asked for a quote by phone.', 'status' => 'new'];
});

it('creates a manual lead without any detected form or outbound communication', function () {
    $this->get(route('admin.form-submissions.index'))->assertSuccessful()->assertSee('Add lead');
    $this->get(route('admin.form-submissions.create'))->assertSuccessful()->assertSee($this->website->name)->assertSee('Phone number');
    $this->post(route('admin.form-submissions.store'), [...$this->payload, 'form_id' => 999, 'is_spam' => true, 'is_manual' => false])->assertSessionDoesntHaveErrors();
    $lead = FormSubmission::sole();
    expect($lead->is_manual)->toBeTrue()->and($lead->form_id)->toBeNull()->and($lead->is_spam)->toBeFalse()
        ->and($lead->website_id)->toBe($this->website->id)->and($lead->data['phone'])->toBe('+44 1234 567890')
        ->and($lead->source_url)->toBeNull()->and($lead->ip_address)->toBeNull()
        ->and($lead->activities()->sole()->user_id)->toBe($this->owner->id)->and($this->website->forms()->count())->toBe(0);
    $this->get(route('admin.form-submissions.show', $lead))->assertSuccessful()->assertSee('Manual lead')->assertDontSee('Resend email notification');
    $this->get(route('admin.form-submissions.index'))->assertSuccessful()->assertSee('Telephone Customer')->assertSee('Manual lead');
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
});

it('permits a name-only lead and records a chosen follow-up date', function () {
    $due = now()->addDay()->startOfMinute();
    $this->post(route('admin.form-submissions.store'), ['website_id' => $this->website->id, 'name' => 'Walk-in Customer', 'status' => 'new', 'follow_up_at' => $due->toDateTimeString()])->assertSessionDoesntHaveErrors();
    $lead = FormSubmission::sole();
    expect($lead->replyToEmail())->toBeNull()->and($lead->followUpReminders()->sole()->due_at->equalTo($due))->toBeTrue();
    Mail::assertNothingOutgoing();
});

it('rejects invalid manual lead fields without creating a lead', function (array $changes, string $field) {
    $this->post(route('admin.form-submissions.store'), [...$this->payload, ...$changes])->assertSessionHasErrors($field);
    expect(FormSubmission::count())->toBe(0);
})->with([
    [['name' => ''], 'name'],
    [['email' => 'invalid'], 'email'],
    [['phone' => str_repeat('1', 51)], 'phone'],
    [['status' => 'invented'], 'status'],
    [['message' => ['invalid']], 'message'],
]);

it('rejects stale website selections and cross-website injection even for admins', function () {
    $other = Website::factory()->for($this->owner, 'owner')->create();
    $this->owner->update(['role' => User::ROLE_ADMIN]);
    $this->post(route('admin.form-submissions.store'), [...$this->payload, 'website_id' => $other->id])->assertSessionHasErrors('website_id');
    $this->owner->update(['current_website_id' => $other->id]);
    $this->post(route('admin.form-submissions.store'), $this->payload)->assertSessionHasErrors('website_id');
    expect(FormSubmission::count())->toBe(0);
});

it('allows shared managers but not viewers to add leads', function () {
    $manager = User::factory()->create(['current_website_id' => $this->website->id]);
    $viewer = User::factory()->create(['current_website_id' => $this->website->id]);
    $this->website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($manager)->post(route('admin.form-submissions.store'), $this->payload)->assertSessionDoesntHaveErrors();
    $this->actingAs($viewer)->get(route('admin.form-submissions.create'))->assertForbidden();
    $this->post(route('admin.form-submissions.store'), $this->payload)->assertForbidden();
    $this->get(route('admin.form-submissions.index'))->assertSuccessful()->assertDontSee('Add lead');
    expect(FormSubmission::count())->toBe(1);
});

it('allows contact corrections on manual leads while preserving notes and tags workflows', function () {
    $lead = FormSubmission::factory()->manual()->for($this->website)->create(['data' => ['name' => 'Original', 'email' => 'old@example.com'], 'notes' => 'Keep these notes']);
    $this->put(route('admin.form-submissions.update', $lead), ['status' => 'qualified', 'name' => 'Updated', 'email' => 'new@example.com', 'phone' => '0123456789', 'new_tag' => 'Referral'])->assertSessionDoesntHaveErrors();
    expect($lead->fresh()->data['email'])->toBe('new@example.com')->and($lead->fresh()->notes)->toBe('Keep these notes')
        ->and($lead->tags()->sole()->name)->toBe('Referral')->and($lead->activities()->where('type', 'contact_details_updated')->count())->toBe(1);
    $this->post(route('admin.form-submissions.resend-notification', $lead))->assertUnprocessable();
    Mail::assertNothingOutgoing();
});

it('does not rewrite original contact form payloads through manual contact fields', function () {
    $lead = FormSubmission::factory()->for($this->website)->create();
    $original = $lead->data;
    $this->put(route('admin.form-submissions.update', $lead), ['status' => 'new', 'name' => 'Replacement'])->assertSessionHasErrors('name');
    expect($lead->fresh()->data)->toBe($original)->and($lead->fresh()->is_manual)->toBeFalse();
});

it('supports review invitations for manually recorded completed work', function () {
    $this->website->update(['review_url' => 'https://example.com/review']);
    $this->post(route('admin.form-submissions.store'), [...$this->payload, 'status' => 'work_completed'])->assertSessionDoesntHaveErrors();
    $lead = FormSubmission::sole();
    $reviews = app(ReviewInvitationService::class);
    $this->post(route('admin.form-submissions.review-invitations.store', $lead), ['preview_hash' => $reviews->fingerprint($reviews->snapshot($lead))])->assertSessionDoesntHaveErrors();
    expect($lead->reviewInvitation()->sole()->recipient)->toBe('customer@example.com');
});
