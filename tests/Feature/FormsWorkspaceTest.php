<?php

use App\Jobs\SendFormSubmissionAcknowledgement;
use App\Mail\FormSubmissionAcknowledgement;
use App\Mail\FormSubmissionReceived;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMailConnection;
use App\Services\AutoresponderDeliveryService;
use App\Services\FormSettingsResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create(['autoresponder_reply_to_email' => 'team@example.com']);
    $this->form = Form::factory()->for($this->website)->create(['autoresponder_reply_to_email_override' => null]);
    $this->actingAs($this->owner);
    $this->defaultsPayload = ['autoresponder_enabled' => false, 'autoresponder_content_type' => 'text', 'autoresponder_delay_minutes' => 0, 'forms_section' => 'defaults'];
});

function formsWorkspaceXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML($html);

    return new DOMXPath($document);
}

test('forms starts with the list and separates defaults from installation', function () {
    $response = $this->get(route('admin.websites.section', [$this->website, 'forms']))->assertSuccessful()
        ->assertSee('Two different emails:')->assertSee('Set up form');
    $xpath = formsWorkspaceXPath($response->getContent());
    expect($xpath->query('//*[@id="forms-section-list" and not(@hidden)]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="forms-section-defaults" and @hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="forms-section-installation" and @hidden]')->length)->toBe(1);
});

test('form sections have stable URLs and only the selected panel is visible', function (string $section) {
    $response = $this->get(route('admin.forms.show', [$this->form, 'form_section' => $section]))->assertSuccessful();
    $xpath = formsWorkspaceXPath($response->getContent());
    expect($xpath->query('//*[@id="form-section-'.$section.'" and not(@hidden)]')->length)->toBe(1);
    foreach (array_diff(['notifications', 'reply', 'setup'], [$section]) as $hidden) {
        expect($xpath->query('//*[@id="form-section-'.$hidden.'" and @hidden]')->length)->toBe(1);
    }
})->with(['notifications', 'reply', 'setup']);

