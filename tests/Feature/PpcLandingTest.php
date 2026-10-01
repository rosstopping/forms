<?php

use Illuminate\Support\Carbon;

it('renders each tailored PPC page without prices and with the existing audit action', function (string $page): void {
    $this->travelTo(Carbon::parse('2026-09-30'));
    $landing = config('ppc.pages.'.$page);
    $response = $this->get(route('marketing.ppc.'.$page));

    $response->assertSuccessful()
        ->assertSee($landing['heading'])
        ->assertSee($landing['problem_title'])
        ->assertSee($landing['example'])
        ->assertDontSee('£')
        ->assertDontSee('Current Growth offer')
        ->assertDontSee('No setup fee')
        ->assertSee('No long-term contract')
        ->assertSee('FAQPage')
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false)
        ->assertSee('href="'.route('marketing.ppc.book').'"', false)
        ->assertDontSee('<iframe', false)
        ->assertDontSee('googletagmanager.com')
        ->assertDontSee('dataLayer');

    expect(substr_count($response->getContent(), '<h1 '))->toBe(1);
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $match);
    $schema = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    expect($schema['@context'])->toBe('https://schema.org');
    foreach ($schema['mainEntity'] as $faq) {
        $response->assertSee($faq['name'])->assertSee($faq['acceptedAnswer']['text']);
    }
    $canonical = isset($landing['canonical_landing'])
        ? route('marketing.landing', $landing['canonical_landing'])
        : route('marketing.ppc.'.$page);
    $response->assertSee('<link rel="canonical" href="'.$canonical.'">', false);
})->with(['doncaster', 'local', 'small-business', 'managed', 'rankings']);

it('keeps PPC pages free of prices after the configured offer expires', function (): void {
    $this->travelTo(Carbon::parse('2027-01-01'));
    $this->get(route('marketing.ppc.managed'))->assertSuccessful()
        ->assertDontSee('£')
        ->assertDontSee('Current Growth offer')
        ->assertSee('href="'.route('marketing.free-site-audit').'"', false);
});

it('connects PPC pages to the service hub and only adds canonical pages to the sitemap', function (): void {
    $hub = $this->get(route('marketing.features'))->assertSuccessful();
    $sitemap = $this->get(route('marketing.sitemap'))->assertSuccessful();
    foreach (config('ppc.pages') as $key => $page) {
        $hub->assertSee(route('marketing.ppc.'.$key));
        if (! isset($page['canonical_landing'])) {
            $sitemap->assertSee('<loc>'.route('marketing.ppc.'.$key).'</loc>', false);
        } else {
            $sitemap->assertDontSee('<loc>'.route('marketing.ppc.'.$key).'</loc>', false);
        }
    }
    $this->get('/seo/not-a-real-page')->assertNotFound();
});
