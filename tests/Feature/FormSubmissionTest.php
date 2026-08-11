<?php

use App\Mail\FormSubmissionAcknowledgement;
use App\Mail\FormSubmissionReceived;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Website;
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

it('quarantines submissions with an explicitly blank message', function (): void {
    $this->withHeader('Origin', 'https://blank-message.example')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'MarcuspaX',
            'company' => 'google',
            'email' => 'zuyev-vadik@bk.ru',
            'phone' => '86798813878',
            'message' => '',
            'submit' => '',
        ])
        ->assertRedirectContains('/submitted');

    expect(FormSubmission::query()->latest('id')->firstOrFail()->is_spam)->toBeTrue();
    Mail::assertNothingSent();
});

it('allows callback forms that do not include a message field', function (): void {
    $this->withHeader('Origin', 'https://callback-form.example')
        ->post('/submit', [
            '_form_name' => 'Callback form',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '01234567890',
        ])
        ->assertRedirectContains('/submitted');

    expect(FormSubmission::query()->latest('id')->firstOrFail()->is_spam)->toBeFalse();
});

it('sends the configured website acknowledgement to a genuine lead', function (): void {
    $website = Website::factory()->create([
        'name' => 'Acme Studio',
        'autoresponder_enabled' => true,
        'autoresponder_subject' => 'Thanks {name}',
        'autoresponder_body' => 'Hello {name}, we received your {form_name} enquiry.',
    ]);
    $website->domains()->create(['domain' => 'autoresponder.example', 'is_primary' => true]);
    Form::factory()->create([
        'website_id' => $website->id,
        'name' => 'Contact form',
        'slug' => 'contact-form',
        'email_enabled_override' => false,
        'autoresponder_enabled_override' => null,
    ]);

    $this->withHeader('Origin', 'https://autoresponder.example')->post('/submit', [
        '_form_name' => 'Contact form',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => 'Please call me about a project.',
    ])->assertRedirectContains('/submitted');

    Mail::assertSent(FormSubmissionAcknowledgement::class, fn (FormSubmissionAcknowledgement $mail): bool => $mail->hasTo('ada@example.com') && $mail->emailSubject === 'Thanks Ada Lovelace');
    expect(FormSubmission::query()->latest('id')->firstOrFail()->autoresponder_sent_at)->not->toBeNull();
});

it('renders an acknowledgement without Sitewell branding', function (): void {
    $submission = FormSubmission::factory()->create();
    $message = new FormSubmissionAcknowledgement(
        $submission,
        'We received your enquiry',
        "Hello Ada,\n\nWe will be in touch soon.",
    );

    $message->assertSeeInHtml('Hello Ada,')
        ->assertSeeInHtml('We will be in touch soon.')
        ->assertDontSeeInHtml('Sitewell')
        ->assertSeeInText('Hello Ada,')
        ->assertDontSeeInText('Sitewell');
});

it('allows a form to disable the website acknowledgement', function (): void {
    $website = Website::factory()->create(['autoresponder_enabled' => true]);
    $website->domains()->create(['domain' => 'no-reply.example', 'is_primary' => true]);
    Form::factory()->create(['website_id' => $website->id, 'name' => 'Contact form', 'slug' => 'contact-form', 'email_enabled_override' => false, 'autoresponder_enabled_override' => false]);

    $this->withHeader('Origin', 'https://no-reply.example')->post('/submit', [
        '_form_name' => 'Contact form', 'name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'A genuine enquiry.',
    ]);

    Mail::assertNotSent(FormSubmissionAcknowledgement::class);
});

it('quarantines HTML link injection and known automation markers', function (): void {
    $this->withHeader('Origin', 'https://html-link-spam.example')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'Miguelelata',
            'company' => 'google',
            'email' => 'xrumer23Ter@gmail.com',
            'phone' => '89595152958',
            'message' => '<a href="https://t.me/detivetrachat">https://t.me/detivetrachat</a> кайт хургада',
            'submit' => '',
        ])
        ->assertRedirectContains('/submitted');

    expect(FormSubmission::query()->latest('id')->firstOrFail()->is_spam)->toBeTrue();
    Mail::assertNothingSent();
});

it('quarantines shortened and obfuscated links', function (string $message): void {
    $this->withHeader('Origin', 'https://short-link-spam.example')
        ->post('/submit', [
            '_form_name' => 'Contact form',
            'name' => 'Promotional sender',
            'email' => 'sender@example.com',
            'message' => $message,
        ])
        ->assertRedirectContains('/submitted');

    expect(FormSubmission::query()->latest('id')->firstOrFail()->is_spam)->toBeTrue();
    Mail::assertNothingSent();
})->with([
    'bare shortened link' => 'Start winning here: psee.io/8r5adn',
    'obfuscated shortened link' => 'Remove us by visiting brnd .li/delist',
]);

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
