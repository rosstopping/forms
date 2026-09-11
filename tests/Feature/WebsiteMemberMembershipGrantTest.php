<?php

use App\Models\User;
use App\Models\Website;
use App\Notifications\WebsiteInvitation;
use App\Support\MembershipPlan;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->create(['user_id' => null]);
});

it('invites a viewer with six months of Growth and assigns their subscription in one step', function (): void {
    $endsOn = now()->addMonthsNoOverflow(6)->format('Y-m-d');
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), [
        'email' => 'client@example.com', 'role' => 'viewer',
        'complimentary_membership_tier' => MembershipPlan::GROWTH,
        'complimentary_membership_ends_on' => $endsOn,
    ])->assertSessionHasNoErrors();
    $client = User::where('email', 'client@example.com')->sole();
    expect($client->admin_membership_tier)->toBe(MembershipPlan::GROWTH)
        ->and($client->admin_membership_expires_at->format('Y-m-d H:i:s'))->toBe($endsOn.' 23:59:59')
        ->and($this->website->fresh()->user_id)->toBe($client->id)
        ->and($this->website->membershipRoleFor($client))->toBe('viewer')
        ->and($this->website->isManageableBy($client))->toBeFalse();
    Notification::assertSentTo($client, WebsiteInvitation::class);
    $this->actingAs($client)->get(route('admin.websites.show', $this->website))
        ->assertOk()->assertViewHas('canUseGrowthFeatures', true);
});

it('preserves the subscription and sponsor when no package is selected', function (): void {
    $sponsor = User::factory()->create();
    $this->website->update(['user_id' => $sponsor->id]);
    $client = User::factory()->create([
        'admin_membership_tier' => MembershipPlan::COMPLETE,
        'admin_membership_expires_at' => now()->addYear()->endOfDay(),
        'membership_tier' => MembershipPlan::GROWTH, 'membership_status' => 'active',
        'stripe_subscription_id' => 'sub_unchanged',
    ]);
    $originalExpiry = $client->admin_membership_expires_at->toDateTimeString();
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), [
        'email' => $client->email, 'role' => 'viewer',
        'complimentary_membership_tier' => '',
        'complimentary_membership_ends_on' => now()->addMonths(6)->format('Y-m-d'),
    ])->assertSessionHasNoErrors();
    expect($client->fresh()->admin_membership_tier)->toBe(MembershipPlan::COMPLETE)
        ->and($client->fresh()->admin_membership_expires_at->toDateTimeString())->toBe($originalExpiry)
        ->and($client->fresh()->stripe_subscription_id)->toBe('sub_unchanged')
        ->and($this->website->fresh()->user_id)->toBe($sponsor->id);
});

it('updates an existing viewer membership on the website without resending an invitation', function (): void {
    $manager = User::factory()->create();
    $this->website->update(['user_id' => $manager->id]);
    $client = User::factory()->create(['stripe_subscription_id' => 'sub_existing', 'membership_tier' => 'essential', 'membership_status' => 'active']);
    $this->website->members()->attach($client, ['role' => 'viewer']);
    $this->actingAs($this->admin)->put(route('admin.websites.members.update', [$this->website, $client]), [
        'complimentary_membership_tier' => MembershipPlan::GROWTH,
        'complimentary_membership_ends_on' => '',
    ])->assertSessionHasNoErrors();
    expect($client->fresh()->admin_membership_tier)->toBe('growth')
        ->and($client->fresh()->admin_membership_expires_at)->toBeNull()
        ->and($client->fresh()->stripe_subscription_id)->toBe('sub_existing')
        ->and($client->fresh()->membership_tier)->toBe('essential')
        ->and($this->website->fresh()->user_id)->toBe($client->id)
        ->and($this->website->membershipRoleFor($client))->toBe('viewer')
        ->and($this->website->membershipRoleFor($manager))->toBe('manager');
    Notification::assertNothingSent();
});

it('allows granting membership to a sole manager without changing their role', function (): void {
    $manager = User::factory()->create();
    $this->website->members()->attach($manager, ['role' => 'manager']);
    $this->actingAs($this->admin)->put(route('admin.websites.members.update', [$this->website, $manager]), [
        'complimentary_membership_tier' => 'growth',
    ])->assertSessionHasNoErrors();
    expect($this->website->membershipRoleFor($manager))->toBe('manager');
});

