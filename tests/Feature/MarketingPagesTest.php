<?php

use App\Jobs\GenerateWebsiteAudit;
use App\Mail\OnboardingEnquiryReceived;
use App\Models\FormSubmission;
use App\Models\WebsiteAudit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('shows each public marketing page', function (string $route, string $copy): void {
    $response = $this->get(route($route))
        ->assertSuccessful()
        ->assertSee('marketing-site prospect-workspace', false)
        ->assertSee('Sitewell')
        ->assertSee($copy);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//nav[@aria-label="Main navigation"]/a');
    expect($links->length)->toBe(1);
    expect($links->item(0)->getAttribute('href'))->toBe(route('marketing.free-site-audit'));
    expect($xpath->query('//header//details')->length)->toBe(0);
    $footerLinks = $xpath->query('//footer//a');
    expect(array_map(fn ($link) => $link->getAttribute('href'), iterator_to_array($footerLinks)))->toBe([route('marketing.faqs'), route('marketing.journal'), route('marketing.privacy'), route('marketing.terms')]);
    expect(array_map(fn ($link) => trim($link->textContent), iterator_to_array($links)))->toBe(['Get your free search audit →']);
    expect($xpath->query('//main')->length)->toBe(1);

})->with([
    'home' => ['marketing.home', 'Turn more searches'],
    'how it works' => ['marketing.how-it-works', 'We take care of your website.'],
    'features' => ['marketing.features', 'Website care. SEO. Content.'],
    'pricing' => ['marketing.pricing', 'Choose your level of support.'],
    'examples' => ['marketing.examples', 'The work we do.'],
    'comparison' => ['marketing.comparison', 'Compare your options.'],
    'about' => ['marketing.about', 'Your website needs looking after.'],
    'faqs' => ['marketing.faqs', 'Your questions, answered.'],
    'get started' => ['marketing.free-site-audit', 'See what your website needs.'],
    'journal' => ['marketing.journal', 'Practical website and SEO guides.'],
    'contact' => ['marketing.contact', 'Talk to Sitewell.'],
    'privacy policy' => ['marketing.privacy', 'How Sitewell uses personal information'],
    'terms of service' => ['marketing.terms', 'Terms for using Sitewell'],
]);

