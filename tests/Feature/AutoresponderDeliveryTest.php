<?php

use App\Jobs\SendFormSubmissionAcknowledgement;
use App\Mail\FormSubmissionAcknowledgement;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormSubmissionEmailDelivery;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMailConnection;
use App\Services\AutoresponderDeliveryService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    Http::preventStrayRequests();
});

it('stores a customer Postmark server token encrypted', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)->put(route('admin.websites.autoresponder.update', $website), [
        'autoresponder_enabled' => true,
        'autoresponder_from_name' => 'Acme',
        'autoresponder_from_email' => 'hello@acme.example',
        'autoresponder_subject' => 'Thanks',
        'autoresponder_body' => 'We received your enquiry.',
        'autoresponder_content_type' => 'text',
        'autoresponder_delay_minutes' => 0,
        'mail_delivery_mode' => WebsiteMailConnection::MODE_CUSTOMER_POSTMARK,
        'postmark_server_token' => 'customer-server-token',
    ])->assertRedirect();

    $connection = $website->mailConnection()->sole();

    expect($connection->mode)->toBe(WebsiteMailConnection::MODE_CUSTOMER_POSTMARK)
        ->and($connection->postmark_server_token)->toBe('customer-server-token')
        ->and(DB::table((new WebsiteMailConnection)->getTable())->whereKey($connection->id)->value('postmark_server_token'))->not->toBe('customer-server-token');
});

it('sends through a customer Postmark account once and records the delivery', function (): void {
    Http::fake([
        'https://api.postmarkapp.com/email' => Http::response(['MessageID' => 'postmark-message-123']),
    ]);
    $website = Website::factory()->create([
        'autoresponder_from_name' => 'Acme',
        'autoresponder_from_email' => 'hello@acme.example',
    ]);
    $website->mailConnection()->create([
        'mode' => WebsiteMailConnection::MODE_CUSTOMER_POSTMARK,
        'status' => 'active',
        'postmark_server_token' => 'customer-server-token',
        'connected_at' => now(),
    ]);
    $form = Form::factory()->for($website)->create();
    $submission = FormSubmission::factory()->for($website)->for($form)->create();
    $job = new SendFormSubmissionAcknowledgement($submission, 'person@example.com', 'Thanks', '<p>Received</p>', 'hello@acme.example', 'Acme');

    $job->handle(app(AutoresponderDeliveryService::class));
    $job->handle(app(AutoresponderDeliveryService::class));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Postmark-Server-Token', 'customer-server-token')
        && $request['From'] === 'Acme <hello@acme.example>'
        && $request['To'] === 'person@example.com');
    Mail::assertNotSent(FormSubmissionAcknowledgement::class);
    expect(FormSubmissionEmailDelivery::query()->sole())
        ->status->toBe('sent')
        ->provider_message_id->toBe('postmark-message-123');
});

it('suppresses managed delivery at its daily limit without sending', function (): void {
    config()->set('forms.autoresponder_limits.warmup_daily', [1, 1, 1]);
    $website = Website::factory()->create();
    $connection = $website->mailConnection()->create([
        'mode' => WebsiteMailConnection::MODE_MANAGED,
        'status' => 'active',
        'connected_at' => now(),
    ]);
    $form = Form::factory()->for($website)->create();
    $sentSubmission = FormSubmission::factory()->for($website)->for($form)->create();
    FormSubmissionEmailDelivery::factory()->create([
        'form_submission_id' => $sentSubmission->id,
        'website_id' => $website->id,
        'website_mail_connection_id' => $connection->id,
        'status' => 'sent',
        'sent_at' => now(),
    ]);
    $submission = FormSubmission::factory()->for($website)->for($form)->create();
    $job = new SendFormSubmissionAcknowledgement($submission, 'person@example.com', 'Thanks', 'Received');

    $job->handle(app(AutoresponderDeliveryService::class));

    Mail::assertNothingSent();
    expect($submission->emailDeliveries()->sole())
        ->status->toBe('suppressed')
        ->suppression_reason->toBe('managed_daily_limit');
    expect($submission->refresh()->autoresponder_sent_at)->toBeNull();
});

it('does not apply managed volume limits to customer Postmark connections', function (): void {
    config()->set('forms.autoresponder_limits.warmup_daily', [0, 0, 0]);
    Http::fake(['https://api.postmarkapp.com/email' => Http::response(['MessageID' => 'byo-message'])]);
    $website = Website::factory()->create();
    $website->mailConnection()->create([
        'mode' => WebsiteMailConnection::MODE_CUSTOMER_POSTMARK,
        'status' => 'active',
        'postmark_server_token' => 'customer-token',
        'connected_at' => now(),
    ]);
    $form = Form::factory()->for($website)->create();
    $submission = FormSubmission::factory()->for($website)->for($form)->create();

    (new SendFormSubmissionAcknowledgement($submission, 'person@example.com', 'Thanks', 'Received', 'hello@example.com'))->handle(app(AutoresponderDeliveryService::class));

    Http::assertSentCount(1);
    expect($submission->emailDeliveries()->sole()->status)->toBe('sent');
});

