<?php

use App\Jobs\GenerateWebsiteAudit;
use App\Mail\OnboardingEnquiryReceived;
use App\Models\FormSubmission;
use App\Models\Website;
use App\Models\WebsiteAudit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('publishes eighteen distinct agency pages with metadata, breadcrumbs and sitemap coverage', function (): void {
    $pages = config('agencies.pages');
    expect($pages)->toHaveCount(17);
    $sitemap = $this->get(route('marketing.sitemap'))->assertSuccessful();
    $hubUrl = route('marketing.agencies');
    $hub = $this->get($hubUrl)->assertSuccessful()->assertSee('We’ll do the work.');
    $sitemap->assertSee('<loc>'.$hubUrl.'</loc>', false);
    $titles = $introductions = $descriptions = $headings = [];
    foreach ($pages as $slug => $page) {
        $url = route('marketing.agencies.show', $slug);
        $response = $this->get($url.'?utm_source=test')->assertSuccessful();
        $html = $response->getContent();
        $response->assertSee('<title>'.e($page['title']).'</title>', false)
            ->assertSee('<meta name="description" content="'.e($page['description']).'">', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('<meta property="og:url" content="'.$url.'">', false)
            ->assertSee('<meta property="og:title" content="'.e($page['title']).'">', false)
            ->assertSee($page['heading'])->assertSee($page['intro'])
            ->assertSee('href="'.$hubUrl.'#join-beta"', false)
            ->assertDontSee('noindex');
        expect(preg_match_all('/<h1\b/i', $html))->toBe(1);
        foreach ($page['sections'] as $section) {
            $response->assertSee($section['heading'])->assertSee($section['body']);
        }
        foreach ($page['faqs'] as [$question, $answer]) {
            $response->assertSee($question)->assertSee($answer);
        }
        foreach ($page['related'] as $related) {
            expect($pages)->toHaveKey($related);
            $response->assertSee('href="'.route('marketing.agencies.show', $related).'"', false);
        }
        $hub->assertSee('href="'.$url.'"', false);
        $sitemap->assertSee('<loc>'.$url.'</loc>', false);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $schema = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        expect($schema['@graph'][0]['@type'])->toBe($page['kind'] === 'article' ? 'Article' : 'WebPage')
            ->and($schema['@graph'][1]['@type'])->toBe('BreadcrumbList')
            ->and($schema['@graph'][1]['itemListElement'][2]['item'])->toBe($url);
        $titles[] = $page['title'];
        $introductions[] = $page['intro'];
        $descriptions[] = $page['description'];
        $headings[] = $page['heading'];
    }
    foreach ([$titles, $introductions, $descriptions, $headings] as $values) {
        expect(array_unique($values))->toHaveCount(17);
    }
});

it('keeps the flagged agency page titles within search result length guidance', function (): void {
    foreach ([
        'white-label-seo' => 'White-label SEO: your clients, your service | Sitewell',
        'seo-reporting' => 'Agency SEO reporting: explain the work and results | Sitewell',
    ] as $slug => $title) {
        $this->get(route('marketing.agencies.show', $slug))
            ->assertSuccessful()
            ->assertSee('<title>'.e($title).'</title>', false);

        expect(mb_strlen($title))->toBeLessThanOrEqual(65);
    }
});

it('presents the managed agency service with existing booking and honest availability', function (): void {
    $home = $this->get(route('marketing.home'))->assertSuccessful()->getContent();
    expect(substr_count($home, 'href="'.route('marketing.agencies').'"'))->toBe(0);
    $this->get(route('marketing.agencies'))->assertSuccessful()
        ->assertSee('What’s included?')->assertSee('Video coming soon')
        ->assertDontSee('fictional businesses and data')->assertDontSee('Join the agency beta')
        ->assertSee('Up to three scheduled content improvements per week')
        ->assertSee('data-audit-book-call', false)
        ->assertSee('marketing-events')
        ->assertSee('href="'.route('marketing.ppc.book').'"', false)
        ->assertDontSee('action="'.route('marketing.agencies.store').'"', false)
        ->assertSee('id="join-beta"', false)->assertSee('id="how-it-works"', false)
        ->assertSee('id="available-now"', false);
    $this->get(route('marketing.agencies.show', 'seo-reporting'))->assertSuccessful()
        ->assertSee('Agency-branded reporting is proposed');
    $this->get('/agencies/not-a-real-page')->assertNotFound();
    $this->get('/agencies/pages.white-label-seo')->assertNotFound();
});

it('stores beta interest separately in existing Leads and queues an internal notification', function (): void {
    Mail::fake();
    $website = Website::factory()->create();
    config(['marketing.contact_website_id' => $website->id]);
    $this->from(route('marketing.agencies'))->post(route('marketing.agencies.store'), [
        'name' => 'Alex', 'agency' => 'Example Studio', 'email' => 'alex@example.com',
        'website' => 'https://example.com', 'client_websites' => 32, 'offers_seo' => 'yes',
        'goals' => 'Help with client reporting.', 'type' => 'attacker-supplied', 'website_id' => 999,
    ])->assertRedirect(route('marketing.agencies').'#join-beta')->assertSessionHas('agency_status');
    $lead = FormSubmission::query()->sole();
    expect($lead->website_id)->toBe($website->id)
        ->and($lead->form->slug)->toBe('sitewell-agency-beta')
        ->and($lead->source_url)->toBe(route('marketing.agencies'))
        ->and($lead->data['client_websites'])->toBe(32)
        ->and($lead->data['offers_seo'])->toBe('yes')
        ->and($lead->data['type'])->toBe('agency_beta')
        ->and($lead->data)->not->toHaveKey('website_id');
    Mail::assertQueued(OnboardingEnquiryReceived::class, function (OnboardingEnquiryReceived $mail): bool {
        expect($mail->render())->toContain('Client websites:', '32', 'Currently offers SEO:');

        return $mail->hasTo(config('forms.default_recipient'))
            && $mail->hasReplyTo('alex@example.com')
            && str_contains($mail->envelope()->subject, 'agency beta');
    });
    Mail::assertQueuedCount(1);
});

it('accepts a prelaunch agency and optional notes without creating a user or subscription', function (): void {
    Mail::fake();
    $this->post(route('marketing.agencies.store'), [
        'name' => 'Alex', 'agency' => 'New Studio', 'email' => 'alex@example.com',
        'website' => 'https://example.com', 'client_websites' => 0, 'offers_seo' => 'no',
    ])->assertSessionHasNoErrors()->assertSessionHas('agency_status');
    expect(FormSubmission::query()->sole()->data['goals'])->toBe('No additional notes supplied.');
    $this->assertDatabaseCount('users', 0);
});

it('rejects invalid beta requests without storing leads or sending mail', function (string $field, mixed $value): void {
    Mail::fake();
    $this->from(route('marketing.agencies'))->post(route('marketing.agencies.store'), array_replace([
        'name' => 'Alex', 'agency' => 'Example Studio', 'email' => 'alex@example.com',
        'website' => 'https://example.com', 'client_websites' => 10, 'offers_seo' => 'no',
    ], [$field => $value]))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('form_submissions', 0);
    Mail::assertNothingOutgoing();
})->with([
    ['name', ''], ['agency', ''], ['email', 'invalid'], ['website', 'javascript:alert(1)'],
    ['client_websites', -1], ['client_websites', 1.5], ['offers_seo', 'maybe'],
    ['goals', str_repeat('x', 3001)], ['_sitewell_check', 'bot'], ['_honeypot', 'bot'],
]);

it('renders accessible validation feedback and keeps entered values', function (): void {
    $this->from(route('marketing.agencies'))->post(route('marketing.agencies.store'), [
        'name' => 'A & B', 'email' => 'invalid',
    ])->assertSessionHasErrors(['agency', 'email']);
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get(route('marketing.agencies'))->assertSuccessful()
        ->assertSee('role="alert"', false)->assertSee('value="A &amp; B"', false)
        ->assertSee('aria-describedby="beta-email-error"', false)
        ->assertSee('id="agency-enquiry"', false)
        ->assertDontSee('suitable pilot');
});

it('rate limits public beta submissions', function (): void {
    Mail::fake();
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post(route('marketing.agencies.store'), [])->assertSessionHasErrors();
    }
    $this->post(route('marketing.agencies.store'), [])->assertTooManyRequests();
    Mail::assertNothingOutgoing();
});