it('rejects invalid grants without creating an account or sending an invitation', function (string $field, string $value): void {
    $data = ['email' => 'client@example.com', 'role' => 'viewer', 'complimentary_membership_tier' => 'growth'];
    $data[$field] = $value;
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), $data)
        ->assertSessionHasErrors($field);
    expect(User::where('email', 'client@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
})->with([
    ['complimentary_membership_tier', 'unlimited'],
    ['complimentary_membership_ends_on', 'not-a-date'],
]);

it('prevents managers from granting packages during invitations or member updates', function (): void {
    $manager = User::factory()->create(['admin_membership_tier' => 'growth']);
    $this->website->update(['user_id' => $manager->id]);
    $client = User::factory()->create();
    $this->website->members()->attach($client, ['role' => 'viewer']);
    $data = ['email' => $client->email, 'role' => 'viewer', 'complimentary_membership_tier' => 'complete', 'complimentary_membership_ends_on' => '2027-03-09'];
    $this->actingAs($manager)->post(route('admin.websites.members.store', $this->website), $data)
        ->assertSessionHasErrors(['complimentary_membership_tier', 'complimentary_membership_ends_on']);
    $this->put(route('admin.websites.members.update', [$this->website, $client]), $data)
        ->assertSessionHasErrors(['complimentary_membership_tier', 'complimentary_membership_ends_on']);
    expect($client->fresh()->admin_membership_tier)->toBeNull()
        ->and($this->website->fresh()->user_id)->toBe($manager->id);
    Notification::assertNothingSent();
});

it('keeps role changes from altering existing complimentary access', function (): void {
    $client = User::factory()->create(['admin_membership_tier' => 'growth']);
    $this->website->members()->attach($client, ['role' => 'viewer']);
    $this->actingAs($this->admin)->put(route('admin.websites.members.update', [$this->website, $client]), ['role' => 'manager'])
        ->assertSessionHasNoErrors();
    expect($client->fresh()->admin_membership_tier)->toBe('growth')
        ->and($this->website->fresh()->user_id)->toBeNull();
});

it('shows membership controls for administrators but not website managers', function (): void {
    $manager = User::factory()->create(['admin_membership_tier' => 'growth']);
    $this->website->update(['user_id' => $manager->id]);
    $this->actingAs($this->admin)->get(route('admin.websites.show', $this->website))
        ->assertOk()->assertSee('Manage membership')
        ->assertSee('name="complimentary_membership_tier"', false)
        ->assertSee('value="'.now()->addMonthsNoOverflow(6)->format('Y-m-d').'"', false);
    $this->actingAs($manager)->get(route('admin.websites.show', $this->website))
        ->assertOk()->assertDontSee('name="complimentary_membership_tier"', false);
});

it('can use an existing paid membership during invitation without overriding it', function (): void {
    $client = User::factory()->create(['membership_tier' => 'growth', 'membership_status' => 'active', 'stripe_subscription_id' => 'sub_paid']);
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), [
        'email' => $client->email, 'role' => 'viewer',
        'complimentary_membership_tier' => 'existing',
        'complimentary_membership_ends_on' => now()->addMonths(6)->format('Y-m-d'),
    ])->assertSessionHasNoErrors();
    expect($this->website->fresh()->user_id)->toBe($client->id)
        ->and($client->fresh()->admin_membership_tier)->toBeNull()
        ->and($client->fresh()->admin_membership_expires_at)->toBeNull()
        ->and($client->fresh()->stripe_subscription_id)->toBe('sub_paid');
});

it('does not create a user when asked to reuse a membership that does not exist', function (): void {
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), [
        'email' => 'new@example.com', 'role' => 'viewer', 'complimentary_membership_tier' => 'existing',
    ])->assertSessionHasErrors('complimentary_membership_tier');
    expect(User::where('email', 'new@example.com')->exists())->toBeFalse()
        ->and($this->website->fresh()->user_id)->toBeNull();
    Notification::assertNothingSent();
});