it('provisions a managed Postmark domain and server without exposing the token', function (): void {
    config()->set('services.postmark.account_token', 'sitewell-account-token');
    Http::fake(function (Request $request) {
        return match ([$request->method(), $request->url()]) {
            ['GET', 'https://api.postmarkapp.com/domains?count=500&offset=0'] => Http::response(['Domains' => []]),
            ['POST', 'https://api.postmarkapp.com/domains'] => Http::response([
                'ID' => 55,
                'Name' => 'acme.example',
                'DKIMVerified' => false,
                'DKIMPendingHost' => 'key._domainkey.acme.example',
                'DKIMPendingTextValue' => 'k=rsa; p=public-key',
                'ReturnPathDomain' => 'pm-bounces.acme.example',
                'ReturnPathDomainCNAMEValue' => 'pm.mtasv.net',
                'ReturnPathDomainVerified' => false,
            ]),
            ['GET', 'https://api.postmarkapp.com/servers?count=500&offset=0&name=Sitewell%20'.Website::query()->value('id').'%20Acme'] => Http::response(['Servers' => []]),
            ['POST', 'https://api.postmarkapp.com/servers'] => Http::response(['ID' => 77, 'ApiTokens' => ['managed-server-token']]),
            default => Http::response([], 404),
        };
    });
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create(['name' => 'Acme']);

    $this->actingAs($owner)->post(route('admin.websites.mail.managed.store', $website), [
        'sending_domain' => 'Acme.Example',
    ])->assertRedirect();

    $connection = $website->mailConnection()->sole();
    expect($connection->mode)->toBe(WebsiteMailConnection::MODE_MANAGED)
        ->and($connection->status)->toBe('pending_verification')
        ->and($connection->postmark_domain_id)->toBe('55')
        ->and($connection->postmark_server_id)->toBe('77')
        ->and($connection->postmark_server_token)->toBe('managed-server-token')
        ->and($connection->dkim_host)->toBe('key._domainkey.acme.example');
    expect(DB::table((new WebsiteMailConnection)->getTable())->whereKey($connection->id)->value('postmark_server_token'))->not->toBe('managed-server-token');
});

it('activates managed Postmark after DKIM verification and sends a test email', function (): void {
    config()->set('services.postmark.account_token', 'sitewell-account-token');
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $website = Website::factory()->for($owner, 'owner')->create([
        'autoresponder_from_name' => 'Acme',
        'autoresponder_from_email' => 'hello@acme.example',
    ]);
    $website->mailConnection()->create([
        'mode' => WebsiteMailConnection::MODE_MANAGED,
        'status' => 'pending_verification',
        'postmark_server_token' => 'managed-server-token',
        'postmark_server_id' => '77',
        'postmark_domain_id' => '55',
        'sending_domain' => 'acme.example',
        'connected_at' => now(),
    ]);
    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response([
                'ID' => 55,
                'DKIMVerified' => true,
                'DKIMHost' => 'key._domainkey.acme.example',
                'DKIMTextValue' => 'k=rsa; p=public-key',
                'ReturnPathDomain' => 'pm-bounces.acme.example',
                'ReturnPathDomainCNAMEValue' => 'pm.mtasv.net',
                'ReturnPathDomainVerified' => true,
            ]);
        }

        return Http::response(['MessageID' => 'test-message-id']);
    });

    $this->actingAs($owner)->post(route('admin.websites.mail.managed.verify', $website))->assertRedirect();
    expect($website->mailConnection()->sole())
        ->status->toBe('active')
        ->dkim_verified->toBeTrue()
        ->return_path_verified->toBeTrue();

    $this->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Managed Postmark')
        ->assertSee('key._domainkey.acme.example')
        ->assertSee('pm-bounces.acme.example');

    $this->post(route('admin.websites.mail.test', $website))->assertRedirect();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.postmarkapp.com/email'
        && $request['To'] === 'owner@example.com'
        && $request->hasHeader('X-Postmark-Server-Token', 'managed-server-token'));
});

it('does not allow a viewer to provision managed Postmark', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);

    $this->actingAs($viewer)->post(route('admin.websites.mail.managed.store', $website), [
        'sending_domain' => 'example.com',
    ])->assertForbidden();

    Http::assertNothingSent();
});
