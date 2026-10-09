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

it('allows an admin to add and remove a standard website user', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['name' => 'Shared client website']);
    $this->actingAs($admin)->post(route('admin.websites.members.store', $website), [
        'email' => 'new.member@example.com',
    ])->assertRedirect();
    $member = User::query()->where('email', 'new.member@example.com')->sole();
    expect($website->membershipRoleFor($member))->toBe(Website::MEMBER_ROLE_VIEWER);
    Notification::assertSentTo($member, WebsiteInvitation::class);
    $this->delete(route('admin.websites.members.destroy', [$website, $member]))->assertRedirect();
    expect($website->members()->whereKey($member->id)->exists())->toBeFalse();
});

it('keeps the only website manager from being demoted or removed', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $legacyOwner = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $legacyOwner->id]);
    $website->members()->attach($legacyOwner, ['role' => Website::MEMBER_ROLE_MANAGER]);

    $this->actingAs($admin)
        ->get(route('admin.websites.show', [$website, 'tab' => 'settings']))
        ->assertSuccessful()
        ->assertDontSee('Assign owner')
        ->assertDontSee('>Owner<', false)
        ->assertDontSee('id="member_role_'.$legacyOwner->id.'"', false)
        ->assertDontSee('Save membership');

    $this->put(route('admin.websites.members.update', [$website, $legacyOwner]), [
        'role' => Website::MEMBER_ROLE_VIEWER,
    ])->assertSessionHasErrors('role');

    expect($website->membershipRoleFor($legacyOwner))->toBe(Website::MEMBER_ROLE_MANAGER)
        ->and($website->isManageableBy($legacyOwner))->toBeTrue();

    $this->delete(route('admin.websites.members.destroy', [$website, $legacyOwner]))
        ->assertSessionHasErrors('role');

    $website->refresh();

    expect($website->user_id)->toBe($legacyOwner->id)
        ->and($website->isAccessibleBy($legacyOwner))->toBeTrue();
});

it('allows a website manager to rename the website without changing admin settings', function (): void {
    $manager = User::factory()->create();
    $viewer = User::factory()->create();
    $website = Website::factory()->for($manager, 'owner')->create([
        'name' => 'Old website name',
        'health_reports_enabled' => true,
    ]);
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);

    $this->actingAs($manager)
        ->get(route('admin.websites.section', [$website, 'settings']))
        ->assertSuccessful()
        ->assertSee('Website name')
        ->assertSee('Save name')
        ->assertDontSee('Advanced website settings')
        ->assertDontSee('Website status')
        ->assertDontSee('>Domains<', false);

    $this->actingAs($manager)
        ->put(route('admin.websites.update', $website), [
            'name' => 'Clear website name',
            'health_reports_enabled' => false,
            'pixel_enabled' => false,
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('status', 'Website name updated.');

    expect($website->fresh())
        ->name->toBe('Clear website name')
        ->health_reports_enabled->toBeTrue()
        ->pixel_enabled->toBeTrue();

    $this->actingAs($viewer)
        ->put(route('admin.websites.update', $website), ['name' => 'Viewer edit'])
        ->assertForbidden();

    expect($website->fresh()->name)->toBe('Clear website name');
});

it('allows an owner to leave after another manager has been added', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $owner = User::factory()->create();
    $replacementManager = User::factory()->create();
    $website = Website::factory()->create(['user_id' => $owner->id]);
    $website->members()->attach($replacementManager, ['role' => Website::MEMBER_ROLE_MANAGER]);

    $this->actingAs($admin)
        ->delete(route('admin.websites.members.destroy', [$website, $owner]))
        ->assertSessionDoesntHaveErrors();

    $website->refresh();

    expect($website->user_id)->toBe($replacementManager->id)
        ->and($website->isAccessibleBy($owner))->toBeFalse();
});

it('keeps customer managers from changing website access', function (): void {
    Notification::fake();
    $manager = User::factory()->create();
    $website = Website::factory()->for($manager, 'owner')->create();
    $this->actingAs($manager)->post(route('admin.websites.members.store', $website), [
        'email' => 'viewer@example.com',
    ])->assertForbidden();
    expect($website->members()->where('email', 'viewer@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
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

it('does not let customers invite users even with a Growth package', function (): void {
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
        ->assertForbidden();

    expect(User::query()->where('email', 'blocked@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('does not expose unrelated users in the website invitation form', function (): void {
    $owner = User::factory()->create(['role' => User::ROLE_ADMIN, 'membership_tier' => MembershipPlan::GROWTH]);
    $website = Website::factory()->for($owner, 'owner')->create();
    $unrelated = User::factory()->create(['email' => 'private.user@example.com']);

    $this->actingAs($owner)->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Add user')
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