it('keeps the managed service metadata and schema consistent without unconfirmed prices', function (): void {
    $response = $this->get(route('marketing.agencies'))->assertSuccessful()
        ->assertSee('<title>SEO outsourcing for web agencies | Sitewell</title>', false)
        ->assertDontSee('£150')->assertDontSee('£316')->assertDontSee('agency beta');
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $schema = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    expect($schema['@graph'][0]['name'])->toBe('Offer SEO to your clients. We’ll do the work.')
        ->and($schema['@graph'][0]['description'])->toContain('Fully managed SEO')
        ->and(substr_count($response->getContent(), 'id="join-beta"'))->toBe(1);
});

it('offers the homepage style audit form for agency clients with attribution and spam protection', function (): void {
    config(['services.turnstile.marketing.enabled' => true, 'services.turnstile.marketing.site_key' => 'agency-site-key', 'services.turnstile.marketing.secret_key' => 'secret']);
    $this->get(route('marketing.agencies').'?utm_source=agency-outreach')->assertSuccessful()
        ->assertSee('id="agency-hero"', false)
        ->assertSee('Try a client’s website to see where we’d start.')
        ->assertSee('action="'.route('marketing.free-site-audit.store').'"', false)
        ->assertSee('name="website_url"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="_sitewell_check"', false)
        ->assertSee('data-audit-form', false)
        ->assertSee('data-sitekey="agency-site-key"', false)
        ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false);
    expect(session('marketing_attribution.landing_page'))->toBe('agencies')
        ->and(session('marketing_attribution.first_touch.utm_source'))->toBe('agency-outreach');
});

