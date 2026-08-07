<?php

use App\Services\WebsiteCrawler;
use App\Services\WebsiteHealthAuditor;

it('finds actionable on-page SEO problems without executing submitted HTML', function (): void {
    $checks = (new WebsiteHealthAuditor(new WebsiteCrawler))->inspectHtml(
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
