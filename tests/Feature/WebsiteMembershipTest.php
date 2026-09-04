<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;
use App\Notifications\WebsiteInvitation;
use App\Support\MembershipPlan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('allows an owner to add update and remove a website member', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id, 'name' => 'Shared client website']);

    $this->actingAs($owner)->post(route('admin.websites.members.store', $website), [
        'email' => 'new.member@example.com',
        'role' => Website::MEMBER_ROLE_VIEWER,
    ])->assertRedirect();

    $member = User::query()->where('email', 'new.member@example.com')->sole();
    expect($website->membershipRoleFor($member))->toBe(Website::MEMBER_ROLE_VIEWER);
    Notification::assertSentTo($member, WebsiteInvitation::class);

    $this->put(route('admin.websites.members.update', [$website, $member]), [
        'role' => Website::MEMBER_ROLE_MANAGER,
    ])->assertRedirect();

    expect($website->membershipRoleFor($member))->toBe(Website::MEMBER_ROLE_MANAGER);

    $this->delete(route('admin.websites.members.destroy', [$website, $member]))->assertRedirect();

    expect($website->members()->whereKey($member->id)->exists())->toBeFalse();
});

it('presents legacy owners as managers who can be changed or removed', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $legacyOwner = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $legacyOwner->id]);

    $this->actingAs($admin)
        ->get(route('admin.websites.show', [$website, 'tab' => 'settings']))
        ->assertSuccessful()
        ->assertDontSee('Assign owner')
        ->assertDontSee('>Owner<', false)
        ->assertSee(route('admin.websites.members.update', [$website, $legacyOwner]))
        ->assertSee(route('admin.websites.members.destroy', [$website, $legacyOwner]));

    $this->put(route('admin.websites.members.update', [$website, $legacyOwner]), [
        'role' => Website::MEMBER_ROLE_VIEWER,
    ])->assertRedirect();

    expect($website->membershipRoleFor($legacyOwner))->toBe(Website::MEMBER_ROLE_VIEWER)
        ->and($website->isManageableBy($legacyOwner))->toBeFalse();

    $this->delete(route('admin.websites.members.destroy', [$website, $legacyOwner]))->assertRedirect();

    $website->refresh();

    expect($website->user_id)->toBeNull()
        ->and($website->isAccessibleBy($legacyOwner))->toBeFalse();
});

it('allows managers to manage website users', function (): void {
    Notification::fake();
    $billingUser = User::factory()->create([
        'admin_membership_tier' => MembershipPlan::GROWTH,
    ]);
    $manager = User::factory()->create();
    $website = Website::factory()->for($billingUser, 'owner')->create();
    $website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);

    $this->actingAs($manager)->post(route('admin.websites.members.store', $website), [
        'email' => 'viewer@example.com',
        'role' => Website::MEMBER_ROLE_VIEWER,
    ])->assertRedirect();

    expect($website->members()->where('email', 'viewer@example.com')->wherePivot('role', Website::MEMBER_ROLE_VIEWER)->exists())->toBeTrue();
});

it('allows managers to work with a shared website and its leads', function (): void {
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id, 'name' => 'Managed website']);
    $website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    $submission = FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id]);

    $this->actingAs($manager)->get(route('admin.websites.index'))->assertOk()->assertSee($website->name);
    $this->get(route('admin.websites.show', $website))->assertOk();
    $this->get(route('admin.form-submissions.index'))
        ->assertOk()
        ->assertSee($submission->displayName())
        ->assertSee('data-bulk-leads-total="1"', false);
    $this->put(route('admin.form-submissions.update', $submission), ['status' => 'contacted'])->assertRedirect();

    expect($submission->refresh()->status)->toBe('contacted');
});

it('gives viewers read only access', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id]);
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $form = Form::factory()->create(['website_id' => $website->id]);
    $submission = FormSubmission::factory()->create(['website_id' => $website->id, 'form_id' => $form->id]);

    $this->actingAs($viewer)->get(route('admin.websites.show', $website))->assertOk();
    $this->get(route('admin.form-submissions.show', $submission))->assertOk();
    $this->put(route('admin.form-submissions.update', $submission), ['status' => 'won'])->assertForbidden();
    $this->put(route('admin.websites.autoresponder.update', $website), [
        'autoresponder_enabled' => true,
    ])->assertForbidden();
    $this->post(route('admin.websites.members.store', $website), [
        'email' => User::factory()->create()->email,
        'role' => Website::MEMBER_ROLE_MANAGER,
    ])->assertForbidden();
});

it('requires an active Growth membership to invite website users', function (): void {
    Notification::fake();
    $owner = User::factory()->create([
        'membership_tier' => MembershipPlan::GROWTH,
        'membership_status' => null,
    ]);
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->post(route('admin.websites.members.store', $website), [
            'email' => 'blocked@example.com',
            'role' => Website::MEMBER_ROLE_VIEWER,
        ])
        ->assertRedirect(route('admin.billing.index'));

    expect(User::query()->where('email', 'blocked@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('does not expose unrelated users in the website invitation form', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::GROWTH]);
    $website = Website::factory()->for($owner, 'owner')->create();
    $unrelated = User::factory()->create(['email' => 'private.user@example.com']);

    $this->actingAs($owner)->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Invite by email')
        ->assertDontSee('Choose a user')
        ->assertDontSee($unrelated->email);
});

it('lets a newly invited website user set up their account once', function (): void {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('website-invitations.accept', now()->addHour(), ['user' => $user]);

    $this->get($url)->assertSuccessful()->assertSee($user->email);

    $this->put($url, [
        'name' => 'Invited Person',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertRedirect(route('login'));

    $user->refresh();
    expect($user->name)->toBe('Invited Person')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secure-password', $user->password))->toBeTrue();

    $this->get($url)->assertGone();
});

it('keeps unrelated websites isolated from members', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $sharedWebsite = Website::factory()->create(['user_id' => $owner->id, 'name' => 'Shared website']);
    $privateWebsite = Website::factory()->create(['name' => 'Private website']);
    $sharedWebsite->members()->attach($member, ['role' => Website::MEMBER_ROLE_MANAGER]);

    $this->actingAs($member)->get(route('admin.websites.index'))
        ->assertOk()
        ->assertSee($sharedWebsite->name)
        ->assertDontSee($privateWebsite->name);

    $this->get(route('admin.websites.show', $privateWebsite))->assertForbidden();
});
