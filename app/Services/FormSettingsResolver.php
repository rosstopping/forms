<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Str;

class FormSettingsResolver
{
    public function resolveAutoresponderEnabled(Form $form): bool
    {
        return $form->autoresponder_enabled_override ?? $form->website->autoresponder_enabled;
    }

    public function resolveAutoresponderSubject(Form $form, FormSubmission $submission): string
    {
        $template = $form->autoresponder_subject_override
            ?: $form->website->autoresponder_subject
            ?: "We've received your {form_name} enquiry";

        return Str::limit(preg_replace('/[\r\n]+/', ' ', $this->replaceAutoresponderTokens($template, $form, $submission)) ?: '', 255, '');
    }

    public function resolveAutoresponderBody(Form $form, FormSubmission $submission): string
    {
        $template = $form->autoresponder_body_override
            ?: $form->website->autoresponder_body
            ?: "Hi {name},\n\nThanks for contacting {website_name}. We've received your enquiry and someone from our team will get back to you soon.\n\nKind regards,\n{website_name}";

        return $this->replaceAutoresponderTokens($template, $form, $submission);
    }

    private function replaceAutoresponderTokens(string $template, Form $form, FormSubmission $submission): string
    {
        return strtr($template, [
            '{name}' => $submission->contactName() ?? 'there',
            '{form_name}' => $form->name,
            '{website_name}' => $form->website->name,
            '{website_domain}' => $form->website->primaryDomain()?->domain ?? $form->website->name,
            '{submission_id}' => (string) $submission->id,
        ]);
    }

    public function resolveEmailEnabled(Form $form): bool
    {
        if ($form->email_enabled_override !== null) {
            return (bool) $form->email_enabled_override;
        }

        return false;
    }

    public function resolveEmailRecipients(Form $form): array
    {
        if (! blank($form->email_recipients_override)) {
            return array_values(array_filter(array_map(static fn ($recipient) => trim((string) $recipient), (array) $form->email_recipients_override)));
        }

        return [];
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
        if ($form->webhook_enabled_override !== null) {
            return (bool) $form->webhook_enabled_override;
        }

        return false;
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
