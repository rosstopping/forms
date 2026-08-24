<?php

use App\Models\Form;
use App\Models\User;
use App\Models\Website;
use App\Support\MembershipPlan;
use Illuminate\Support\Facades\Hash;

it('allows an administrator to edit a user and optionally change their password', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create([
        'name' => 'Original name',
        'email' => 'original@example.com',
        'role' => User::ROLE_USER,
        'password' => 'original-password',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $user))
        ->assertSuccessful()
        ->assertSee('Edit user')
        ->assertSee('original@example.com');

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated name',
            'email' => 'updated@example.com',
            'role' => User::ROLE_ADMIN,
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->name)->toBe('Updated name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->role)->toBe(User::ROLE_ADMIN)
        ->and(Hash::check('replacement-password', $user->password))->toBeTrue();
});

it('keeps a users password when the password fields are left blank', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create(['password' => 'original-password']);
    $originalPassword = $user->password;

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'New display name',
            'email' => $user->email,
            'role' => $user->role,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertSessionDoesntHaveErrors();

    expect($user->fresh()->password)->toBe($originalPassword);
});

it('allows an administrator to create a user with a managed membership', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertSuccessful()
        ->assertSee('Admin-managed membership')
        ->assertSee('Complete');

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Managed member',
            'email' => 'managed@example.com',
            'password' => 'managed-password',
            'password_confirmation' => 'managed-password',
            'role' => User::ROLE_USER,
            'admin_membership_tier' => MembershipPlan::COMPLETE,
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'managed@example.com')->sole();

    expect($user->admin_membership_tier)->toBe(MembershipPlan::COMPLETE)
        ->and($user->hasMembershipFeature(MembershipPlan::FEATURE_COMPLETE))->toBeTrue();
});

it('allows an administrator to grant and remove a membership without Stripe', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create([
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'membership_tier' => null,
        'membership_status' => null,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => User::ROLE_USER,
            'admin_membership_tier' => MembershipPlan::GROWTH,
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->admin_membership_tier)->toBe(MembershipPlan::GROWTH)
        ->and($user->stripe_customer_id)->toBeNull()
        ->and($user->stripe_subscription_id)->toBeNull()
        ->and($user->membership_tier)->toBeNull()
        ->and($user->membership_status)->toBeNull()
        ->and($user->effectiveMembershipTier())->toBe(MembershipPlan::GROWTH)
        ->and($user->hasActiveMembership())->toBeTrue()
        ->and($user->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeTrue()
        ->and($user->hasMembershipFeature(MembershipPlan::FEATURE_COMPLETE))->toBeFalse();

    $website = Website::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->post(route('admin.content-requests.store', $website), [
            'instructions' => 'Create a service page.',
        ])
        ->assertRedirect(route('admin.websites.show', $website));

    expect($website->contentRequests()->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('admin.billing.index'))
        ->assertSuccessful()
        ->assertSee('Growth')
        ->assertSee('admin managed');

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => User::ROLE_USER,
            'admin_membership_tier' => '',
        ])
        ->assertSessionDoesntHaveErrors();

    $user->refresh();

    expect($user->admin_membership_tier)->toBeNull()
        ->and($user->hasActiveMembership())->toBeFalse()
        ->and($user->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeFalse();
});

it('prevents normal users from editing user accounts', function (): void {
    $normalUser = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($normalUser)
        ->get(route('admin.users.edit', $otherUser))
        ->assertForbidden();

    $this->actingAs($normalUser)
        ->put(route('admin.users.update', $otherUser), [
            'name' => 'Unauthorised change',
            'email' => $otherUser->email,
            'role' => User::ROLE_USER,
        ])
        ->assertForbidden();
});

it('allows an administrator to delete a user without owned websites', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status', 'User deleted.');

    $this->assertModelMissing($user);
});

it('does not delete the current administrator or a website owner', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $owner = User::factory()->create();
    Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $admin))
        ->assertSessionHasErrors('user');

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $owner))
        ->assertSessionHasErrors('user');

    $this->assertModelExists($admin);
    $this->assertModelExists($owner);
});

it('allows an administrator to rename and delete a website', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['name' => 'Original website']);
    $form = Form::factory()->for($website)->create();

    $this->actingAs($admin)
        ->put(route('admin.websites.update', $website), [
            'name' => 'Renamed website',
            'user_id' => $website->user_id,
            'health_reports_enabled' => false,
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('admin.websites.show', $website));

    expect($website->fresh()->name)->toBe('Renamed website');

    $this->actingAs($admin)
        ->delete(route('admin.websites.destroy', $website))
        ->assertRedirect(route('admin.websites.index'));

    $this->assertModelMissing($website);
    $this->assertModelMissing($form);
});

it('allows an administrator to configure website webhook settings', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Send submissions to a webhook')
        ->assertSee('Webhook URL')
        ->assertSee('Webhook secret');

    $this->actingAs($admin)
        ->put(route('admin.websites.update', $website), [
            'name' => $website->name,
            'user_id' => $website->user_id,
            'health_reports_enabled' => false,
            'webhook_enabled' => true,
            'webhook_url' => 'https://hooks.example.com/submissions',
            'webhook_secret' => 'signing-secret',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('admin.websites.show', $website));

    expect($website->fresh())
        ->webhook_enabled->toBeTrue()
        ->webhook_url->toBe('https://hooks.example.com/submissions')
        ->webhook_secret->toBe('signing-secret');

    $this->actingAs($admin)
        ->put(route('admin.websites.update', $website), [
            'name' => 'Renamed without changing webhook settings',
        ])
        ->assertSessionDoesntHaveErrors();

    expect($website->fresh())
        ->webhook_enabled->toBeTrue()
        ->webhook_url->toBe('https://hooks.example.com/submissions')
        ->webhook_secret->toBe('signing-secret');
});

it('prevents website owners from deleting websites', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->delete(route('admin.websites.destroy', $website))
        ->assertForbidden();

    $this->assertModelExists($website);
});