it('shows journal articles and returns not found for unknown slugs', function (): void {
    $this->get(route('marketing.journal'))
        ->assertSuccessful()
        ->assertSee('How often should I update my website?')
        ->assertSee('How do I know if my SEO is working?')
        ->assertSee('Why isn\'t my website showing on Google?')
        ->assertSee('Why has my website traffic dropped?')
        ->assertSee('Why isn\'t my website ranking on Google?')
        ->assertSee('What does website maintenance actually include?');

    $this->get(route('marketing.article', 'how-often-should-i-update-my-website'))
        ->assertSuccessful()
        ->assertSee('Start by separating four different kinds of update')
        ->assertSee('Technical maintenance should be ongoing, not annual')
        ->assertSee('A simple rhythm usually beats long periods of neglect')
        ->assertSee('Start a free website audit')
        ->assertSee('href="'.route('marketing.article', 'what-website-maintenance-actually-includes').'"', false)
        ->assertSee('href="'.route('marketing.article', 'how-do-i-know-if-my-seo-is-working').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'website-management-services').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'website-maintenance-packages').'"', false)
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false);

    $this->get(route('marketing.article', 'how-do-i-know-if-my-seo-is-working'))
        ->assertSuccessful()
        ->assertSee('Start with the business outcome, not the dashboard')
        ->assertSee('Conversions and leads tell you whether the traffic is useful')
        ->assertSee('How Sitewell monitors website performance')
        ->assertSee('Start a free website audit')
        ->assertSee('href="'.route('marketing.article', 'search-data-to-content-decisions').'"', false)
        ->assertSee('href="'.route('marketing.article', 'how-often-should-i-update-my-website').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'managed-seo-services').'"', false)
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false);

    $this->get(route('marketing.article', 'why-isnt-my-website-showing-on-google'))
        ->assertSuccessful()
        ->assertSee('First, separate two different problems')
        ->assertSee('Problem A: The page is not indexed')
        ->assertSee('Problem B: The page is indexed but ranking poorly')
        ->assertSee('A simple diagnosis workflow for business owners')
        ->assertSee('Start a free website audit')
        ->assertSee('href="'.route('marketing.article', 'why-isnt-my-website-ranking-on-google').'"', false)
        ->assertSee('href="'.route('marketing.article', 'why-has-my-website-traffic-dropped').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'managed-seo-services').'"', false)
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false);

    $this->get(route('marketing.article', 'why-has-my-website-traffic-dropped'))
        ->assertSuccessful()
        ->assertSee('Cause 1: Search results changed after an algorithm update')
        ->assertSee('Cause 5: Tracking problems, not real demand loss')
        ->assertSee('Cause 7: Content decay on once-useful pages')
        ->assertSee('A practical recovery checklist')
        ->assertSee('Start a free website audit')
        ->assertSee('href="'.route('marketing.landing', 'seo-for-small-businesses').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'local-seo-services').'"', false)
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false);

    $this->get(route('marketing.article', 'why-isnt-my-website-ranking-on-google'))
        ->assertSuccessful()
        ->assertSee('Reason 2: Local relevance is too weak')
        ->assertSee('A practical diagnosis checklist before you spend more')
        ->assertSee('Start a free website audit')
        ->assertSee('href="'.route('marketing.landing', 'local-seo-services').'"', false)
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'managed-seo-services').'"', false);

    $this->get(route('marketing.article', 'what-website-maintenance-actually-includes'))
        ->assertSuccessful()
        ->assertSee('Website maintenance is more than hosting and renewals')
        ->assertSee('Run a free website audit')
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'website-maintenance-packages').'"', false)
        ->assertSee('href="'.route('marketing.pricing').'"', false)
        ->assertSee('href="'.route('marketing.article', 'forms-that-never-lose-a-lead').'"', false)
        ->assertSee('href="'.route('marketing.article', 'search-data-to-content-decisions').'"', false)
        ->assertSee('href="'.route('marketing.article', 'why-isnt-my-website-ranking-on-google').'"', false);

    $this->get(route('marketing.article', 'a-clean-website-handover'))
        ->assertSuccessful()
        ->assertSee('A clean website handover is the start of good care')
        ->assertSee('Give one team the complete picture');

    $this->get(route('marketing.article', 'search-data-to-content-decisions'))
        ->assertSuccessful()
        ->assertSee('Turn search data into the next useful improvement')
        ->assertSee('href="'.route('marketing.landing', 'seo-for-small-businesses').'"', false)
        ->assertSee('href="'.route('marketing.landing', 'managed-seo-services').'"', false);

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
    'website updates article' => ['marketing.article', ['how-often-should-i-update-my-website']],
    'seo working article' => ['marketing.article', ['how-do-i-know-if-my-seo-is-working']],
    'showing article' => ['marketing.article', ['why-isnt-my-website-showing-on-google']],
    'traffic drop article' => ['marketing.article', ['why-has-my-website-traffic-dropped']],
    'ranking article' => ['marketing.article', ['why-isnt-my-website-ranking-on-google']],
    'website management landing page' => ['marketing.landing', ['website-management-services']],
    'website maintenance landing page' => ['marketing.landing', ['website-maintenance-packages']],
    'local seo landing page' => ['marketing.landing', ['local-seo-services']],
    'small business SEO landing page' => ['marketing.landing', ['seo-for-small-businesses']],
]);

