<?php

use App\Services\StructuredDataAnalyzer;
use DOMDocument;
use DOMXPath;

it('validates JSON-LD graphs and reports missing recommended properties', function (): void {
    $checks = structuredDataChecks(<<<'HTML'
        <html><head><script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@graph": [
            {"@type": "Organization", "name": "Acme"},
            {"@type": "WebSite", "name": "Acme", "url": "https://example.com/"}
          ]
        }
        </script></head><body></body></html>
        HTML);
    $checksByKey = collect($checks)->keyBy('key');

    expect($checksByKey['structured_data_syntax']['status'])->toBe('passed')
        ->and($checksByKey['structured_data_syntax']['details']['types'])->toBe(['Organization', 'WebSite'])
        ->and($checksByKey['structured_data_context']['status'])->toBe('passed')
        ->and($checksByKey['structured_data_organization_0']['status'])->toBe('warning')
        ->and($checksByKey['structured_data_organization_0']['message'])->toContain('url, logo')
        ->and($checksByKey['structured_data_web_site_1']['status'])->toBe('passed')
        ->and($checksByKey)->not->toHaveKey('structured_data_opportunity_organization');
});

it('fails malformed JSON-LD without executing or rendering its contents', function (): void {
    $checks = structuredDataChecks('<html><head><script type="application/ld+json">{"@type":"Organization", invalid}</script></head><body></body></html>');
    $syntax = collect($checks)->firstWhere('key', 'structured_data_syntax');

    expect($syntax['status'])->toBe('failed')
        ->and($syntax['details']['invalid_blocks'])->toBe(1)
        ->and($syntax['message'])->toContain('invalid JSON');
});

it('only recommends schema types when visible page evidence supports them', function (): void {
    $checks = structuredDataChecks(<<<'HTML'
        <html><head><meta property="og:type" content="article"></head><body>
        <nav aria-label="Breadcrumb"><a href="/">Home</a></nav>
        <article><h1>A useful guide</h1><time>11 August 2026</time></article>
        </body></html>
        HTML, 'https://example.com/guides/useful');
    $keys = collect($checks)->pluck('key');

    expect($keys)->toContain('structured_data_opportunity_article', 'structured_data_opportunity_breadcrumb_list')
        ->and($keys)->not->toContain('structured_data_opportunity_product', 'structured_data_opportunity_organization');
});

it('recognises microdata and does not duplicate an existing schema recommendation', function (): void {
    $checks = structuredDataChecks(<<<'HTML'
        <html><body><main itemscope itemtype="https://schema.org/Product"><meta property="og:type" content="product"><h1 itemprop="name">Camera</h1></main></body></html>
        HTML, 'https://example.com/camera');
    $syntax = collect($checks)->firstWhere('key', 'structured_data_syntax');

    expect($syntax['details']['types'])->toContain('Product')
        ->and(collect($checks)->pluck('key'))->not->toContain('structured_data_opportunity_product');
});

/** @return array<int, array<string, mixed>> */
function structuredDataChecks(string $html, string $url = 'https://example.com/'): array
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);

    return (new StructuredDataAnalyzer)->analyze(new DOMXPath($document), $url);
}
