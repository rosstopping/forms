<?php

use App\Models\User;
use App\Models\Website;
use App\Notifications\WebsiteInvitation;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->create(['user_id' => null]);
});

it('shows a simple access list without role or membership controls', function (): void {
    $this->actingAs($this->admin)->get(route('admin.websites.section', [$this->website, 'settings']))
        ->assertSuccessful()->assertSee('Add user')->assertSee('No users attached yet.')
        ->assertDontSee('Managers can make changes')->assertDontSee('Manage membership')
        ->assertDontSee('Subscription account')->assertDontSee('name="complimentary_membership_tier"', false)
        ->assertDontSee('id="member_role"', false);
});

it('adds an existing user with standard customer access and preserves billing', function (): void {
    $customer = User::factory()->create(['stripe_customer_id' => 'cus_unchanged', 'stripe_subscription_id' => 'sub_unchanged']);
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), ['email' => $customer->email])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.websites.section', [$this->website, 'settings']));
    expect($this->website->membershipRoleFor($customer))->toBe(Website::MEMBER_ROLE_VIEWER)
        ->and($this->website->isAccessibleBy($customer))->toBeTrue()
        ->and($this->website->isManageableBy($customer))->toBeFalse()
        ->and($this->website->fresh()->user_id)->toBeNull()
        ->and($customer->fresh()->stripe_customer_id)->toBe('cus_unchanged')
        ->and($customer->fresh()->stripe_subscription_id)->toBe('sub_unchanged');
    Notification::assertSentTo($customer, WebsiteInvitation::class, fn ($notification): bool => ! $notification->requiresSetup);
});

it('creates a new customer with a secure setup invitation and no package grant', function (): void {
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), ['email' => '  NEW.Person@example.com '])
        ->assertSessionHasNoErrors();
    $customer = User::where('email', 'new.person@example.com')->sole();
    expect($customer->role)->toBe(User::ROLE_USER)->and($customer->admin_membership_tier)->toBeNull();
    Notification::assertSentTo($customer, WebsiteInvitation::class, fn ($notification): bool => $notification->requiresSetup);
});

it('preserves existing access when a user is added again', function (): void {
    $customer = User::factory()->create();
    $this->website->members()->attach($customer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), ['email' => $customer->email])
        ->assertSessionHasNoErrors();
    expect($this->website->members()->count())->toBe(1);
});

it('rejects obsolete role and package inputs', function (array $input, string $field): void {
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), ['email' => 'person@example.com', ...$input])
        ->assertSessionHasErrors($field);
    expect($this->website->members()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    [['role' => 'manager'], 'role'],
    [['complimentary_membership_tier' => 'growth'], 'complimentary_membership_tier'],
    [['complimentary_membership_ends_on' => '2027-03-09'], 'complimentary_membership_ends_on'],
]);

it('limits access management to admins including on unassigned packages', function (): void {
    $customer = User::factory()->create();
    $this->website->members()->attach($customer, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $this->actingAs($customer)->post(route('admin.websites.members.store', $this->website), ['email' => 'other@example.com'])->assertForbidden();
    $this->get(route('admin.websites.section', [$this->website, 'settings']))->assertForbidden();
});

it('allows admins to remove a customer without changing the website service', function (): void {
    $customer = User::factory()->create();
    $this->website->update(['service_package' => 'growth', 'service_status' => 'active']);
    $this->website->members()->attach($customer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($this->admin)->delete(route('admin.websites.members.destroy', [$this->website, $customer]))->assertSessionHasNoErrors();
    expect($this->website->isAccessibleBy($customer))->toBeFalse()
        ->and($this->website->fresh()->service_package)->toBe('growth');
});

it('retires the old membership update endpoint', function (): void {
    $customer = User::factory()->create();
    $this->website->members()->attach($customer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($this->admin)->put(route('admin.websites.members.update', [$this->website, $customer]), [])->assertGone();
});
