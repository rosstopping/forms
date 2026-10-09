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

it('rejects old package grants without creating accounts or sending invitations', function (string $tier): void {
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), [
        'email' => 'client@example.com',
        'complimentary_membership_tier' => $tier,
    ])->assertSessionHasErrors('complimentary_membership_tier');
    expect(User::where('email', 'client@example.com')->exists())->toBeFalse()
        ->and($this->website->fresh()->user_id)->toBeNull();
    Notification::assertNothingSent();
})->with(['essential', 'growth', 'complete', 'existing', 'unlimited']);

it('preserves existing complimentary access billing and sponsor when attaching a customer', function (): void {
    $sponsor = User::factory()->create();
    $this->website->update(['user_id' => $sponsor->id]);
    $client = User::factory()->create([
        'admin_membership_tier' => MembershipPlan::COMPLETE,
        'admin_membership_expires_at' => now()->addYear()->endOfDay(),
        'stripe_subscription_id' => 'sub_unchanged',
    ]);
    $expiry = $client->admin_membership_expires_at->toDateTimeString();
    $this->actingAs($this->admin)->post(route('admin.websites.members.store', $this->website), ['email' => $client->email])
        ->assertSessionHasNoErrors();
    expect($client->fresh()->admin_membership_tier)->toBe(MembershipPlan::COMPLETE)
        ->and($client->fresh()->admin_membership_expires_at->toDateTimeString())->toBe($expiry)
        ->and($client->fresh()->stripe_subscription_id)->toBe('sub_unchanged')
        ->and($this->website->fresh()->user_id)->toBe($sponsor->id);
    Notification::assertSentTo($client, WebsiteInvitation::class);
});

it('does not update existing memberships through website access', function (): void {
    $client = User::factory()->create(['membership_tier' => 'essential', 'stripe_subscription_id' => 'sub_existing']);
    $this->website->members()->attach($client, ['role' => 'viewer']);
    $this->actingAs($this->admin)->put(route('admin.websites.members.update', [$this->website, $client]), [
        'complimentary_membership_tier' => 'growth',
    ])->assertSessionHasErrors('complimentary_membership_tier');
    expect($client->fresh()->membership_tier)->toBe('essential')
        ->and($client->fresh()->admin_membership_tier)->toBeNull()
        ->and($client->fresh()->stripe_subscription_id)->toBe('sub_existing')
        ->and($this->website->fresh()->user_id)->toBeNull();
    Notification::assertNothingSent();
});

it('does not allow changing a legacy role through website access', function (): void {
    $client = User::factory()->create();
    $this->website->members()->attach($client, ['role' => 'viewer']);
    $this->actingAs($this->admin)->put(route('admin.websites.members.update', [$this->website, $client]), ['role' => 'manager'])
        ->assertSessionHasErrors('role');
    expect($this->website->membershipRoleFor($client))->toBe('viewer');
});

it('prevents customer managers from granting packages or changing access', function (): void {
    $manager = User::factory()->create();
    $this->website->update(['user_id' => $manager->id]);
    $client = User::factory()->create();
    $this->website->members()->attach($client, ['role' => 'viewer']);
    $this->actingAs($manager)->post(route('admin.websites.members.store', $this->website), ['email' => $client->email])->assertForbidden();
    $this->put(route('admin.websites.members.update', [$this->website, $client]), ['complimentary_membership_tier' => 'complete'])->assertForbidden();
    expect($client->fresh()->admin_membership_tier)->toBeNull();
    Notification::assertNothingSent();
});

it('shows no account package controls in website access for any account', function (): void {
    $manager = User::factory()->create();
    $this->website->update(['user_id' => $manager->id]);
    $this->actingAs($this->admin)->get(route('admin.websites.section', [$this->website, 'settings']))
        ->assertOk()->assertSee('Add user')->assertDontSee('Manage membership')
        ->assertDontSee('name="complimentary_membership_tier"', false);
    $this->actingAs($manager)->get(route('admin.websites.section', [$this->website, 'settings']))
        ->assertOk()->assertDontSee('name="complimentary_membership_tier"', false);
});
