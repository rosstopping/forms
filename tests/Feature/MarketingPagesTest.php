<?php

use App\Mail\OnboardingEnquiryReceived;
use Illuminate\Support\Facades\Mail;

it('shows each public marketing page', function (string $route, string $copy): void {
    $this->get(route($route))
        ->assertSuccessful()
        ->assertSee('Sitewell')
        ->assertSee($copy);
})->with([
    'home' => ['marketing.home', 'Your website, well looked after'],
    'features' => ['marketing.features', 'Everything you need after a website goes live'],
    'pricing' => ['marketing.pricing', 'Everything your website needs to work harder'],
    'free audit' => ['marketing.free-site-audit', 'Find out what your website needs next'],
    'journal' => ['marketing.journal', 'Practical notes on looking after websites'],
    'contact' => ['marketing.contact', 'A better website starts here'],
]);

it('shows journal articles and returns not found for unknown slugs', function (): void {
    $this->get(route('marketing.article', 'a-clean-website-handover'))
        ->assertSuccessful()
        ->assertSee('A clean website handover is the start of good care')
        ->assertSee('Connect the signals that matter');

    $this->get(route('marketing.article', 'missing-article'))->assertNotFound();
});

it('validates and queues onboarding enquiries', function (): void {
    Mail::fake();

    $this->from(route('marketing.contact'))
        ->post(route('marketing.contact.store'), [
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'agency' => 'Northfield Studio',
            'website_count' => '6-15',
            'goals' => 'We need reliable forms, health reports, and a clearer content workflow.',
            '_sitewell_check' => '',
        ])
        ->assertRedirect(route('marketing.contact'))
        ->assertSessionHas('status');

    Mail::assertQueued(OnboardingEnquiryReceived::class, function (OnboardingEnquiryReceived $mail): bool {
        return $mail->hasTo(config('forms.default_recipient'))
            && $mail->hasReplyTo('alex@example.com')
            && $mail->enquiry['agency'] === 'Northfield Studio';
    });
});

it('rejects incomplete and automated onboarding enquiries', function (): void {
    Mail::fake();

    $this->from(route('marketing.contact'))
        ->post(route('marketing.contact.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'website_count' => '1000',
            'goals' => '',
            '_sitewell_check' => 'filled by a bot',
        ])
        ->assertRedirect(route('marketing.contact'))
        ->assertSessionHasErrors(['name', 'email', 'website_count', 'goals', '_sitewell_check']);

    Mail::assertNothingOutgoing();
});
