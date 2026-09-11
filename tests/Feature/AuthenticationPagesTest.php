<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

it('renders the shared auth layout and labelled forms', function (string $path, string $heading): void {
    $this->get($path)->assertSuccessful()
        ->assertSee($heading)
        ->assertSee('well looked after.')
        ->assertSee('name="_token"', false)
        ->assertSee('for="email"', false);
})->with([
    ['/login', 'Good to see you again'],
    ['/forgot-password', 'Forgot your password?'],
    ['/reset-password/example-token?email=member@example.com', 'Choose a new password'],
]);

it('displays failed login feedback and keeps the email without exposing the password', function (): void {
    $this->followingRedirects()->from(route('login'))->post(route('login'), [
        'email' => 'member@example.com',
        'password' => 'incorrect-password',
    ])->assertSee(__('auth.failed'))
        ->assertSee('member@example.com')
        ->assertSee('aria-invalid="true"', false)
        ->assertDontSee('incorrect-password');
});

it('signs in from the redesigned form', function (): void {
    $user = User::factory()->create(['password' => 'valid-password']);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'valid-password'])
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

it('renders signed invitations and shows the setup confirmation on sign in', function (): void {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('website-invitations.accept', now()->addHour(), ['user' => $user]);

    $this->get($url)->assertSuccessful()->assertSee('Make yourself at home')->assertSee($user->email);
    $this->put($url, [
        'name' => 'Invited member',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'));
    $this->get(route('login'))->assertSee('Your account is ready. You can now sign in.');
    $this->get($url)->assertGone();
});

it('rejects unsigned and expired invitations', function (): void {
    $user = User::factory()->unverified()->create();
    $this->get(route('website-invitations.accept', $user))->assertForbidden();
    $this->get(URL::temporarySignedRoute('website-invitations.accept', now()->subMinute(), ['user' => $user]))->assertForbidden();
});

it('sends a working password reset link without revealing whether an account exists', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.request'))->assertSessionHasNoErrors();
    $status = session('status');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $this->get($notification->toMail($user)->actionUrl)->assertSuccessful()
            ->assertSee($user->email)->assertSee($notification->token);

        return true;
    });

    $this->post(route('password.email'), ['email' => 'unknown@example.com'])
        ->assertSessionHas('status', $status)->assertSessionHasNoErrors();
    Notification::assertCount(1);
});

it('resets a password once and rotates the remember token', function (): void {
    $user = User::factory()->create(['password' => 'old-password']);
    $rememberToken = $user->remember_token;
    $token = Password::createToken($user);
    Event::fake([PasswordReset::class]);
    $data = ['token' => $token, 'email' => $user->email, 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'];

    $this->post(route('password.update'), $data)->assertRedirect(route('login'))->assertSessionHasNoErrors();
    expect(Hash::check('replacement-password', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe($rememberToken);
    Event::assertDispatched(PasswordReset::class);
    $this->get(route('login'))->assertSee(__('passwords.reset'));
    $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
});

it('rejects invalid expired and mismatched reset credentials', function (string $scenario): void {
    $user = User::factory()->create(['password' => 'original-password']);
    $token = Password::createToken($user);
    if ($scenario === 'expired') {
        $this->travel(61)->minutes();
    }
    $this->post(route('password.update'), [
        'token' => $scenario === 'invalid' ? 'invalid-token' : $token,
        'email' => $user->email,
        'password' => 'replacement-password',
        'password_confirmation' => $scenario === 'mismatched' ? 'different-password' : 'replacement-password',
    ])->assertSessionHasErrors($scenario === 'mismatched' ? 'password' : 'email');
    expect(Hash::check('original-password', $user->fresh()->password))->toBeTrue();
})->with(['invalid', 'expired', 'mismatched']);

it('validates reset email input and throttles repeated requests', function (): void {
    Notification::fake();
    $this->post(route('password.email'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->post(route('password.email'), ['email' => 'unknown@example.com'])->assertRedirect();
    }
    $this->post(route('password.email'), ['email' => 'unknown@example.com'])->assertTooManyRequests();
});
