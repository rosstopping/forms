<?php

use App\Mail\OnboardingEnquiryReceived;
use Illuminate\Support\Facades\Mail;

it('shows each public marketing page', function (string $route, string $copy): void {
    $this->get(route($route))
        ->assertSuccessful()
        ->assertSee('Sitewell')
        ->assertSee($copy);
})->with([
    'home' => ['marketing.home', 'A website that keeps working after launch'],
    'how it works' => ['marketing.how-it-works', 'Your website and SEO, managed by specialists'],
    'features' => ['marketing.features', 'Specialist website and SEO management, all in one place'],
    'pricing' => ['marketing.pricing', 'Everything your website needs to work harder'],
    'examples' => ['marketing.examples', 'From specialist insight to completed improvement'],
    'comparison' => ['marketing.comparison', 'One specialist team, beyond launch day'],
    'about' => ['marketing.about', 'Websites deserve ownership after launch'],
    'faqs' => ['marketing.faqs', 'What businesses ask before handing us their website'],
    'get started' => ['marketing.free-site-audit', 'Enter your website below to get started'],
    'journal' => ['marketing.journal', 'Practical notes on looking after websites'],
    'contact' => ['marketing.contact', 'See what your website needs next'],
    'privacy policy' => ['marketing.privacy', 'How Sitewell uses personal information'],
    'terms of service' => ['marketing.terms', 'Terms for using Sitewell'],
]);

it('shows journal articles and returns not found for unknown slugs', function (): void {
    $this->get(route('marketing.article', 'a-clean-website-handover'))
        ->assertSuccessful()
        ->assertSee('A clean website handover is the start of good care')
        ->assertSee('Give one team the complete picture');

    $this->get(route('marketing.article', 'missing-article'))->assertNotFound();
});

it('outputs canonical URLs for key marketing pages', function (string $routeName, array $parameters = []): void {
    $this->get(route($routeName, $parameters))
        ->assertSuccessful()
        ->assertSee('<link rel="canonical" href="'.route($routeName, $parameters).'">', false);
})->with([
    'home' => ['marketing.home'],
    'how it works' => ['marketing.how-it-works'],
    'features' => ['marketing.features'],
    'pricing' => ['marketing.pricing'],
    'examples' => ['marketing.examples'],
    'comparison' => ['marketing.comparison'],
    'about' => ['marketing.about'],
    'faqs' => ['marketing.faqs'],
    'travel and hospitality' => ['marketing.industry', ['travel-and-hospitality']],
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
    'home' => ['marketing.home', [], 'Managed business websites'],
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
        ->assertSee('<loc>'.route('marketing.how-it-works').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.features').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.feature', 'website-design-and-management').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.feature', 'forms-and-lead-management').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.pricing').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.examples').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.comparison').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.about').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.faqs').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.industry', 'travel-and-hospitality').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.industry', 'events-and-experiences').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.industry', 'training-and-professional-services').'</loc>', false)
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

it('explains every website connection option and shows the walkthrough video', function (): void {
    $this->get(route('marketing.how-it-works'))
        ->assertSuccessful()
        ->assertSee('The Sitewell Pixel')
        ->assertSee('The WordPress connector')
        ->assertSee('Fully managed by Sitewell')
        ->assertSee('https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6')
        ->assertSee('our team can add a lightweight connection')
        ->assertSee('Deactivate the plugin to return to the original WordPress website')
        ->assertSee('Connection setup, technical changes, deployments, monitoring, and ongoing website care')
        ->assertSee('href="'.route('marketing.wordpress').'"', false)
        ->assertSee('href="'.route('marketing.contact').'"', false);
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

it('positions the free site audit page around practical business outcomes', function (): void {
    $this->get(route('marketing.free-site-audit'))
        ->assertSuccessful()
        ->assertSee('What a useful website audit should actually tell you')
        ->assertSee('If you want to do a quick self-check first')
        ->assertSee('href="'.route('marketing.journal').'"', false)
        ->assertSee('href="'.route('marketing.features').'"', false)
        ->assertSee('href="'.route('marketing.contact').'"', false);
});

it('features the product video and contact call to action on the home page', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6')
        ->assertSee('A closer look at calmer website care')
        ->assertSee('Let’s make your website work harder for your business')
        ->assertSee('href="'.route('marketing.contact').'"', false);
});

it('shows an illustrative search trend and completed SEO improvement in the hero', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('Search performance')
        ->assertSee('Last 30 days · Illustrative data')
        ->assertSee('Clicks')
        ->assertSee('Impressions')
        ->assertSee('SEO amendment actioned')
        ->assertSee('/commercial-electrician')
        ->assertSee('Managed and published by your Sitewell specialist');
});

