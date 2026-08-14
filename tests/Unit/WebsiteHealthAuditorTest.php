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

it('explains homepage metadata length warnings', function (): void {
    $title = str_repeat('Long title ', 8);
    $description = str_repeat('Long description ', 12);
    $checks = (new WebsiteHealthAuditor(new WebsiteCrawler(new StructuredDataAnalyzer)))->inspectHtml(
        '<html><head><title>'.$title.'</title><meta name="description" content="'.$description.'"></head><body><h1>Home</h1></body></html>',
        'https://example.com',
    );
    $checksByKey = collect($checks)->keyBy('key');

    expect($checksByKey['page_title']['message'])->toContain('Aim for 65 or fewer')
        ->and($checksByKey['meta_description']['message'])->toContain('Aim for 170 or fewer');
});

it('turns an empty homepage response into a failed check', function (): void {
    $checks = (new WebsiteHealthAuditor(new WebsiteCrawler(new StructuredDataAnalyzer)))->inspectHtml('', 'https://example.com');

    expect($checks)->toHaveCount(1)
        ->and($checks[0]['key'])->toBe('html_parseable')
        ->and($checks[0]['status'])->toBe('failed')
        ->and($checks[0]['message'])->toContain('empty response body');
});
