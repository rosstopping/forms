<?php

use App\Models\User;
use App\Models\Website;
use App\Support\MembershipPlan;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'memberships.plans.essential.stripe_price_id' => 'price_essential',
        'memberships.plans.growth.stripe_price_id' => 'price_growth',
        'memberships.plans.complete.stripe_price_id' => 'price_complete',
        'services.stripe.secret' => 'sk_test_sitewell',
        'services.stripe.webhook_secret' => 'whsec_sitewell',
        'services.stripe.api_url' => 'https://api.stripe.test/v1',
    ]);
});

it('shows the marketing packages on the account billing page', function (): void {
    $user = User::factory()->create([
        'membership_tier' => MembershipPlan::GROWTH,
        'membership_status' => 'active',
    ]);

    $this->actingAs($user)->get(route('admin.billing.index'))
        ->assertSuccessful()
        ->assertSee('Billing and membership')
        ->assertSee('Essential')
        ->assertSee('£149')
        ->assertSee('Google Search Console performance')
        ->assertSee('£249')
        ->assertSee('Google Business Profile management')
        ->assertSee('£399')
        ->assertSee('Current package');
});

it('starts a Stripe hosted subscription checkout for a selected package', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api.stripe.test/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.test/c/pay/cs_test_123',
        ], 200),
    ]);
    $user = User::factory()->create([
        'membership_tier' => null,
        'membership_status' => null,
        'stripe_customer_id' => null,
    ]);

    $this->actingAs($user)->post(route('admin.billing.checkout'), ['tier' => MembershipPlan::GROWTH])
        ->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_123');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.stripe.test/v1/checkout/sessions'
        && $request['mode'] === 'subscription'
        && $request['line_items[0][price]'] === 'price_growth'
        && $request['client_reference_id'] === (string) $user->id
        && $request['customer_email'] === $user->email);
});

it('opens the Stripe hosted portal for package changes and cancellation', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api.stripe.test/v1/billing_portal/sessions' => Http::response([
            'id' => 'bps_123',
            'url' => 'https://billing.stripe.test/session/bps_123',
        ]),
    ]);
    $user = User::factory()->create(['stripe_customer_id' => 'cus_123']);

    $this->actingAs($user)->post(route('admin.billing.portal'))
        ->assertRedirect('https://billing.stripe.test/session/bps_123');

    Http::assertSent(fn ($request): bool => $request['customer'] === 'cus_123'
        && $request['return_url'] === route('admin.billing.index'));
});

it('synchronizes package and cancellation details from a signed Stripe webhook', function (): void {
    $user = User::factory()->create([
        'stripe_customer_id' => 'cus_123',
        'membership_tier' => MembershipPlan::ESSENTIAL,
    ]);
    $periodEnd = now()->addMonth()->timestamp;
    $cancelAt = now()->addWeeks(2)->timestamp;
    $payload = json_encode([
        'type' => 'customer.subscription.updated',
        'data' => ['object' => [
            'id' => 'sub_123',
            'customer' => 'cus_123',
            'status' => 'active',
            'current_period_end' => $periodEnd,
            'cancel_at' => $cancelAt,
            'items' => ['data' => [['price' => ['id' => 'price_complete']]]],
        ]],
    ], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_sitewell');

    $this->call('POST', route('stripe.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
    ], content: $payload)->assertSuccessful();

    $user->refresh();

    expect($user->stripe_subscription_id)->toBe('sub_123')
        ->and($user->membership_tier)->toBe(MembershipPlan::COMPLETE)
        ->and($user->membership_status)->toBe('active')
        ->and($user->membership_current_period_end?->timestamp)->toBe($periodEnd)
        ->and($user->membership_cancel_at?->timestamp)->toBe($cancelAt);
});

it('rejects Stripe webhooks with an invalid signature', function (): void {
    $this->call('POST', route('stripe.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=invalid',
    ], content: '{}')->assertBadRequest();
});

it('only enables explicitly tiered features at the required package level', function (): void {
    $essential = User::factory()->create(['membership_tier' => MembershipPlan::ESSENTIAL]);
    $growth = User::factory()->create(['membership_tier' => MembershipPlan::GROWTH]);
    $complete = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE]);

    expect($essential->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeFalse()
        ->and($growth->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeTrue()
        ->and($growth->hasMembershipFeature(MembershipPlan::FEATURE_COMPLETE))->toBeFalse()
        ->and($complete->hasMembershipFeature(MembershipPlan::FEATURE_COMPLETE))->toBeTrue();
});

it('blocks a website owner from Growth routes when they have Essential', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::ESSENTIAL]);
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)->get(route('admin.search-console.connect', $website))
        ->assertRedirect(route('admin.billing.index'))
        ->assertSessionHas('error');
});

it('shows only the website feature areas included in the owner package', function (): void {
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::ESSENTIAL]);
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertDontSee('data-tab="search"', false)
        ->assertDontSee('data-tab="seo"', false)
        ->assertDontSee('data-tab="business-profile"', false)
        ->assertSee('data-tab="content"', false)
        ->assertDontSee('Manual content requests')
        ->assertSee('A Growth or Complete membership is required to invite additional website users.')
        ->assertSee('data-tab="forms"', false);
});