it('submits an agency client website through the existing audit flow', function (): void {
    Queue::fake([GenerateWebsiteAudit::class]);
    config(['services.turnstile.marketing.enabled' => false]);
    $this->get(route('marketing.agencies'))->assertSuccessful();
    $this->from(route('marketing.agencies'))->post(route('marketing.free-site-audit.store'), ['website_url' => 'client.example'])->assertRedirect();
    $audit = WebsiteAudit::query()->sole();
    expect($audit->domain)->toBe('client.example')
        ->and($audit->marketing_attribution['landing_page'])->toBe('agencies');
    Queue::assertPushed(GenerateWebsiteAudit::class, fn ($job): bool => $job->audit->is($audit));
});

it('shows audit validation on the agency hero without displaying the legacy enquiry form', function (): void {
    $this->from(route('marketing.agencies'))->post(route('marketing.free-site-audit.store'), ['website_url' => 'not a website'])->assertRedirect(route('marketing.agencies'))->assertSessionHasErrors('website_url');
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get(route('marketing.agencies'))->assertSuccessful()
        ->assertSee('id="hero-website-error"', false)
        ->assertSee('value="not a website"', false)
        ->assertDontSee('id="agency-enquiry"', false);
});

it('shows the homepage search and AI logos beneath the agency hero', function (): void {
    $response = $this->get(route('marketing.agencies'))->assertSuccessful()
        ->assertSee('Where your clients’ websites should show up.')
        ->assertSee('aria-label="Search platforms"', false);
    foreach (['google', 'bing', 'openai', 'gemini', 'perplexity', 'claude'] as $mark) {
        $response->assertSee('src="'.asset('search-'.$mark.'.svg').'"', false);
    }
});

it('keeps the agency page concise after the three steps while preserving service scope and branding clarity', function (): void {
    $this->get(route('marketing.agencies'))->assertSuccessful()
        ->assertSee('Send me the website. I’ll take it from there.')
        ->assertSee('Up to three scheduled content improvements per week, prepared for review')
        ->assertSee('clear weekly reports')
        ->assertSee('Dashboards and reports will include your logo and branding.')
        ->assertDontSee('custom branding and domains aren’t included')
        ->assertDontSee('The work behind your SEO service.')
        ->assertDontSee('A monthly service for clients who already trust you.')
        ->assertDontSee('A few useful answers.')
        ->assertDontSee('Your clients. Your pricing.');
});
