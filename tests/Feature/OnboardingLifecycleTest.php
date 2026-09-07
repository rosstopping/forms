<?php

use App\Enums\OnboardingLifecycleStep;
use App\Listeners\MarkOnboardingLifecycleMessageSent;
use App\Models\OnboardingLifecycleMessage;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAudit;
use App\Notifications\OnboardingLifecycleNotification;
use App\Services\OnboardingLifecycleManager;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function lifecycleUser(array $userAttributes = []): User
{
    $user = User::factory()->create([
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(14),
        'membership_tier' => 'growth',
        'membership_status' => 'trialing',
        'membership_current_period_end' => now()->addDays(14),
        ...$userAttributes,
    ]);
    $website = Website::factory()->for($user, 'owner')->create();
    WebsiteAudit::factory()->for($user)->for($website)->create([
        'claimed_at' => now(),
        'status' => WebsiteAudit::STATUS_COMPLETED,
    ]);

    return $user->fresh(['onboardingAudit.website.searchConsoleConnection']);
}

it('schedules the complete lifecycle and immediately queues the welcome message', function (): void {
    Notification::fake();
    $user = lifecycleUser();

    expect(app(OnboardingLifecycleManager::class)->dispatchDue($user))->toBe(1)
        ->and($user->onboardingLifecycleMessages()->count())->toBe(8)
        ->and($user->onboardingLifecycleMessages()->whereNotNull('queued_at')->sole()->step)->toBe(OnboardingLifecycleStep::Welcome);

    Notification::assertSentTo($user, OnboardingLifecycleNotification::class, fn ($notification): bool => $notification->message->step === OnboardingLifecycleStep::Welcome);

    expect(app(OnboardingLifecycleManager::class)->dispatchDue($user->fresh(['onboardingAudit.website.searchConsoleConnection'])))->toBe(0);
    Notification::assertSentToTimes($user, OnboardingLifecycleNotification::class, 1);
});

it('dispatches due lifecycle steps through the scheduled command', function (): void {
    Notification::fake();
    $this->travelTo(now()->setTime(10, 0));
    $user = lifecycleUser([
        'onboarding_trial_ends_at' => now()->addDays(12),
        'membership_current_period_end' => now()->addDays(12),
    ]);

    $this->artisan('onboarding:dispatch-lifecycle')
        ->expectsOutput('Dispatched 1 onboarding lifecycle message(s).')
        ->assertSuccessful();

    Notification::assertSentTo($user, OnboardingLifecycleNotification::class, fn ($notification): bool => $notification->message->step === OnboardingLifecycleStep::SearchConsole);
});

it('suppresses irrelevant Search Console and call reminders', function (): void {
    Notification::fake();
    $this->travelTo(now()->addDays(4)->setTime(10, 0));
    $user = lifecycleUser([
        'onboarding_trial_ends_at' => now()->addDays(10),
        'membership_current_period_end' => now()->addDays(10),
        'onboarding_call_booked_at' => now(),
    ]);
    $website = $user->onboardingAudit->website;
    SearchConsoleConnection::factory()->for($website)->for($user, 'connector')->create();

    app(OnboardingLifecycleManager::class)->synchronize($user->fresh(['onboardingAudit.website.searchConsoleConnection']));

    $user->onboardingLifecycleMessages()->whereIn('step', [
        OnboardingLifecycleStep::SearchConsole,
        OnboardingLifecycleStep::BookCall,
    ])->update(['scheduled_for' => now()->subMinute(), 'suppressed_at' => null]);

    expect(app(OnboardingLifecycleManager::class)->dispatchDue($user->fresh(['onboardingAudit.website.searchConsoleConnection'])))->toBe(0);

    expect($user->onboardingLifecycleMessages()->whereIn('step', [
        OnboardingLifecycleStep::SearchConsole,
        OnboardingLifecycleStep::BookCall,
    ])->whereNotNull('suppressed_at')->count())->toBe(2);
    Notification::assertNothingSent();
});

it('does not flood an existing trial with lifecycle messages missed before deployment', function (): void {
    Notification::fake();
    $user = lifecycleUser([
        'onboarding_trial_ends_at' => now()->addDays(4),
        'membership_current_period_end' => now()->addDays(4),
    ]);

    app(OnboardingLifecycleManager::class)->dispatchDue($user);

    expect($user->onboardingLifecycleMessages()->whereNotNull('suppressed_at')->count())->toBe(4);
    Notification::assertSentTo($user, OnboardingLifecycleNotification::class, fn ($notification): bool => $notification->message->step === OnboardingLifecycleStep::GrowthFeatures);
});

it('queues an internal follow-up for admins three days after an unconverted trial', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = lifecycleUser([
        'onboarding_trial_ends_at' => now()->subDays(3)->setTime(9, 0),
        'membership_current_period_end' => now()->subDays(3)->setTime(9, 0),
    ]);

    expect(app(OnboardingLifecycleManager::class)->dispatchDue($user))->toBe(1);

    Notification::assertSentTo($admin, OnboardingLifecycleNotification::class, fn ($notification): bool => $notification->message->step === OnboardingLifecycleStep::AdminFollowUp);
    Notification::assertNotSentTo($user, OnboardingLifecycleNotification::class);
});

it('records lifecycle delivery and signed call-to-action clicks', function (): void {
    $user = lifecycleUser();
    $message = OnboardingLifecycleMessage::factory()->for($user)->create([
        'step' => OnboardingLifecycleStep::Welcome,
        'queued_at' => now(),
    ]);
    $notification = new OnboardingLifecycleNotification($message, $user, 'https://example.test');

    app(MarkOnboardingLifecycleMessageSent::class)->handle(new NotificationSent($user, $notification, 'mail', null));
    expect($message->fresh()->sent_at)->not->toBeNull();

    $url = URL::temporarySignedRoute('onboarding-lifecycle.click', now()->addHour(), [
        'onboardingLifecycleMessage' => $message,
    ]);

    $this->get($url)
        ->assertRedirect(route('admin.dashboard'));

    expect($message->fresh()->clicked_at)->not->toBeNull();
});

it('suppresses remaining messages after a paid conversion', function (): void {
    Notification::fake();
    $user = lifecycleUser(['membership_status' => 'active']);
    app(OnboardingLifecycleManager::class)->synchronize($user);
    $user->onboardingLifecycleMessages()->update(['scheduled_for' => now()->subMinute(), 'suppressed_at' => null]);

    expect(app(OnboardingLifecycleManager::class)->dispatchDue($user->fresh(['onboardingAudit.website.searchConsoleConnection'])))->toBe(0)
        ->and($user->onboardingLifecycleMessages()->whereNotNull('suppressed_at')->count())->toBe(8);
    Notification::assertNothingSent();
});
