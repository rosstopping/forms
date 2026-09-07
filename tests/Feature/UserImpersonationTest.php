<?php

use App\Models\User;
use Lab404\Impersonate\Services\ImpersonateManager;

it('lets an administrator impersonate a customer and return to their account', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $customer = User::factory()->create(['name' => 'Alex Customer']);

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate.store', $customer))
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($customer);
    expect(app(ImpersonateManager::class)->isImpersonating())->toBeTrue();

    $this->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('You are viewing Sitewell as Alex Customer.')
        ->assertSee('Return to admin');

    $this->from(route('admin.billing.index'))
        ->post(route('admin.billing.checkout'), ['tier' => 'growth'])
        ->assertRedirect(route('admin.billing.index'));

    $this->delete(route('admin.impersonation.destroy'))
        ->assertRedirect(route('admin.users.index'));

    $this->assertAuthenticatedAs($admin);
    expect(app(ImpersonateManager::class)->isImpersonating())->toBeFalse();
});

it('does not let customers impersonate another user', function (): void {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();

    $this->actingAs($customer)
        ->post(route('admin.users.impersonate.store', $otherCustomer))
        ->assertForbidden();

    $this->assertAuthenticatedAs($customer);
});

it('does not let administrators impersonate another administrator', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate.store', $otherAdmin))
        ->assertForbidden();

    $this->assertAuthenticatedAs($admin);
});
