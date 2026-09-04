<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Website;
use App\Services\AutoresponderHtmlSanitizer;
use App\Services\FormSettingsResolver;
use App\Services\WebsiteMailRecipients;

it('does not send email by default unless form recipients are configured', function (): void {
    $resolver = new FormSettingsResolver(new AutoresponderHtmlSanitizer, new WebsiteMailRecipients);
    $form = new Form(['name' => 'Contact']);
    $form->setRelation('website', new Website(['name' => 'Example', 'email_enabled' => false, 'email_recipients' => []]));

    expect($resolver->resolveEmailEnabled($form))->toBeFalse();
    expect($resolver->resolveEmailRecipients($form))->toBe([]);
});

it('uses form-level email and webhook settings when configured', function (): void {
    $resolver = new FormSettingsResolver(new AutoresponderHtmlSanitizer, new WebsiteMailRecipients);
    $form = new Form([
        'email_enabled_override' => true,
        'email_recipients_override' => ['ops@example.com'],
        'webhook_enabled_override' => true,
        'webhook_url_override' => 'https://example.com/hook',
        'webhook_secret_override' => 'secret',
    ]);
    $form->setRelation('website', new Website(['name' => 'Example', 'email_enabled' => false, 'email_recipients' => []]));

    expect($resolver->resolveEmailEnabled($form))->toBeTrue();
    expect($resolver->resolveEmailRecipients($form))->toEqual(['ops@example.com']);
    expect($resolver->resolveWebhookEnabled($form))->toBeTrue();
    expect($resolver->resolveWebhookUrl($form))->toBe('https://example.com/hook');
    expect($resolver->resolveWebhookSecret($form))->toBe('secret');
});

it('replaces tags for any submitted form field', function (): void {
    $resolver = new FormSettingsResolver(new AutoresponderHtmlSanitizer, new WebsiteMailRecipients);
    $website = new Website(['name' => 'Example', 'autoresponder_delay_minutes' => 10]);
    $form = new Form([
        'name' => 'Quote request',
        'autoresponder_subject_override' => 'Quote for {service_type}',
        'autoresponder_body_override' => 'Hi {first_name}, you selected {Service Type} in {Preferred Areas}.',
        'autoresponder_delay_minutes_override' => 25,
    ]);
    $form->setRelation('website', $website);
    $submission = new FormSubmission([
        'data' => [
            'first_name' => 'Ada',
            'Service Type' => 'Research & <script>',
            'Preferred Areas' => ['London', 'Bristol'],
        ],
    ]);

    expect($resolver->resolveAutoresponderSubject($form, $submission))->toBe('Quote for Research & <script>')
        ->and($resolver->resolveAutoresponderBody($form, $submission))->toBe('<div>Hi Ada, you selected Research &amp; &lt;script&gt; in London, Bristol.</div>')
        ->and($resolver->resolveAutoresponderDelayMinutes($form))->toBe(25);
});

it('preserves raw html while escaping submission tag values', function (): void {
    $resolver = new FormSettingsResolver(new AutoresponderHtmlSanitizer, new WebsiteMailRecipients);
    $website = new Website([
        'name' => 'Example',
        'autoresponder_content_type' => 'text',
    ]);
    $form = new Form([
        'name' => 'Contact',
        'autoresponder_content_type_override' => 'html',
        'autoresponder_body_override' => '<table style="color: red"><tr><td>{name}</td></tr></table>',
    ]);
    $form->setRelation('website', $website);
    $submission = new FormSubmission(['data' => ['name' => '<Ada>']]);

    expect($resolver->resolveAutoresponderContentType($form))->toBe('html')
        ->and($resolver->resolveAutoresponderBody($form, $submission))
        ->toBe('<table style="color: red"><tr><td>&lt;Ada&gt;</td></tr></table>');
});
