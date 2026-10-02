<?php

use App\Services\StructuredDataAnalyzer;
use App\Services\WebsiteCrawler;
use App\Services\WebsiteHealthAuditor;

it('finds actionable on-page SEO problems without executing submitted HTML', function (): void {
    $checks = (new WebsiteHealthAuditor(new WebsiteCrawler(new StructuredDataAnalyzer)))->inspectHtml(
        '<html><head><meta name="robots" content="noindex"></head><body><h1>One</h1><h1>Two</h1><img src="photo.jpg"><script type="application/ld+json">invalid</script></body></html>',
        'https://example.com',
    );

    $checksByKey = collect($checks)->keyBy('key');

    expect($checksByKey['page_title']['status'])->toBe('failed')
        ->and($checksByKey['indexable']['status'])->toBe('failed')
        ->and($checksByKey['h1']['status'])->toBe('warning')
        ->and($checksByKey['image_alt_text']['status'])->toBe('warning')
        ->and($checksByKey['structured_data']['status'])->toBe('warning');
});

it('does not turn arbitrary metadata lengths into audit findings', function (): void {
    $title = str_repeat('Long title ', 8);
    $description = str_repeat('Long description ', 12);
    $checks = (new WebsiteHealthAuditor(new WebsiteCrawler(new StructuredDataAnalyzer)))->inspectHtml(
        '<html><head><title>'.$title.'</title><meta name="description" content="'.$description.'"></head><body><h1>Home</h1></body></html>',
        'https://example.com',
    );
    $checksByKey = collect($checks)->keyBy('key');

    expect($checksByKey['page_title']['status'])->toBe('passed')
        ->and($checksByKey['meta_description']['status'])->toBe('passed');
});

it('checks that a keyboard skip link targets the main content', function (): void {
    $auditor = new WebsiteHealthAuditor(new WebsiteCrawler(new StructuredDataAnalyzer));
    $withSkipLink = collect($auditor->inspectHtml('<html><body><a href="#content">Skip to content</a><nav>Links</nav><main id="content">Hello</main></body></html>', 'https://example.com'))->keyBy('key');
    $withoutWorkingSkipLink = collect($auditor->inspectHtml('<html><body><a href="#missing">Skip to content</a><nav>Links</nav><main id="content">Hello</main></body></html>', 'https://example.com'))->keyBy('key');
    $withoutNavigation = collect($auditor->inspectHtml('<html><body><main id="content">Hello</main></body></html>', 'https://example.com'))->keyBy('key');

    expect($withSkipLink['skip_link']['status'])->toBe('passed')
        ->and($withoutWorkingSkipLink['skip_link']['status'])->toBe('warning')
        ->and($withoutNavigation['skip_link']['status'])->toBe('passed');
});

it('turns an empty homepage response into a failed check', function (): void {
    $checks = (new WebsiteHealthAuditor(new WebsiteCrawler(new StructuredDataAnalyzer)))->inspectHtml('', 'https://example.com');

    expect($checks)->toHaveCount(1)
        ->and($checks[0]['key'])->toBe('html_parseable')
        ->and($checks[0]['status'])->toBe('failed')
        ->and($checks[0]['message'])->toContain('empty response body');
});
