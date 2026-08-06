<?php

use App\Mail\FormSubmissionReceived;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Mail::fake();
    Http::fake();
});

it('auto-registers a website and form and stores the submission', function (): void {
    $response = $this->withHeader('Origin', 'https://example.com')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Hello from Laravel',
        ]);

    $response->assertRedirectContains('/submitted');
    $this->assertDatabaseHas('websites', ['name' => 'example.com', 'auto_discovered' => true]);
    $this->assertDatabaseHas('forms', ['name' => 'Contact form', 'slug' => 'contact-form']);
    $this->assertDatabaseHas('form_submissions', ['source_domain' => 'example.com']);
    Mail::assertNothingSent();
});

it('allows public submissions without a CSRF token', function (): void {
    $response = $this->withHeader('Origin', 'https://example.com')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'Grace Hopper',
        ]);

    $response->assertStatus(302);
});

it('escapes submitted HTML exactly once when rendering the email', function (): void {
    $message = "Hello, I'd like a <strong>website</strong>.";

    $this->withHeader('Origin', 'https://example.com')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'Grace Hopper',
            'message' => $message,
        ])
        ->assertRedirectContains('/submitted');

    $submission = FormSubmission::query()->latest('id')->firstOrFail();

    expect($submission->data['message'])->toBe($message);

    (new FormSubmissionReceived($submission))
        ->assertSeeInHtml($message)
        ->assertDontSeeInHtml('&amp;#039;', false)
        ->assertDontSeeInHtml('<strong>website</strong>', false);
});

it('rejects unknown websites when auto discovery is disabled', function (): void {
    config()->set('forms.auto_register_websites', false);

    $response = $this->withHeader('Origin', 'https://unknown.example')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'Grace Hopper',
        ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('websites', 0);
    $this->assertDatabaseCount('form_submissions', 0);
});

it('detects honeypot spam without sending notifications', function (): void {
    $response = $this->withHeader('Origin', 'https://example.com')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            '_honeypot' => 'filled',
            'name' => 'Linus',
        ]);

    $response->assertRedirectContains('/submitted');
    $this->assertDatabaseHas('form_submissions', ['is_spam' => true]);
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('quarantines link-heavy submissions without sending notifications', function (): void {
    $this->withHeader('Origin', 'https://spam-check.example')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'DonaldJussy',
            'email' => 'antonkitahi@mail.ru',
            'message' => 'Products https://spam.example/one https://spam.example/two https://spam.example/three',
        ])
        ->assertRedirectContains('/submitted');

    $submission = FormSubmission::query()->latest('id')->firstOrFail();

    expect($submission->is_spam)->toBeTrue();
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('quarantines promotional submissions with a link and invalid email', function (): void {
    $this->withHeader('Origin', 'https://promotion-check.example')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'Reva',
            'email' => 'Reva Braman',
            'message' => 'Order yours now at 50% OFF with FREE Shipping: http://bangeshop.example',
        ])
        ->assertRedirectContains('/submitted');

    expect(FormSubmission::query()->latest('id')->firstOrFail()->is_spam)->toBeTrue();
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('does not flag a legitimate message containing one link', function (): void {
    $this->withHeader('Origin', 'https://legitimate-check.example')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Could you quote for the project described at https://example.com/brief?',
        ])
        ->assertRedirectContains('/submitted');

    expect(FormSubmission::query()->latest('id')->firstOrFail()->is_spam)->toBeFalse();
});

it('rate limits repeated submissions from the same domain and IP address', function (): void {
    config()->set('forms.rate_limit_per_minute', 2);

    $request = fn (): TestResponse => $this->withHeader('Origin', 'https://rate-limit.example')
        ->postJson('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'Grace Hopper',
        ]);

    $request()->assertSuccessful();
    $request()->assertSuccessful();
    $request()->assertTooManyRequests();

    $this->assertDatabaseCount('form_submissions', 2);
});
