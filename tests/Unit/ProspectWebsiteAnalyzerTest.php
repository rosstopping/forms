<?php

use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('runs a detailed public website audit for a prospect', function () {
    Http::fake(function (Request $request) {
        return match ($request->url()) {
            'https://example.com/contact' => Http::response('<a href="mailto:hello@example.com">Email us</a>', 200),
            'https://example.com/robots.txt' => Http::response('User-agent: *', 200),
            'https://example.com/sitemap.xml' => Http::response('<urlset></urlset>', 200),
            default => Http::response(<<<'HTML'
                <html><head><title>Example plumbing services</title><meta name="viewport" content="width=device-width, initial-scale=1"><script type="application/ld+json">{invalid}</script></head><body><h1>Reliable plumbing</h1><img src="team.jpg"><a href="/contact">Contact</a></body></html>
                HTML, 200, ['X-Content-Type-Options' => 'nosniff']),
        };
    });

    $audit = app(ProspectWebsiteAnalyzer::class)->analyze('https://example.com');

    expect($audit['score'])->toBeGreaterThan(0)
        ->and(collect($audit['findings'])->pluck('category'))->toContain('Availability & speed', 'Search essentials', 'Accessibility', 'Structured data', 'Security', 'Discoverability')
        ->and(collect($audit['findings'])->pluck('key'))->toContain('website_reachable', 'robots_txt', 'sitemap_xml', 'structured_data', 'image_alt_text')
        ->and(collect($audit['findings'])->where('severity', 'passed')->isNotEmpty())->toBeTrue()
        ->and(collect($audit['findings'])->whereIn('severity', ['warning', 'failed'])->isNotEmpty())->toBeTrue()
        ->and(data_get($audit, 'contacts.emails.0.value'))->toBe('hello@example.com');
});
