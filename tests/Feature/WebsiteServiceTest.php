<?php

use App\Models\User;
use App\Models\Website;
use App\Support\MembershipPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('lets admins set a website service without changing billing or enabling work', function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $customer = User::factory()->create([
        'membership_tier' => MembershipPlan::ESSENTIAL,
        'stripe_customer_id' => 'cus_existing',
        'stripe_subscription_id' => 'sub_existing',
    ]);
    $website = Website::factory()->for($customer, 'owner')->create([
        'seo_weekly_snapshots_enabled' => false,
        'health_reports_enabled' => false,
        'weekly_ranking_reports_enabled' => false,
    ]);

    $this->actingAs($admin)->put(route('admin.websites.service.update', $website), [
        'service_package' => MembershipPlan::GROWTH,
        'service_status' => Website::SERVICE_STATUS_ACTIVE,
        'service_ends_on' => '2027-03-09',
        'user_id' => $admin->id,
        'seo_weekly_snapshots_enabled' => true,
    ])->assertRedirect(route('admin.websites.section', [$website, 'settings']))
        ->assertSessionHasNoErrors();

    $website->refresh();
    expect($website->service_package)->toBe(MembershipPlan::GROWTH)
        ->and($website->service_status)->toBe(Website::SERVICE_STATUS_ACTIVE)
        ->and($website->service_ends_at->format('Y-m-d H:i:s'))->toBe('2027-03-09 23:59:59')
        ->and($website->user_id)->toBe($customer->id)
        ->and($website->seo_weekly_snapshots_enabled)->toBeFalse()
        ->and($website->health_reports_enabled)->toBeFalse()
        ->and($website->weekly_ranking_reports_enabled)->toBeFalse()
        ->and($customer->fresh()->membership_tier)->toBe(MembershipPlan::ESSENTIAL)
        ->and($customer->fresh()->stripe_customer_id)->toBe('cus_existing')
        ->and($customer->fresh()->stripe_subscription_id)->toBe('sub_existing');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
    Mail::assertNothingSent();
});

it('keeps service configuration out of customer settings and rejects customer changes', function (): void {
    $customer = User::factory()->create();
    $website = Website::factory()->for($customer, 'owner')->create();

    $this->actingAs($customer)->get(route('admin.websites.section', [$website, 'settings']))
        ->assertSuccessful()->assertDontSee('id="website-service-title"', false);
    $this->put(route('admin.websites.service.update', $website), [
        'service_package' => MembershipPlan::COMPLETE,
        'service_status' => Website::SERVICE_STATUS_ACTIVE,
    ])->assertForbidden();

    expect($website->fresh()->service_package)->toBeNull();
});

it('requires sign in for service changes', function (): void {
    $website = Website::factory()->create();
    $this->put(route('admin.websites.service.update', $website), [
        'service_package' => MembershipPlan::GROWTH,
        'service_status' => Website::SERVICE_STATUS_ACTIVE,
    ])->assertRedirect(route('login'));
});

it('shows the admin service settings including unassigned websites', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['user_id' => null]);
    $this->actingAs($admin)->get(route('admin.websites.section', [$website, 'settings']))
        ->assertSuccessful()->assertSee('Managed service')
        ->assertSee('This website needs a package assigned.')
        ->assertSee('name="service_package"', false);
});

it('validates service settings before saving', function (array $invalid, string $field): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $this->actingAs($admin)->put(route('admin.websites.service.update', $website), array_replace([
        'service_package' => MembershipPlan::GROWTH,
        'service_status' => Website::SERVICE_STATUS_ACTIVE,
    ], $invalid))->assertSessionHasErrors($field);

    expect($website->fresh()->service_package)->toBeNull();
})->with([
    'invalid package' => [['service_package' => 'enterprise'], 'service_package'],
    'missing package' => [['service_package' => null], 'service_package'],
    'invalid status' => [['service_status' => 'trialing'], 'service_status'],
    'invalid end date' => [['service_ends_on' => 'tomorrow'], 'service_ends_on'],
]);