it('uses shorter SEO page titles for flagged marketing pages', function (string $routeName, array $parameters, string $title): void {
    $fullTitle = $title.' · Your website, well looked after';

    $this->get(route($routeName, $parameters))
        ->assertSuccessful()
        ->assertSee('<title>'.$fullTitle.'</title>', false);

    expect(mb_strlen($fullTitle))->toBeLessThanOrEqual(65);
})->with([
    'home' => ['marketing.home', [], 'Managed business websites'],
    'clean handover article' => ['marketing.article', ['a-clean-website-handover'], 'A clean website handover'],
    'website updates article' => ['marketing.article', ['how-often-should-i-update-my-website'], 'How often to update a website'],
    'seo working article' => ['marketing.article', ['how-do-i-know-if-my-seo-is-working'], 'How to know if SEO is working'],
    'showing article' => ['marketing.article', ['why-isnt-my-website-showing-on-google'], 'Website not showing on Google?'],
    'traffic drop article' => ['marketing.article', ['why-has-my-website-traffic-dropped'], 'Why website traffic drops'],
    'ranking article' => ['marketing.article', ['why-isnt-my-website-ranking-on-google'], 'Why your website is not ranking'],
    'website maintenance article' => ['marketing.article', ['what-website-maintenance-actually-includes'], 'Website maintenance explained'],
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
    $this->get(route('marketing.article', 'what-website-maintenance-actually-includes'))
        ->assertSuccessful()
        ->assertSee('application/ld+json')
        ->assertSee('BlogPosting')
        ->assertSee('What does website maintenance actually include?')
        ->assertSee('2026-08-31');
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
        ->assertSee('<loc>'.route('marketing.landing', 'website-management-services').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.landing', 'website-maintenance-packages').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.landing', 'small-business-website-support').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.landing', 'managed-seo-services').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.landing', 'seo-for-small-businesses').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.landing', 'local-seo-services').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.landing', 'website-lead-generation').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.landing', 'improve-my-website').'</loc>', false)
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
        ->assertSee('<loc>'.route('marketing.article', 'how-often-should-i-update-my-website').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.article', 'how-do-i-know-if-my-seo-is-working').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.article', 'why-isnt-my-website-showing-on-google').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.article', 'why-has-my-website-traffic-dropped').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.article', 'why-isnt-my-website-ranking-on-google').'</loc>', false)
        ->assertSee('<loc>'.route('marketing.article', 'what-website-maintenance-actually-includes').'</loc>', false)
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

it('keeps get started focused on one protected website form', function (): void {
    $response = $this->get(route('marketing.free-site-audit'))
        ->assertSuccessful()
        ->assertSee('See what your website needs.')
        ->assertSee('Search setup')
        ->assertSee('Website health')
        ->assertSee('Security basics')
        ->assertSee('Get your free search audit')
        ->assertSee('action="'.route('marketing.free-site-audit.store').'"', false)
        ->assertSee('name="_sitewell_check"', false)
        ->assertDontSee('Useful from the start')
        ->assertDontSee('What the audit checks.')
        ->assertSee('href="'.route('marketing.journal').'"', false)
        ->assertDontSee('href="'.route('marketing.features').'"', false)
        ->assertDontSee('href="'.route('marketing.contact').'"', false);

    expect(substr_count($response->getContent(), 'data-audit-form'))->toBe(1);
});

it('features the product video and audit call to action on the home page', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6')
        ->assertDontSee('in plain sight.')
        ->assertSee('Get your free search audit')
        ->assertDontSee('href="'.route('marketing.contact').'"', false);
});

it('leads with search outcomes and the website check without invented proof', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('Turn more searches')
        ->assertSee('your next customer.')
        ->assertSee('Get your free search audit')
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false)
        ->assertDontSee('#1 Search Growth Agency')
        ->assertDontSee('250,000')
        ->assertDontSee('£0 upfront')
        ->assertDontSee('href="'.route('marketing.how-it-works').'"', false)
        ->assertDontSee('href="'.route('marketing.contact').'"', false)
        ->assertDontSee('Last 30 days · Illustrative data')
        ->assertDontSee('/commercial-electrician');
});

it('uses the final Spotlight hero with unique ids even on old preview links', function (?string $selected): void {
    $response = $this->get(route('marketing.home', ['hero' => $selected]))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-uidotsh-pick or @data-uidotsh-option]')->length)->toBe(0)
        ->and($xpath->query('//h1')->length)->toBe(1)
        ->and($xpath->query('//h1')->item(0)->getAttribute('id'))->toBe('hero-spotlight')
        ->and(trim($xpath->query('//h1')->item(0)->textContent))->toBe('Turn more searches into your next customer.')
        ->and(substr_count($response->getContent(), 'src="https://ui.sh/ui-picker.js"'))->toBe(0);
    $hero = $xpath->query('//section[@aria-labelledby="hero-spotlight"]')->item(0);
    expect($hero->textContent)->toContain('We’ll get you more customers.')
        ->not->toContain('No email', 'No commitment', 'Your business. Easier to find.');
    expect($xpath->query('//ul[@aria-label="Search engines and AI assistants"]/li')->length)->toBe(6);
    foreach (['google', 'bing', 'openai', 'gemini', 'perplexity', 'claude'] as $mark) {
        expect(is_file(public_path('search-'.$mark.'.svg')))->toBeTrue();
    }
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $element) {
        $ids[] = $element->getAttribute('id');
    }
    expect(count(array_unique($ids)))->toBe(count($ids));
    $this->get(route('marketing.pricing'))->assertDontSee('https://ui.sh/ui-picker.js');
})->with([null, 'search-first', 'editorial', 'spotlight']);

