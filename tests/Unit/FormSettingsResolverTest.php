<?php

use App\Models\Form;
use App\Models\Website;
use App\Services\FormSettingsResolver;

it('does not send email by default unless form recipients are configured', function (): void {
	$resolver = new FormSettingsResolver();
	$form = new Form(['name' => 'Contact']);
	$form->setRelation('website', new Website(['name' => 'Example', 'email_enabled' => false, 'email_recipients' => []]));

	expect($resolver->resolveEmailEnabled($form))->toBeFalse();
	expect($resolver->resolveEmailRecipients($form))->toBe([]);
});

it('uses form-level email and webhook settings when configured', function (): void {
	$resolver = new FormSettingsResolver();
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
