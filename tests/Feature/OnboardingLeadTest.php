<?php

use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAudit;
use App\Models\WebsiteDomain;

it('gives admins a lead view of users who signed up through Get started', function (): void {
    $this->travelTo('2026-09-07 12:00:00 Europe/London');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $customer = User::factory()->create([
        'name' => 'Alex Onboarding',
        'email' => 'alex@example.test',
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
        'membership_status' => 'trialing',
    ]);
    $website = Website::factory()->for($customer, 'owner')->create(['name' => 'Alex Website']);
    $website->domains()->create([
        'domain' => 'alex-business.test',
        'is_primary' => true,
        'ownership_status' => WebsiteDomain::OWNERSHIP_PENDING,
    ]);
    WebsiteAudit::factory()->for($customer)->for($website)->create([
        'domain' => 'alex-business.test',
        'claimed_at' => now()->subDays(4),
    ]);
    $manualCustomer = User::factory()->create(['name' => 'Manual Customer', 'onboarding_status' => 'trial_active']);
    Website::factory()->for($manualCustomer, 'owner')->create();

    $this->actingAs($admin)
        ->get(route('admin.onboarding.index'))
        ->assertOk()
        ->assertSee('Onboarding leads')
        ->assertSee('Alex Onboarding')
        ->assertSee('alex@example.test')
        ->assertSee('alex-business.test')
        ->assertSee('Day 5 of 14')
        ->assertSee('10 days remaining')
        ->assertSee('Pending')
        ->assertSee('Search Console not connected')
        ->assertSee('Not booked')
        ->assertSee('View as user')
        ->assertDontSee('Manual Customer');
});

it('filters onboarding leads by search verification and call progress', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $verifiedCustomer = User::factory()->create([
        'name' => 'Verified Customer',
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addWeek(),
        'onboarding_call_booked_at' => now(),
    ]);
    $verifiedWebsite = Website::factory()->for($verifiedCustomer, 'owner')->create();
    $verifiedWebsite->domains()->create([
        'domain' => 'verified.test',
        'is_primary' => true,
        'ownership_status' => WebsiteDomain::OWNERSHIP_VERIFIED,
        'verification_method' => 'search_console',
    ]);
    SearchConsoleConnection::factory()->for($verifiedWebsite)->for($verifiedCustomer, 'connector')->create([
        'property_url' => 'sc-domain:verified.test',
    ]);
    WebsiteAudit::factory()->for($verifiedCustomer)->for($verifiedWebsite)->create([
        'domain' => 'verified.test',
        'claimed_at' => now(),
    ]);

    $pendingCustomer = User::factory()->create([
        'name' => 'Pending Customer',
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addWeek(),
    ]);
    $pendingWebsite = Website::factory()->for($pendingCustomer, 'owner')->create();
    $pendingWebsite->domains()->create([
        'domain' => 'pending.test',
        'is_primary' => true,
        'ownership_status' => WebsiteDomain::OWNERSHIP_PENDING,
    ]);
    WebsiteAudit::factory()->for($pendingCustomer)->for($pendingWebsite)->create([
        'domain' => 'pending.test',
        'claimed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.onboarding.index', [
            'search' => 'verified.test',
            'verification' => 'verified',
            'call' => 'booked',
        ]))
        ->assertOk()
        ->assertSee('Verified Customer')
        ->assertSee('Via Search Console')
        ->assertSee('Booked')
        ->assertDontSee('Pending Customer');
});

it('keeps onboarding lead management admin only', function (): void {
    $customer = User::factory()->create();
    Website::factory()->for($customer, 'owner')->create();

    $this->actingAs($customer)
        ->get(route('admin.onboarding.index'))
        ->assertForbidden();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('href="'.route('admin.onboarding.index').'"', false);
});