it('keeps the chosen audit button on Spotlight even on old preview links', function (mixed $button): void {
    $response = $this->get(route('marketing.home', ['cta' => $button]))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $visibleButtons = $xpath->query('//section[@aria-labelledby="hero-spotlight"]//button[@type="submit"]');
    expect($visibleButtons->length)->toBe(1)
        ->and($visibleButtons->item(0)->getAttribute('aria-label'))->toBe('Get your free search audit')
        ->and($xpath->query('//section[@aria-labelledby="hero-spotlight"]//form')->item(0)->getAttribute('action'))->toBe(route('marketing.free-site-audit.store'))
        ->and($xpath->query('//h1[not(ancestor::*[@hidden])]')->item(0)->getAttribute('id'))->toBe('hero-spotlight');
})->with([null, 'check', 'audit', 'fix', 'improve', 'invalid', [['fix']]]);

it('features the local UK phone number on the home page', function (): void {
    $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertSee('Hi, I’m Ross.')
        ->assertSee('Web developer. Based in Doncaster.')
        ->assertSee('01302 248 374');
});

it('keeps contact separate from website-only onboarding', function (): void {
    $this->get(route('marketing.contact'))
        ->assertSuccessful()
        ->assertSee('Contact Sitewell')
        ->assertSee('01302 248 374')
        ->assertSee('href="tel:+441302248374"', false)
        ->assertSee('Work email')
        ->assertSee('Send enquiry')
        ->assertDontSee('No account, email address, or website access is needed.');
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
        ->assertSee('Bring your website or have us build one.')
        ->assertSee('Can I take my website with me?')
        ->assertSee('There is no upfront cost or setup fee, no long-term contract, and no minimum commitment. You can cancel anytime.')
        ->assertSeeInOrder(['Essential', 'Free website included if you need one', 'Growth', 'Free website included if you need one', 'Complete', 'Free website included if you need one']);

    $this->get(route('marketing.contact'))
        ->assertSuccessful()
        ->assertSee('Work email')
        ->assertDontSee('How many websites')
        ->assertDontSee('Start onboarding');
});

it('publishes detailed outcome-led feature pages', function (string $slug, string $heading, string $example): void {
    $this->get(route('marketing.feature', $slug))
        ->assertSuccessful()
        ->assertSee($heading)
        ->assertSee('What we do.')
        ->assertSee($example)
        ->assertSee('Common questions.');
})->with([
    'website design' => ['website-design-and-management', 'A professional website that keeps working after launch', 'From an ageing brochure to an active business tool'],
    'health monitoring' => ['website-health-monitoring', 'Find website problems before customers do', 'A useful report, not a wall of warnings'],
    'forms and leads' => ['forms-and-lead-management', 'Keep track of every enquiry.', 'Friday enquiry, Monday follow-up'],
    'search growth' => ['seo-and-search-growth', 'See how your website performs in search.', 'A ranking becomes an action'],
    'content planning' => ['content-planning-and-generation', 'Content your customers need.', 'Improve the page that is already earning attention'],
    'business profile' => ['google-business-profile', 'Keep your website and local presence working together', 'A good review receives a thoughtful response'],
]);

it('returns not found for an unknown feature page', function (): void {
    $this->get(route('marketing.feature', 'unknown-feature'))->assertNotFound();
});

