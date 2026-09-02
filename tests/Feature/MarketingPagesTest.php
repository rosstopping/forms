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
    'privacy policy' => ['marketing.privacy', 'How Sitewell uses personal information'],
    'terms of service' => ['marketing.terms', 'Terms for using Sitewell'],
]);

it('shows journal articles and returns not found for unknown slugs', function (): void {
    $this->get(route('marketing.article', 'a-clean-website-handover'))
        ->assertSuccessful()
        ->assertSee('A clean website handover is the start of good care')
        ->assertSee('Connect the signals that matter');

    $this->get(route('marketing.article', 'missing-article'))->assertNotFound();
});

it('outputs canonical URLs for key marketing pages', function (string $routeName, array $parameters = []): void {
    $this->get(route($routeName, $parameters))
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.route($routeName, $parameters).'">', false);
})->with([
    'home' => ['marketing.home'],
    'features' => ['marketing.features'],
    'pricing' => ['marketing.pricing'],
    'free audit' => ['marketing.free-site-audit'],
    'journal' => ['marketing.journal'],
    'contact' => ['marketing.contact'],
    'privacy policy' => ['marketing.privacy'],
    'terms of service' => ['marketing.terms'],
    'journal article' => ['marketing.article', ['a-clean-website-handover']],
]);

it('uses shorter SEO page titles for flagged marketing pages', function (string $routeName, array $parameters, string $title): void {
    $fullTitle = $title.' · Your website, well looked after';

    $this->get(route($routeName, $parameters))
        ->assertSuccessful()
        ->assertSee('<title>'.$fullTitle.'</title>', false);

    expect(strlen($fullTitle))->toBeLessThanOrEqual(65);
})->with([
    'home' => ['marketing.home', [], 'Website care for growing teams'],
    'clean handover article' => ['marketing.article', ['a-clean-website-handover'], 'A clean website handover'],
    'forms article' => ['marketing.article', ['forms-that-never-lose-a-lead'], 'Build forms that capture leads'],
    'search article' => ['marketing.article', ['search-data-to-content-decisions'], 'Turn search data into action'],
]);

it('adds organization structured data on the home page', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('application/ld+json')
        ->assertSee('Organization')
        ->assertSee('Sitewell');
});

it('adds blog posting structured data on article pages', function (): void {
    $this->get(route('marketing.article', 'forms-that-never-lose-a-lead'))
        ->assertSuccessful()
        ->assertSee('application/ld+json')
        ->assertSee('BlogPosting')
        ->assertSee('Build forms that never leave a lead wondering')
        ->assertSee('2026-07-31');
});

it('publishes an XML sitemap for the marketing site', function (): void {
    $this->get(route('marketing.sitemap'))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertSee('<loc>'.route('marketing.home').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.features').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.pricing').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.free-site-audit').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.journal').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.contact').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.privacy').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.terms').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.article', 'a-clean-website-handover').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.article', 'forms-that-never-lose-a-lead').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.article', 'search-data-to-content-decisions').'</loc>', false)
        ->assertDontSee(route('login'))
        ->assertDontSee('/admin');
});

it('publishes legal pages suitable for connected Google services', function (): void {
    $this->get(route('marketing.privacy'))
        ->assertSuccessful()
        ->assertSee('Google Search Console data')
        ->assertSee('Google Business Profile data')
        ->assertSee('encrypted access and refresh tokens')
        ->assertSee('Google API Services User Data Policy')
        ->assertSee('Limited Use requirements')
        ->assertSee('href="'.route('marketing.contact').'"', false);

    $this->get(route('marketing.terms'))
        ->assertSuccessful()
        ->assertSee('Connected services')
        ->assertSee('Automated and AI-assisted features')
        ->assertSee('href="'.route('marketing.privacy').'"', false);
});

it('features the product video and contact call to action on the home page', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6')
        ->assertSee('A closer look at calmer website care')
        ->assertSee('Let’s make your website work harder for your business')
        ->assertSee('href="'.route('marketing.contact').'"', false);
});

it('features the local UK phone call to action on the home page', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('We’re a local, UK-based company')
        ->assertSee('01302 985 828')
        ->assertSee('href="tel:+441302985828"', false);
});

it('offers the UK phone number on the contact page', function (): void {
    $this->get(route('marketing.contact'))
        ->assertSuccessful()
        ->assertSee('Prefer to talk?')
        ->assertSee('01302 985 828')
        ->assertSee('href="tel:+441302985828"', false);
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
        ->assertSee('From £149/month · £0 upfront · No contract · Cancel anytime')
        ->assertSee('Every plan includes your website build if you need one')
        ->assertSee('There is no upfront cost or setup fee, no long-term contract, and no minimum commitment. You can cancel anytime.')
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
