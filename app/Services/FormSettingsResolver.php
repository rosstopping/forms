<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Website;

class FormSettingsResolver
{
	public function resolveEmailEnabled(Form $form): bool
	{
		return $form->email_enabled_override ?? $form->website->email_enabled ?? config('forms.default_recipient') !== null;
	}

	public function resolveEmailRecipients(Form $form): array
	{
		if (! blank($form->email_recipients_override)) {
			return $form->email_recipients_override;
		}

		if (! blank($form->website->email_recipients)) {
			return $form->website->email_recipients;
		}

		$recipient = config('forms.default_recipient');

		return $recipient ? [$recipient] : [];
	}

	public function resolveEmailSubject(Form $form, string $default): string
	{
		if (! blank($form->email_subject_override)) {
			return strtr($form->email_subject_override, [
				'{form_name}' => $form->name,
				'{website_name}' => $form->website->name,
				'{website_domain}' => $form->website->primaryDomain()?->domain ?? $form->website->name,
				'{submission_id}' => (string) $form->id,
			]);
		}

		return $default;
	}

	public function resolveWebhookEnabled(Form $form): bool
	{
		return $form->webhook_enabled_override ?? $form->website->webhook_enabled;
	}

	public function resolveWebhookUrl(Form $form): ?string
	{
		return $form->webhook_url_override ?: $form->website->webhook_url;
	}

	public function resolveWebhookSecret(Form $form): ?string
	{
		return $form->webhook_secret_override ?: $form->website->webhook_secret;
	}
}
