<?php

use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteDomain;
use App\Models\WebsiteHealthReport;

it('shows a prominent onboarding call and account checklist during the trial', function (): void {
    $user = User::factory()->create([
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
    ]);
    $website = Website::factory()->for($user, 'owner')->create();
    $website->domains()->create([
        'domain' => 'example.test',
        'is_primary' => true,
        'ownership_status' => WebsiteDomain::OWNERSHIP_PENDING,
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Let’s turn your findings into a practical plan')
        ->assertSee('Book your free onboarding call')
        ->assertSee('Verify website ownership')
        ->assertSee('Connect Google Search Console')
        ->assertSee('Review your first health report')
        ->assertSee('0 of 4');
});

it('tracks when a trial user opens the booking calendar', function (): void {
    config(['marketing.booking_url' => 'https://calendar.example.test/sitewell']);
    $user = User::factory()->create([
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
    ]);
    Website::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->get(route('admin.onboarding-call'))
        ->assertRedirect('https://calendar.example.test/sitewell');

    expect($user->fresh()->onboarding_call_booking_started_at)->not->toBeNull();
});

it('does not expose the tracked booking redirect outside onboarding', function (): void {
    $user = User::factory()->create();
    Website::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->get(route('admin.onboarding-call'))
        ->assertNotFound();
});

it('does not expose the booking redirect after the onboarding trial expires', function (): void {
    $user = User::factory()->create([
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->subMinute(),
    ]);
    Website::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->get(route('admin.onboarding-call'))
        ->assertNotFound();
});

it('updates checklist progress from genuine onboarding activity', function (): void {
    $user = User::factory()->create([
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
        'onboarding_call_booked_at' => now(),
        'onboarding_health_report_viewed_at' => now(),
    ]);
    $website = Website::factory()->for($user, 'owner')->create();
    $website->domains()->create(['domain' => 'example.test', 'is_primary' => true]);
    SearchConsoleConnection::factory()->for($website)->for($user, 'connector')->create([
        'property_url' => 'sc-domain:example.test',
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('4 of 4');
});

it('records the first health report viewed during onboarding', function (): void {
    $user = User::factory()->create([
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
    ]);
    $website = Website::factory()->for($user, 'owner')->create();
    $report = WebsiteHealthReport::factory()->for($website)->create();

    $this->actingAs($user)
        ->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertOk();

    expect($user->fresh()->onboarding_health_report_viewed_at)->not->toBeNull();
});

it('lets admins mark onboarding calls as booked and completed', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $customer = User::factory()->create([
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
    ]);
    Website::factory()->for($customer, 'owner')->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Onboarding call')
        ->assertSee('Not booked');

    $this->patch(route('admin.users.onboarding-call.update', $customer), ['status' => 'booked'])
        ->assertRedirect();

    expect($customer->fresh()->onboarding_call_booked_at)->not->toBeNull()
        ->and($customer->onboarding_call_completed_at)->toBeNull();

    $this->patch(route('admin.users.onboarding-call.update', $customer), ['status' => 'completed'])
        ->assertRedirect();

    $customer->refresh();
    expect($customer->onboarding_call_completed_at)->not->toBeNull();

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Your onboarding call is complete')
        ->assertDontSee('href="'.route('admin.onboarding-call').'"', false);
});

it('prevents customers from changing onboarding call status', function (): void {
    $customer = User::factory()->create([
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
    ]);
    $otherCustomer = User::factory()->create();

    $this->actingAs($customer)
        ->patch(route('admin.users.onboarding-call.update', $otherCustomer), ['status' => 'completed'])
        ->assertForbidden();

    expect($otherCustomer->fresh()->onboarding_call_completed_at)->toBeNull();
});