test('website reply-to can be changed without configuring or unpausing Postmark', function () {
    $connection = $this->website->mailConnection()->create(['mode' => WebsiteMailConnection::MODE_MANAGED, 'status' => 'paused', 'paused_at' => now(), 'pause_reason' => 'Sending paused']);
    $this->put(route('admin.websites.autoresponder.update', $this->website), [...$this->defaultsPayload, 'autoresponder_reply_to_email' => 'hello@business.example'])
        ->assertSessionDoesntHaveErrors()->assertRedirect(route('admin.websites.section', [$this->website, 'forms', 'forms_section' => 'defaults']));
    expect($this->website->fresh()->autoresponder_reply_to_email)->toBe('hello@business.example')
        ->and($connection->fresh()->status)->toBe('paused')->and($connection->fresh()->pause_reason)->toBe('Sending paused');
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

test('reply-to rejects invalid email addresses and header injection', function (string $email) {
    $this->put(route('admin.websites.autoresponder.update', $this->website), [...$this->defaultsPayload, 'autoresponder_reply_to_email' => $email])->assertSessionHasErrors('autoresponder_reply_to_email');
    $this->put(route('admin.forms.update', $this->form), ['autoresponder_mode' => 'inherit', 'autoresponder_reply_to_email_override' => $email])->assertSessionHasErrors('autoresponder_reply_to_email_override');
})->with(['not-an-email', "hello@example.com\r\nBcc: other@example.com"]);

test('form reply-to overrides the website and clearing it restores inheritance', function () {
    $url = route('admin.forms.update', $this->form);
    $this->put($url, ['autoresponder_mode' => 'inherit', 'autoresponder_reply_to_email_override' => 'sales@example.com', 'form_section' => 'reply'])
        ->assertRedirect(route('admin.forms.show', [$this->form, 'form_section' => 'reply']));
    expect(app(FormSettingsResolver::class)->resolveAutoresponderReplyTo($this->form->fresh()))->toBe('sales@example.com');
    $this->put($url, ['autoresponder_mode' => 'inherit', 'autoresponder_reply_to_email_override' => ''])->assertRedirect();
    expect(app(FormSettingsResolver::class)->resolveAutoresponderReplyTo($this->form->fresh()))->toBe('team@example.com');
});

test('delayed acknowledgements snapshot reply-to and keep the fixed sender', function () {
    $this->website->update(['autoresponder_enabled' => true, 'autoresponder_delay_minutes' => 15]);
    $this->website->domains()->create(['domain' => 'reply.example', 'is_primary' => true]);
    $this->form->update(['name' => 'Contact form', 'slug' => 'contact-form', 'email_enabled_override' => false, 'autoresponder_enabled_override' => null, 'autoresponder_reply_to_email_override' => 'sales@example.com']);
    $this->withHeader('Origin', 'https://reply.example')->post('/submit', ['_form_name' => 'Contact form', 'name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Please call me about a project.'])->assertRedirect();
    $this->form->update(['autoresponder_reply_to_email_override' => 'changed@example.com']);
    Queue::assertPushed(SendFormSubmissionAcknowledgement::class, fn ($job) => $job->replyToEmail === 'sales@example.com' && $job->fromEmail === config('forms.autoresponder_from_address'));
});

test('normal mail delivery includes the saved reply-to with Postmark disabled', function () {
    config(['services.postmark.delivery_enabled' => false]);
    $submission = FormSubmission::factory()->for($this->website)->for($this->form)->create();
    $delivery = app(AutoresponderDeliveryService::class)->send($submission, 'customer@example.com', 'Thank you', '<p>Received.</p>', 'mail@digizu.co.uk', 'Our business', 'team@example.com');
    Mail::assertSent(FormSubmissionAcknowledgement::class, function ($mail) {
        $envelope = $mail->envelope();

        return $envelope->replyTo[0]->address === 'team@example.com' && $envelope->from->address === 'mail@digizu.co.uk';
    });
    expect($delivery->reply_to_email)->toBe('team@example.com');
    Http::assertNothingSent();
});

test('Postmark delivery also receives the reply-to when explicitly enabled', function () {
    config(['services.postmark.delivery_enabled' => true]);
    $this->website->mailConnection()->create(['mode' => WebsiteMailConnection::MODE_CUSTOMER_POSTMARK, 'status' => 'active', 'postmark_server_token' => 'test-token']);
    Http::fake(['https://api.postmarkapp.com/email' => Http::response(['MessageID' => 'message-id'])]);
    $submission = FormSubmission::factory()->for($this->website)->for($this->form)->create();
    app(AutoresponderDeliveryService::class)->send($submission, 'customer@example.com', 'Thanks', '<p>Received.</p>', 'mail@digizu.co.uk', 'Our business', 'team@example.com');
    Http::assertSent(fn ($request) => $request['ReplyTo'] === 'team@example.com');
    Mail::assertNothingSent();
});

test('team notifications still reply to the visitor rather than the business', function () {
    $submission = FormSubmission::factory()->for($this->website)->for($this->form)->create(['data' => ['email' => 'visitor@example.com']]);
    expect((new FormSubmissionReceived($submission))->envelope()->replyTo[0]->address)->toBe('visitor@example.com');
});

test('viewers cannot edit website or individual form reply settings', function () {
    $viewer = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $this->actingAs($viewer)->get(route('admin.forms.show', $this->form))->assertSuccessful()->assertDontSee('Save settings');
    $this->get(route('admin.websites.section', [$this->website, 'forms', 'forms_section' => 'defaults']))->assertSuccessful()->assertDontSee('Save automatic reply');
    $this->put(route('admin.websites.autoresponder.update', $this->website), $this->defaultsPayload)->assertForbidden();
    $this->put(route('admin.forms.update', $this->form), ['autoresponder_mode' => 'inherit'])->assertForbidden();
});

test('form setup checks return to their own section without sending anything', function () {
    $this->post(route('admin.forms.setup-check', $this->form), ['form_section' => 'setup'])->assertRedirect(route('admin.forms.show', [$this->form, 'form_section' => 'setup']));
    Mail::assertNothingSent();
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});