it('uses the wide editorial hero layout without picker scaffolding', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('lg:grid-cols-[7fr_5fr]', false)
        ->assertDontSee('data-uidotsh-pick', false)
        ->assertDontSee('data-uidotsh-option', false)
        ->assertDontSee('https://ui.sh/ui-picker.js');
});

it('features the local UK phone call to action on the home page', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('We’re a local, UK-based company')
        ->assertSee('01302 985 828')
        ->assertSee('href="tel:+441302985828"', false);
});

it('uses the same website-only onboarding on both entry routes', function (): void {
    $this->get(route('marketing.contact'))
        ->assertSuccessful()
        ->assertSee('Website address')
        ->assertSee('No account, email address, or website access is needed.')
        ->assertDontSee('Work email');
});

it('markets customer-facing SEO features and a free website on every plan', function (): void {
    $this->get(route('marketing.features'))
        ->assertSuccessful()
        ->assertSee('Website health &amp; SEO', false)
        ->assertSee('Keywords close to stronger positions')
        ->assertSee('Page-level SEO and content improvements')
        ->assertSee('A free website, if you need one')
        ->assertDontSee('Outreach')
        ->assertDontSee('Website builder');

    $this->get(route('marketing.pricing'))
        ->assertSuccessful()
        ->assertSee('From £149/month · £0 upfront · No contract · Portable website')
        ->assertSee('Every plan includes a website build if you need one—or you can bring your existing website with you.')
        ->assertSee('Can I take my website with me?')
        ->assertSee('There is no upfront cost or setup fee, no long-term contract, and no minimum commitment. You can cancel anytime.')
        ->assertSeeInOrder(['Essential', 'Free website included if you need one', 'Growth', 'Free website included if you need one', 'Complete', 'Free website included if you need one']);

    $this->get(route('marketing.contact'))
        ->assertSuccessful()
        ->assertSee('Website address')
        ->assertDontSee('How many websites')
        ->assertDontSee('Start onboarding');
});

it('publishes detailed outcome-led feature pages', function (string $slug, string $heading, string $example): void {
    $this->get(route('marketing.feature', $slug))
        ->assertSuccessful()
        ->assertSee($heading)
        ->assertSee('From problem to practical progress')
        ->assertSee($example)
        ->assertSee('What businesses usually ask');
})->with([
    'website design' => ['website-design-and-management', 'A professional website that keeps working after launch', 'From an ageing brochure to an active business tool'],
    'health monitoring' => ['website-health-monitoring', 'Find website problems before customers do', 'A useful report, not a wall of warnings'],
    'forms and leads' => ['forms-and-lead-management', 'Give every genuine enquiry a dependable next step', 'Friday enquiry, Monday follow-up'],
    'search growth' => ['seo-and-search-growth', 'Turn real search activity into the next useful improvement', 'A ranking becomes an action'],
    'content planning' => ['content-planning-and-generation', 'Useful content, planned and written by specialists', 'Improve the page that is already earning attention'],
    'business profile' => ['google-business-profile', 'Keep your website and local presence working together', 'A good review receives a thoughtful response'],
]);

