<?php

use App\Mail\OnboardingEnquiryReceived;
use App\Models\FormSubmission;
use App\Models\Website;
use Illuminate\Support\Facades\Mail;

it('publishes eighteen distinct agency pages with metadata, breadcrumbs and sitemap coverage', function (): void {
    $pages = config('agencies.pages');
    expect($pages)->toHaveCount(17);
    $sitemap = $this->get(route('marketing.sitemap'))->assertSuccessful();
    $hubUrl = route('marketing.agencies');
    $hub = $this->get($hubUrl)->assertSuccessful()->assertSee('Without building an SEO team.');
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

it('keeps the agency hub available outside navigation and labels proposed previews honestly', function (): void {
    $home = $this->get(route('marketing.home'))->assertSuccessful()->getContent();
    expect(substr_count($home, 'href="'.route('marketing.agencies').'"'))->toBe(0);
    $this->get(route('marketing.agencies'))->assertSuccessful()
        ->assertSee('Agency beta / planned direction')->assertSee('fictional businesses and data')
        ->assertSee('current customer access uses individual website workspaces')
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
        ->assertSee('aria-describedby="beta-email-error"', false);
});

it('rate limits public beta submissions', function (): void {
    Mail::fake();
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post(route('marketing.agencies.store'), [])->assertSessionHasErrors();
    }
    $this->post(route('marketing.agencies.store'), [])->assertTooManyRequests();
    Mail::assertNothingOutgoing();
});