it('lets admins remove a service end date', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create(['service_ends_at' => now()->addMonth()]);
    $this->actingAs($admin)->put(route('admin.websites.service.update', $website), [
        'service_package' => MembershipPlan::COMPLETE,
        'service_status' => Website::SERVICE_STATUS_PAUSED,
        'service_ends_on' => '',
    ])->assertSessionHasNoErrors();

    expect($website->fresh()->service_ends_at)->toBeNull()
        ->and($website->fresh()->hasActiveService())->toBeFalse();
});

it('uses the website package independently of the billing account', function (): void {
    $customer = User::factory()->create(['membership_tier' => MembershipPlan::ESSENTIAL, 'membership_status' => 'canceled']);
    $website = Website::factory()->for($customer, 'owner')->create([
        'service_package' => MembershipPlan::GROWTH,
        'service_status' => Website::SERVICE_STATUS_ACTIVE,
    ]);

    expect($website->hasActiveService())->toBeTrue()
        ->and($website->hasServiceFeature(MembershipPlan::FEATURE_GROWTH))->toBeTrue()
        ->and($website->hasServiceFeature(MembershipPlan::FEATURE_COMPLETE))->toBeFalse();
    $website->update(['service_package' => MembershipPlan::COMPLETE]);
    expect($website->hasServiceFeature(MembershipPlan::FEATURE_COMPLETE))->toBeTrue();
});

it('keeps a dated service active through the selected final day', function (): void {
    $this->travelTo('2026-10-08 12:00:00');
    $website = Website::factory()->create([
        'service_package' => MembershipPlan::GROWTH,
        'service_status' => Website::SERVICE_STATUS_ACTIVE,
        'service_ends_at' => now()->endOfDay(),
    ]);
    $this->travelTo($website->service_ends_at->copy()->subSecond());
    expect($website->hasActiveService())->toBeTrue();
    $this->travelTo($website->service_ends_at->copy()->addSecond());
    expect($website->hasActiveService())->toBeFalse();
});

it('does not deliver an inactive expired or unassigned service', function (array $attributes): void {
    $website = Website::factory()->create(array_replace([
        'service_package' => MembershipPlan::GROWTH,
        'service_status' => Website::SERVICE_STATUS_ACTIVE,
    ], $attributes));

    expect($website->hasActiveService())->toBeFalse()
        ->and($website->hasServiceFeature(MembershipPlan::FEATURE_GROWTH))->toBeFalse();
})->with([
    'inactive website' => [['is_active' => false]],
    'paused service' => [['service_status' => Website::SERVICE_STATUS_PAUSED]],
    'ended service' => [['service_status' => Website::SERVICE_STATUS_ENDED]],
    'unassigned package' => [['service_package' => null]],
    'unassigned status' => [['service_status' => null]],
    'expired service' => [fn (): array => ['service_ends_at' => now()->subSecond()]],
]);