it('returns not found for an unknown feature page', function (): void {
    $this->get(route('marketing.feature', 'unknown-feature'))->assertNotFound();
});

it('positions the commercial service as specialist led rather than AI led', function (string $routeName, array $parameters = []): void {
    $this->get(route($routeName, $parameters))
        ->assertSuccessful()
        ->assertDontSeeText('artificial intelligence')
        ->assertDontSeeText('AI assistant')
        ->assertDontSeeText('automated content')
        ->assertDontSeeText('content generation');
})->with([
    'home' => ['marketing.home'],
    'features' => ['marketing.features'],
    'pricing' => ['marketing.pricing'],
    'how it works' => ['marketing.how-it-works'],
    'website management' => ['marketing.feature', ['website-design-and-management']],
    'website health' => ['marketing.feature', ['website-health-monitoring']],
    'lead management' => ['marketing.feature', ['forms-and-lead-management']],
    'managed SEO' => ['marketing.feature', ['seo-and-search-growth']],
    'SEO content' => ['marketing.feature', ['content-planning-and-generation']],
    'local presence' => ['marketing.feature', ['google-business-profile']],
]);

it('makes website portability a core marketing promise', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('Bring your website')
        ->assertSee('Take it with you')
        ->assertSee('Stay for the care, not because your website is trapped');

    $this->get(route('marketing.feature', 'website-design-and-management'))
        ->assertSuccessful()
        ->assertSee('Bring your existing website')
        ->assertSee('Take your website with you');
});

it('shows the current monthly price for every plan', function (): void {
    $this->travelTo('2026-09-07 12:00:00 Europe/London');

    $this->get(route('marketing.pricing'))
        ->assertSuccessful()
        ->assertSee('£149')
        ->assertSee('20% off until 31 December 2026')
        ->assertSee('£316')
        ->assertSee('£395')
        ->assertSee('£695');

    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('2026 Growth offer: save 20% — now £316/month');
});

it('keeps the work page hidden until its content is ready', function (): void {
    $this->get('/our-work')->assertNotFound();
});

it('publishes sector-specific specialist management pages', function (string $industry, string $heading, string $client): void {
    $this->get(route('marketing.industry', $industry))
        ->assertSuccessful()
        ->assertSee($heading)
        ->assertSee($client)
        ->assertSee('One team across the website and search journey');
})->with([
    'travel' => ['travel-and-hospitality', 'Turn inspiration into confident enquiries and bookings', 'Casa Amore Tenerife'],
    'events' => ['events-and-experiences', 'Keep fast-moving event websites clear, visible, and ready to sell', 'VVIP Events Zante'],
    'training' => ['training-and-professional-services', 'Explain specialist services clearly and earn the right enquiries', 'Northern Prep Squad'],
]);

it('returns not found for an unknown industry page', function (): void {
    $this->get(route('marketing.industry', 'unknown-industry'))->assertNotFound();
});

it('adds service examples comparison context and expanded FAQs', function (): void {
    $this->get(route('marketing.examples'))
        ->assertSuccessful()
        ->assertSee('Representative examples')
        ->assertSee('A commercially useful service query is already close to page one.')
        ->assertSee('Our SEO specialist assesses the ranking page');

    $this->get(route('marketing.comparison'))
        ->assertSuccessful()
        ->assertSee('One-off web build')
        ->assertSee('Rented website package')
        ->assertSee('Ongoing specialist value');

    $this->get(route('marketing.faqs'))
        ->assertSuccessful()
        ->assertSee('Who actually manages the work?')
        ->assertSee('Do you guarantee Google rankings?')
        ->assertSee('Can you work with our current website provider?');
});

it('explains the specialist work carried out during a typical month on every plan', function (): void {
    $this->get(route('marketing.pricing'))
        ->assertSuccessful()
        ->assertSeeInOrder([
            'Essential',
            'A typical month',
            'Growth',
            'A typical month',
            'Complete',
            'A typical month',
        ])
        ->assertSee('Compare the working relationship');
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
