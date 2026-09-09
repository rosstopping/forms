<?php

use App\Models\User;
use App\Models\Website;
use App\Support\MembershipPlan;

it('lets an administrator use a viewer subscription without granting management access', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $client = User::factory()->create([
        'admin_membership_tier' => MembershipPlan::GROWTH,
        'admin_membership_expires_at' => now()->addMonths(6),
    ]);
    $website = Website::factory()->create(['user_id' => null]);
    $website->members()->attach($client, ['role' => Website::MEMBER_ROLE_VIEWER]);

    $this->actingAs($admin)->get(route('admin.websites.show', $website))
        ->assertOk()->assertSee('Subscription account');
    $this->put(route('admin.websites.update', $website), ['subscription_user_id' => $client->id])
        ->assertSessionHasNoErrors();

    expect($website->fresh()->user_id)->toBe($client->id)
        ->and($website->fresh()->membershipRoleFor($client))->toBe(Website::MEMBER_ROLE_VIEWER)
        ->and($website->fresh()->isManageableBy($client))->toBeFalse()
        ->and($website->fresh()->isManageableBy($admin))->toBeTrue();

    $this->actingAs($client)->get(route('admin.websites.show', $website))
        ->assertOk()->assertViewHas('canUseGrowthFeatures', true)
        ->assertViewHas('canUseCompleteFeatures', false)
        ->assertDontSee('name="subscription_user_id"', false);
    $this->put(route('admin.websites.update', $website), ['name' => 'Not allowed'])->assertForbidden();
    $this->post(route('admin.content-requests.store', $website), ['instructions' => 'Change this site'])->assertForbidden();
});

it('preserves an existing implicit manager when changing the subscription account', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $manager = User::factory()->create();
    $client = User::factory()->create();
    $website = Website::factory()->for($manager, 'owner')->create();
    $website->members()->attach($client, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($admin)->put(route('admin.websites.update', $website), ['subscription_user_id' => $client->id])
        ->assertSessionHasNoErrors();
    expect($website->fresh()->membershipRoleFor($manager))->toBe(Website::MEMBER_ROLE_MANAGER)
        ->and($website->fresh()->membershipRoleFor($client))->toBe(Website::MEMBER_ROLE_VIEWER);
});

it('does not let a manager change the subscription account', function (): void {
    $manager = User::factory()->create();
    $client = User::factory()->create();
    $website = Website::factory()->for($manager, 'owner')->create();
    $website->members()->attach($client, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($manager)->put(route('admin.websites.update', $website), [
        'name' => $website->name, 'subscription_user_id' => $client->id,
    ])->assertSessionHasNoErrors();
    expect($website->fresh()->user_id)->toBe($manager->id);
});

it('rejects subscription accounts that are not website members', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $stranger = User::factory()->create();
    $website = Website::factory()->create(['user_id' => null]);
    $this->actingAs($admin)->put(route('admin.websites.update', $website), ['subscription_user_id' => $stranger->id])
        ->assertSessionHasErrors('subscription_user_id');
    expect($website->fresh()->user_id)->toBeNull();
});

it('saves a six month admin managed trial through the selected final day', function (): void {
    $this->travelTo(now()->setDate(2026, 9, 9)->startOfDay());
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $client = User::factory()->create(['membership_status' => null, 'membership_tier' => null]);
    $this->actingAs($admin)->put(route('admin.users.update', $client), [
        'name' => $client->name, 'email' => $client->email, 'role' => User::ROLE_USER,
        'admin_membership_tier' => MembershipPlan::GROWTH, 'admin_membership_expires_at' => '2027-03-09',
    ])->assertSessionHasNoErrors();
    $client->refresh();
    expect($client->admin_membership_expires_at->format('Y-m-d H:i:s'))->toBe('2027-03-09 23:59:59')
        ->and($client->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeTrue();
    $this->get(route('admin.users.edit', $client))->assertSee('value="2027-03-09"', false);
    $this->actingAs($client)->get(route('admin.billing.index'))
        ->assertOk()->assertSee('Admin-managed access ends 9 March 2027');
    $this->travelTo($client->admin_membership_expires_at->copy()->subSecond());
    expect($client->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeTrue();
    $this->travelTo($client->admin_membership_expires_at->copy()->addSecond());
    expect($client->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeFalse();
});

it('expires Growth consistently in the website view and protected routes', function (): void {
    $client = User::factory()->create([
        'admin_membership_tier' => MembershipPlan::GROWTH,
        'admin_membership_expires_at' => now()->subDay(),
        'membership_status' => null, 'membership_tier' => null,
    ]);
    $website = Website::factory()->for($client, 'owner')->create();
    $website->members()->attach($client, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($client)->get(route('admin.websites.show', $website))
        ->assertOk()->assertViewHas('canUseGrowthFeatures', false);
    $this->post(route('admin.content-requests.store', $website), ['instructions' => 'Change this site'])
        ->assertRedirect(route('admin.billing.index'));
});

it('falls back to a paid subscription after an admin managed trial expires', function (): void {
    $client = User::factory()->create([
        'admin_membership_tier' => MembershipPlan::GROWTH,
        'admin_membership_expires_at' => now()->subDay(),
        'membership_tier' => MembershipPlan::ESSENTIAL,
        'membership_status' => 'active',
    ]);
    expect($client->hasActiveMembership())->toBeTrue()
        ->and($client->effectiveMembershipTier())->toBe(MembershipPlan::ESSENTIAL)
        ->and($client->hasMembershipFeature(MembershipPlan::FEATURE_HEALTH_REPORTS))->toBeTrue()
        ->and($client->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeFalse();
});

it('keeps undated admin managed access and clears expiry when removing the override', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $client = User::factory()->create(['admin_membership_tier' => MembershipPlan::GROWTH]);
    expect($client->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeTrue();
    $client->update(['admin_membership_expires_at' => now()->addMonth()]);
    $this->actingAs($admin)->put(route('admin.users.update', $client), [
        'name' => $client->name, 'email' => $client->email, 'role' => User::ROLE_USER,
        'admin_membership_tier' => '',
    ])->assertSessionHasNoErrors();
    expect($client->fresh()->admin_membership_expires_at)->toBeNull();
});
