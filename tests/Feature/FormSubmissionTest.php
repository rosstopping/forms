<?php

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Website;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
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
	Mail::assertSentCount(1);
});

it('allows public submissions without a CSRF token', function (): void {
	$response = $this->withHeader('Origin', 'https://example.com')
		->post('/submit', [
			'_form_name' => 'Contact form',
			'name' => 'Grace Hopper',
		]);

	$response->assertStatus(302);
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