it('publishes search-led service landing pages with unique metadata and FAQ schema', function (string $slug, string $title, string $heading): void {
    $landing = config("marketing.landing_pages.{$slug}");
    $response = $this->get(route('marketing.landing', $slug));

    $response
        ->assertSuccessful()
        ->assertSee('<title>'.$title.' · Your website, well looked after</title>', false)
        ->assertSee('<meta name="description" content="'.$landing['meta_description'].'">', false)
        ->assertSee($heading)
        ->assertSee('application/ld+json')
        ->assertSee('FAQPage')
        ->assertSee('Get your free search audit')
        ->assertSee('Related help');

    expect(strlen($title.' · Your website, well looked after'))->toBeLessThanOrEqual(65)
        ->and(strlen($landing['meta_description']))->toBeLessThanOrEqual(160);
})->with([
    'website management' => ['website-management-services', 'Website management services UK', 'Website management for your business.'],
    'website maintenance' => ['website-maintenance-packages', 'Website maintenance packages', 'Ongoing website maintenance for small businesses'],
    'small business support' => ['small-business-website-support', 'Small business website support', 'Website support without chasing three different suppliers'],
    'managed SEO' => ['managed-seo-services', 'Managed SEO services UK', 'SEO analysis and improvements.'],
    'small business SEO' => ['seo-for-small-businesses', 'SEO for small businesses UK', 'SEO for small businesses.'],
    'local SEO' => ['local-seo-services', 'Local SEO services UK', 'Local SEO services for businesses that depend on nearby customers'],
    'website leads' => ['website-lead-generation', 'Get more website leads', 'Turn more visitors into enquiries.'],
    'website improvement' => ['improve-my-website', 'Improve my business website', 'Make the website you already have work harder'],
]);

it('links the services hub to each search-led landing page', function (): void {
    $response = $this->get(route('marketing.features'));

    foreach (array_keys(config('marketing.landing_pages')) as $slug) {
        $response->assertSee('href="'.route('marketing.landing', $slug).'"', false);
    }
});

