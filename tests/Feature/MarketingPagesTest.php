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

it('features the product video and contact call to action on the home page', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6')
        ->assertSee('A closer look at calmer website care')
        ->assertSee('Let’s make your website work harder for your business')
        ->assertSee('href="'.route('marketing.contact').'"', false);
});

it('markets customer-facing SEO features and a free website on every plan', function (): void {
    $this->get(route('marketing.features'))
        ->assertSuccessful()
        ->assertSee('Website health &amp; SEO audits', false)
        ->assertSee('Striking-distance keywords')
        ->assertSee('Page-level SEO and content recommendations')
        ->assertSee('A free website, if you need one')
        ->assertDontSee('Outreach')
        ->assertDontSee('Website builder');

    $this->get(route('marketing.pricing'))
        ->assertSuccessful()
        ->assertSee('Free website included with every plan')
        ->assertSeeInOrder(['Essential', 'Free website included if you need one', 'Growth', 'Free website included if you need one', 'Complete', 'Free website included if you need one']);

    $this->get(route('marketing.contact'))
        ->assertSuccessful()
        ->assertSee('Current website')
        ->assertDontSee('How many websites')
        ->assertDontSee('Start onboarding');
});

it('validates and queues get started enquiries with an optional current website', function (): void {
    Mail::fake();

    $this->from(route('marketing.contact'))
        ->post(route('marketing.contact.store'), [
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'agency' => 'Northfield Studio',
            'website' => 'https://northfield.example',
            'goals' => 'We need reliable forms, health reports, and a clearer content workflow.',
            '_sitewell_check' => '',
        ])
        ->assertRedirect(route('marketing.contact'))
        ->assertSessionHas('status');

    Mail::assertQueued(OnboardingEnquiryReceived::class, function (OnboardingEnquiryReceived $mail): bool {
        return $mail->hasTo(config('forms.default_recipient'))
            && $mail->hasReplyTo('alex@example.com')
            && $mail->enquiry['agency'] === 'Northfield Studio'
            && $mail->enquiry['website'] === 'https://northfield.example';
    });
});

it('rejects incomplete and automated get started enquiries', function (): void {
    Mail::fake();

    $this->from(route('marketing.contact'))
        ->post(route('marketing.contact.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'website' => 'not-a-url',
            'goals' => '',
            '_sitewell_check' => 'filled by a bot',
        ])
        ->assertRedirect(route('marketing.contact'))
        ->assertSessionHasErrors(['name', 'email', 'website', 'goals', '_sitewell_check']);

    Mail::assertNothingOutgoing();
});
