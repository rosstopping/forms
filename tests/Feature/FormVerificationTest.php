<?php

use App\Jobs\SendFormSubmissionAcknowledgement;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Website;
use App\Models\WebsiteDomain;
use App\Services\TurnstileVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
    Http::preventStrayRequests();
    $this->website = Website::factory()->create([
        'turnstile_enabled' => true,
        'turnstile_secret_key' => 'private-secret',
    ]);
    $this->website->domains()->create(['domain' => 'protected.example', 'is_primary' => true]);
    Form::factory()->for($this->website)->create(['name' => 'Contact', 'slug' => 'contact']);
});

it('only trusts verified exact domains and their www alias', function (string $hostname, bool $verified): void {
    $this->website->domains()->create(['domain' => 'pending.example', 'ownership_status' => WebsiteDomain::OWNERSHIP_PENDING]);
    $other = Website::factory()->create();
    $other->domains()->create(['domain' => 'other.example']);
    Http::fake(['*' => Http::response(['success' => true, 'hostname' => $hostname])]);

    $result = app(TurnstileVerifier::class)->verify($this->website, 'private-token', '192.0.2.1');

    expect($result->passed)->toBe($verified)
        ->and($result->reason)->toBe($verified ? 'verified' : 'hostname_mismatch');
})->with([
    'apex' => ['protected.example', true],
    'www' => ['www.protected.example', true],
    'subdomain' => ['shop.protected.example', false],
    'www subdomain' => ['www.shop.protected.example', false],
    'double www' => ['www.www.protected.example', false],
    'lookalike' => ['protected.example.attacker.example', false],
    'other website' => ['other.example', false],
    'pending' => ['pending.example', false],
    'pending www' => ['www.pending.example', false],
    'absent' => ['', false],
    'empty www' => ['www.', false],
]);

it('records verification reasons safely and asks visitors to retry without accepting or quarantining', function (string $failure, string $reason, int $status): void {
    Log::spy();
    $response = match ($failure) {
        'connection' => Http::failedConnection('private-token private-secret 192.0.2.1'),
        'http' => Http::response('private-token', 503),
        'malformed' => Http::response(['unexpected' => 'private-token']),
        'malformed_errors' => Http::response(['success' => false, 'error-codes' => [['private-token']]]),
        'internal' => Http::response(['success' => false, 'error-codes' => ['internal-error']]),
        'secret' => Http::response(['success' => false, 'error-codes' => ['invalid-input-secret']]),
        'expired' => Http::response(['success' => false, 'error-codes' => ['timeout-or-duplicate']]),
        'hostname' => Http::response(['success' => true, 'hostname' => 'attacker.example']),
        default => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
    };
    Http::fake(['*' => $response]);
    if ($failure === 'config') {
        $this->website->update(['turnstile_secret_key' => null]);
    }

    $result = $this->withHeaders(['Origin' => 'https://protected.example', 'Referer' => 'https://protected.example/contact'])
        ->postJson('/submit', [
            '_form_name' => 'Contact',
            '_form_success_url' => 'https://protected.example/thanks',
            'message' => 'Private message contents',
            'email' => 'private@example.com',
            'cf-turnstile-response' => $failure === 'missing' ? '' : 'private-token',
        ])->assertStatus($status)->assertJsonPath('success', false)
        ->assertJsonMissingPath('conversion_event_id');

    if ($status === 503) {
        $result->assertHeader('Retry-After', '30');
    }
    expect(FormSubmission::query()->count())->toBe(0);
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
    Log::shouldHaveReceived('log')->once()->with($status === 503 ? 'warning' : 'info', 'Form verification rejected', [
        'website_id' => $this->website->id,
        'form_id' => $this->website->forms()->sole()->id,
        'reason' => $reason,
    ]);
    Log::shouldNotHaveReceived('error');
    Http::assertSentCount(in_array($failure, ['missing', 'config'], true) ? 0 : 1);
})->with([
    'missing' => ['missing', 'missing_token', 422],
    'invalid' => ['invalid', 'invalid_token', 422],
    'expired or reused' => ['expired', 'expired_or_duplicate_token', 422],
    'wrong host' => ['hostname', 'hostname_mismatch', 422],
    'connection' => ['connection', 'verifier_unavailable', 503],
    'http error' => ['http', 'verifier_unavailable', 503],
    'bad payload' => ['malformed', 'verifier_unavailable', 503],
    'bad error codes' => ['malformed_errors', 'verifier_unavailable', 503],
    'provider error' => ['internal', 'verifier_unavailable', 503],
    'invalid secret' => ['secret', 'verifier_unavailable', 503],
    'missing secret' => ['config', 'configuration_error', 503],
]);

it('shows a readable verification error for ordinary browser submissions', function (): void {
    Http::fake(['*' => Http::response([], 503)]);
    $this->withHeader('Origin', 'https://protected.example')->post('/submit', [
        '_form_name' => 'Contact',
        'cf-turnstile-response' => 'token',
    ])->assertStatus(503)->assertSee('Your enquiry has not been submitted')->assertHeader('Retry-After', '30');
    expect(FormSubmission::query()->count())->toBe(0);
});

it('records content and honeypot quarantine reasons without storing security fields', function (array $payload, string $reason): void {
    Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'www.protected.example'])]);
    $this->withHeader('Origin', 'https://www.protected.example')->postJson('/submit', array_merge([
        '_form_name' => 'Contact',
        'cf-turnstile-response' => 'private-token',
    ], $payload))->assertSuccessful()->assertJsonPath('conversion_event_id', null);

    $submission = FormSubmission::query()->sole();
    expect($submission->is_spam)->toBeTrue()
        ->and($submission->activities()->sole()->metadata['spam_reasons'])->toBe([$reason])
        ->and($submission->data)->not->toHaveKeys(['cf-turnstile-response', '_honeypot']);
    Mail::assertNothingSent();
    Queue::assertNotPushed(SendFormSubmissionAcknowledgement::class);
})->with([
    'content' => [['message' => 'Visit https://spam.example/offer'], 'content'],
    'honeypot' => [['_honeypot' => 'filled'], 'honeypot'],
]);

it('accepts a fresh verified attempt after an outage without replaying the failed request', function (): void {
    Http::fake(['*' => Http::sequence()->push([], 503)->push(['success' => true, 'hostname' => 'www.protected.example'])]);
    $payload = ['_form_name' => 'Contact', 'message' => 'Please provide a quote.'];
    $this->withHeader('Origin', 'https://www.protected.example')->postJson('/submit', $payload + [
        'cf-turnstile-response' => 'first-token',
    ])->assertStatus(503);
    expect(FormSubmission::query()->count())->toBe(0);

    $this->postJson('/submit', $payload + ['cf-turnstile-response' => 'fresh-token'])
        ->assertSuccessful()->assertJsonPath('success', true);
    $submission = FormSubmission::query()->sole();
    expect($submission->is_spam)->toBeFalse()
        ->and($submission->activities()->where('type', 'created')->sole()->metadata)->toBe([
            'spam_reasons' => [],
            'verification_reason' => 'verified',
        ]);
    Http::assertSentCount(2);
});
