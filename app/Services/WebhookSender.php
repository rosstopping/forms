<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookSender
{
	public function send(FormSubmission $submission): void
	{
		$form = $submission->form;
		$website = $submission->website;

		if (! $this->shouldSend($form)) {
			return;
		}

		$url = $this->resolveUrl($form);

		if (blank($url)) {
			return;
		}

		$body = json_encode([
			'submission_id' => $submission->id,
			'website' => [
				'id' => $website->id,
				'name' => $website->name,
				'domain' => $website->primaryDomain()?->domain ?? $website->name,
			],
			'form' => [
				'id' => $form->id,
				'name' => $form->name,
				'slug' => $form->slug,
			],
			'source_url' => $submission->source_url,
			'submitted_at' => $submission->created_at->toIso8601String(),
			'data' => $submission->data,
		], JSON_UNESCAPED_SLASHES);

		$headers = [
			'Content-Type' => 'application/json',
			'User-Agent' => 'CentralForms/1.0',
			'X-Form-Submission-ID' => (string) $submission->id,
			'X-Website-ID' => (string) $website->id,
			'X-Form-ID' => (string) $form->id,
			'X-Form-Slug' => $form->slug,
		];

		if ($secret = $this->resolveSecret($form)) {
			$headers['X-Form-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
		}

		$response = Http::withHeaders($headers)
			->timeout(config('forms.webhook_timeout'))
			->post($url, json_decode($body, true));

		$submission->update([
			'webhook_sent_at' => now(),
			'webhook_status_code' => $response->status(),
			'webhook_response' => Str::limit($response->body(), config('forms.webhook_response_max_length')),
			'webhook_error' => $response->successful() ? null : 'Webhook request failed',
		]);
	}

	protected function shouldSend(Form $form): bool
	{
		return $form->webhook_enabled_override ?? $form->website->webhook_enabled;
	}

	protected function resolveUrl(Form $form): ?string
	{
		return $form->webhook_url_override ?: $form->website->webhook_url;
	}

	protected function resolveSecret(Form $form): ?string
	{
		return $form->webhook_secret_override ?: $form->website->webhook_secret;
	}
}