it('backfills recognised packages preserves expiries and leaves ambiguous websites for review', function (): void {
    $overrideOwner = User::factory()->create([
        'admin_membership_tier' => MembershipPlan::GROWTH,
        'admin_membership_expires_at' => now()->addMonths(6)->endOfDay(),
        'stripe_customer_id' => 'cus_preserved',
        'stripe_subscription_id' => 'sub_preserved',
    ]);
    $override = Website::factory()->for($overrideOwner, 'owner')->create();
    $trialOwner = User::factory()->create([
        'membership_status' => 'trialing',
        'membership_current_period_end' => now()->addDays(7),
        'membership_tier' => MembershipPlan::GROWTH,
    ]);
    $trial = Website::factory()->for($trialOwner, 'owner')->create();
    $inactiveOwner = User::factory()->create(['membership_status' => 'canceled']);
    $inactive = Website::factory()->for($inactiveOwner, 'owner')->create();
    $ownerless = Website::factory()->create(['user_id' => null]);
    $unassigned = Website::factory()->for(User::factory()->create(['membership_tier' => null]), 'owner')->create();
    $manual = Website::factory()->create([
        'service_package' => MembershipPlan::ESSENTIAL,
        'service_status' => Website::SERVICE_STATUS_ENDED,
    ]);

    $migration = require database_path('migrations/2026_10_08_153020_backfill_managed_service_packages_for_websites.php');
    $migration->up();

    expect($override->fresh()->service_package)->toBe(MembershipPlan::GROWTH)
        ->and($override->fresh()->service_status)->toBe(Website::SERVICE_STATUS_ACTIVE)
        ->and($override->fresh()->service_ends_at->timestamp)->toBe($overrideOwner->admin_membership_expires_at->timestamp)
        ->and($trial->fresh()->service_ends_at->timestamp)->toBe($trialOwner->membership_current_period_end->timestamp)
        ->and($inactive->fresh()->service_status)->toBe(Website::SERVICE_STATUS_PAUSED)
        ->and($ownerless->fresh()->service_package)->toBeNull()
        ->and($unassigned->fresh()->service_package)->toBeNull()
        ->and($manual->fresh()->service_package)->toBe(MembershipPlan::ESSENTIAL)
        ->and($manual->fresh()->service_status)->toBe(Website::SERVICE_STATUS_ENDED)
        ->and($overrideOwner->fresh()->stripe_customer_id)->toBe('cus_preserved')
        ->and($overrideOwner->fresh()->stripe_subscription_id)->toBe('sub_preserved');

    $override->update(['service_package' => MembershipPlan::ESSENTIAL, 'service_status' => Website::SERVICE_STATUS_PAUSED]);
    $migration->up();
    expect($override->fresh()->service_package)->toBe(MembershipPlan::ESSENTIAL)
        ->and($override->fresh()->service_status)->toBe(Website::SERVICE_STATUS_PAUSED);
});

it('keeps a Stripe subscription update from overwriting the website service', function (): void {
    config(['services.stripe.webhook_secret' => 'whsec_service', 'memberships.plans.complete.stripe_price_id' => 'price_complete']);
    $customer = User::factory()->create(['stripe_customer_id' => 'cus_service']);
    $website = Website::factory()->for($customer, 'owner')->create([
        'service_package' => MembershipPlan::ESSENTIAL,
        'service_status' => Website::SERVICE_STATUS_PAUSED,
    ]);
    $payload = json_encode([
        'type' => 'customer.subscription.updated',
        'data' => ['object' => [
            'id' => 'sub_service', 'customer' => 'cus_service', 'status' => 'active',
            'items' => ['data' => [['price' => ['id' => 'price_complete']]]],
        ]],
    ], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_service');
    $this->call('POST', route('stripe.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
    ], content: $payload)->assertSuccessful();

    expect($customer->fresh()->membership_tier)->toBe(MembershipPlan::COMPLETE)
        ->and($website->fresh()->service_package)->toBe(MembershipPlan::ESSENTIAL)
        ->and($website->fresh()->service_status)->toBe(Website::SERVICE_STATUS_PAUSED);
});

it('backfills expired overrides inactive sites and expired trials safely', function (array $userAttributes, bool $isActive, string $package, string $status): void {
    $owner = User::factory()->create($userAttributes);
    $website = Website::factory()->for($owner, 'owner')->create(['is_active' => $isActive]);
    $migration = require database_path('migrations/2026_10_08_153020_backfill_managed_service_packages_for_websites.php');
    $migration->up();

    expect($website->fresh()->service_package)->toBe($package)
        ->and($website->fresh()->service_status)->toBe($status)
        ->and($website->fresh()->health_reports_enabled)->toBeFalse()
        ->and($website->fresh()->seo_weekly_snapshots_enabled)->toBeFalse();
})->with([
    'expired override falls back to paid package' => [fn (): array => [
        'admin_membership_tier' => MembershipPlan::GROWTH,
        'admin_membership_expires_at' => now()->subDay(),
        'membership_tier' => MembershipPlan::ESSENTIAL,
    ], true, MembershipPlan::ESSENTIAL, Website::SERVICE_STATUS_ACTIVE],
    'inactive website stays paused' => [[], false, MembershipPlan::COMPLETE, Website::SERVICE_STATUS_PAUSED],
    'expired trial stays paused' => [fn (): array => [
        'membership_status' => 'trialing', 'membership_current_period_end' => now()->subDay(),
    ], true, MembershipPlan::COMPLETE, Website::SERVICE_STATUS_PAUSED],
]);