it('keeps landing page titles and descriptions unique', function (): void {
    $landingPages = collect(config('marketing.landing_pages'));

    expect($landingPages->pluck('seo_title')->unique())->toHaveCount($landingPages->count())
        ->and($landingPages->pluck('meta_description')->unique())->toHaveCount($landingPages->count());
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
        ->assertSee('Your website stays yours if you leave.');

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
        ->assertDontSee('2026 Growth offer:')
        ->assertDontSee('£');
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
        ->assertSee('These are illustrative scenarios')
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
        ->assertSee('Compare the working relationship')
        ->assertDontSee('VAT');
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

    $lead = FormSubmission::query()->sole();
    expect($lead->status)->toBe('new')
        ->and($lead->form?->name)->toBe('Sitewell contact form')
        ->and($lead->data['email'])->toBe('alex@example.com')
        ->and($lead->data['goals'])->toContain('health reports');
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

it('keeps the selected light video layout with one accessible player', function (): void {
    $response = $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertDontSee('Your progress,')
        ->assertSee('hideEmbedTopBar=true', false)
        ->assertSee('hide_share=true', false)
        ->assertSee('title="See how Sitewell looks after your website"', false)
        ->assertSee('loading="lazy"', false)
        ->assertSee('allowfullscreen', false)
        ->assertDontSee('href="'.route('marketing.contact').'"', false)
        ->assertDontSee('Your website.<br>The work behind it.', false)
        ->assertDontSee('https://ui.sh/ui-picker.js');

    expect(substr_count($response->getContent(), 'src="https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6?'))->toBe(1);
});

it('keeps pricing and the client list off the focused homepage', function (): void {
    $this->travelTo('2026-09-14 12:00:00 Europe/London');
    config(['memberships.plans.essential.price' => 159, 'memberships.growth_offer.price' => 320]);

    $response = $this->get(route('marketing.home'))
        ->assertSuccessful()
        ->assertDontSee('£')
        ->assertDontSee('upfront')
        ->assertDontSee('Some of the businesses we look after')
        ->assertDontSee('href="'.route('marketing.pricing').'"', false)
        ->assertDontSee('href="'.config('marketing.booking_url').'"', false)
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false)
        ->assertSee('href="'.route('marketing.terms').'"', false)
        ->assertDontSee('Every feature has a job to do for your business')
        ->assertDontSee('A calm control room for your business website');

    foreach (config('marketing.clients') as $client) {
        $response->assertDontSee($client['name'])->assertDontSee('href="'.$client['url'].'"', false);
    }

    foreach (config('memberships.plans') as $tier => $plan) {
        $response->assertDontSee('href="'.route('marketing.contact', ['plan' => $tier]).'"', false);
    }
});

it('returns to standard pricing after the growth offer expires', function (): void {
    $this->travelTo('2027-01-01 12:00:00 Europe/London');

    $this->get(route('marketing.pricing'))
        ->assertSuccessful()
        ->assertSee('£'.config('memberships.plans.growth.price'))
        ->assertDontSee('20% off')
        ->assertDontSee('£'.config('memberships.growth_offer.price'));
});

it('renders the homepage website form with configured spam protection', function (): void {
    config()->set('services.turnstile.marketing', ['enabled' => true, 'site_key' => 'homepage-key', 'secret_key' => 'test-secret']);
    $this->get(route('marketing.home'))->assertSuccessful()
        ->assertSee('Your customers search Google, ask ChatGPT and read the sites that shape AI answers.')
        ->assertSee('We find the searches that count and help you get found.')
        ->assertSee('name="website_url"', false)
        ->assertSee('name="_sitewell_check"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('data-sitekey="homepage-key"', false)
        ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
        ->assertDontSee('I don’t have a website yet')
        ->assertDontSee('href="'.route('marketing.contact').'"', false);
});

it('starts an anonymous audit directly from the homepage', function (): void {
    Queue::fake();
    config()->set('services.turnstile.marketing.enabled', false);
    $this->get(route('marketing.home', ['utm_source' => 'homepage-test']))->assertSuccessful();
    $response = $this->from(route('marketing.home'))->post(route('marketing.free-site-audit.store'), [
        'website_url' => 'example.com',
        '_sitewell_check' => '',
    ]);
    $audit = WebsiteAudit::query()->sole();
    $response->assertRedirect(route('marketing.website-audits.show', $audit));
    expect($audit->website_url)->toBe('https://example.com')
        ->and($audit->marketing_attribution['first_touch']['utm_source'])->toBe('homepage-test');
    Queue::assertPushed(GenerateWebsiteAudit::class);
});

it('returns audit validation errors and the entered address to the homepage', function (): void {
    config()->set('services.turnstile.marketing.enabled', false);
    $this->from(route('marketing.home'))->post(route('marketing.free-site-audit.store'), [
        'website_url' => 'not a website',
    ])->assertRedirect(route('marketing.home'))->assertSessionHasErrors('website_url');
    $validationErrors = session('errors');
    $response = $this->get(route('marketing.home'))->assertSuccessful()->assertSee('value="not a website"', false);
    $this->view('marketing.home', [...$response->original->getData(), 'errors' => $validationErrors])
        ->assertSee('id="hero-website-error"', false)
        ->assertSee('aria-describedby="hero-website-error"', false);
});

it('keeps the streamlined homepage focused on services proof and the audit', function (): void {
    $response = $this->get(route('marketing.home'))->assertSuccessful()
        ->assertSee('The changes your website needs.')
        ->assertSee('Sharper page copy.')
        ->assertSee('Useful new content.')
        ->assertSee('Technical fixes.')
        ->assertDontSee('Find the opportunities.')
        ->assertDontSee('Keep things moving.')
        ->assertDontSee('A clear weekly update.')
        ->assertSee('Your website stays yours if you leave.')
        ->assertDontSee('href="'.route('marketing.landing', 'website-management-services').'"', false)
        ->assertDontSee('href="'.route('marketing.article', 'how-to-find-seo-problems').'"', false)
        ->assertDontSee('Good work.')
        ->assertDontSee('The work behind a better website')
        ->assertDontSee('What could your');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//main/section')->length)->toBe(4)
        ->and($xpath->query('//form[@data-audit-form]')->length)->toBe(1)
        ->and($xpath->query('//iframe')->length)->toBe(1)
        ->and($xpath->query('//main//*[@data-home-reveal]')->length)->toBe(8)
        ->and($xpath->query('//main//form[@data-audit-form]//*[@data-home-reveal]')->length)->toBe(0)
        ->and($xpath->query('//footer')->length)->toBe(1);
});

it('uses only the audit call to action in homepage content and header', function (): void {
    $response = $this->get(route('marketing.home'))->assertSuccessful()
        ->assertDontSee('Book a call')
        ->assertDontSee('I don’t have a website yet');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    foreach ($xpath->query('//main//a | //header//a[contains(@class, "rounded-full")]') as $link) {
        expect($link->getAttribute('href'))->toBe(route('marketing.free-site-audit'))
            ->and($link->textContent)->toContain('Get your free search audit');
    }
    $this->get(route('marketing.free-site-audit'))->assertSuccessful()->assertSee('name="website_url"', false);
});
