<?php

use App\Models\Website;
use App\Services\ProspectContactFinder;
use App\Services\ProspectWebsiteAnalyzer;
use App\Services\WebsiteCrawler;
use App\Services\WebsiteHealthAuditor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** @return array<string, mixed> */
function sitemapAuditFinding(string $auditType): array
{
    if ($auditType === 'public') {
        test()->mock(ProspectContactFinder::class)->shouldReceive('find')->once()->andReturn([]);
        $audit = app(ProspectWebsiteAnalyzer::class)->analyze('https://example.com');

        return collect($audit['findings'])->firstWhere('key', 'sitemap_xml');
    }

    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    test()->mock(WebsiteCrawler::class)->shouldReceive('crawl')->once()->andReturn([]);
    $audit = app(WebsiteHealthAuditor::class)->audit($website);

    return collect($audit['checks'])->firstWhere('key', 'sitemap_xml');
}

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('accepts sitemap redirects to a successful WordPress sitemap index', function (string $auditType, int $status, string $location): void {
    Http::fake([
        'https://example.com' => Http::response('<html><body>Homepage</body></html>'),
        'https://example.com/robots.txt' => Http::response('User-agent: *'),
        'https://example.com/sitemap.xml' => Http::response('', $status, ['Location' => $location]),
        'https://example.com/sitemap_index.xml' => Http::response('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>', 200),
    ]);

    $finding = sitemapAuditFinding($auditType);

    expect($finding[$auditType === 'public' ? 'severity' : 'status'])->toBe('passed');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.com/sitemap_index.xml');
})->with(['public', 'health'])->with([
    [301, '/sitemap_index.xml'],
    [301, 'https://example.com/sitemap_index.xml'],
    [302, 'sitemap_index.xml'],
    [303, '/sitemap_index.xml'],
    [307, '//example.com/sitemap_index.xml'],
    [308, '/sitemap_index.xml'],
]);

it('uses the final sitemap response in a redirect chain', function (string $auditType, int $finalStatus): void {
    Http::fake([
        'https://example.com' => Http::response('<html><body>Homepage</body></html>'),
        'https://example.com/robots.txt' => Http::response('User-agent: *'),
        'https://example.com/sitemap.xml' => Http::response('', 301, ['Location' => '/maps/current.xml']),
        'https://example.com/maps/current.xml' => Http::response('', 302, ['Location' => '../sitemap_index.xml']),
        'https://example.com/sitemap_index.xml' => Http::response('<sitemapindex></sitemapindex>', $finalStatus),
    ]);

    $finding = sitemapAuditFinding($auditType);

    expect($finding[$auditType === 'public' ? 'severity' : 'status'])->toBe($finalStatus === 200 ? 'passed' : 'warning');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.com/sitemap_index.xml');
})->with(['public', 'health'])->with([200, 404, 500]);

it('does not follow unsafe missing or looping sitemap redirect locations', function (string $auditType, string $location): void {
    Http::fake([
        'https://example.com' => Http::response('<html><body>Homepage</body></html>'),
        'https://example.com/robots.txt' => Http::response('User-agent: *'),
        'https://example.com/sitemap.xml' => Http::response('', 301, ['Location' => $location]),
    ]);

    $finding = sitemapAuditFinding($auditType);

    expect($finding[$auditType === 'public' ? 'severity' : 'status'])->toBe('warning');
    Http::assertSentCount(3);
})->with(['public', 'health'])->with([
    '', '/sitemap.xml', 'http://127.0.0.1/sitemap.xml', 'file:///etc/passwd',
    'https://example.com:8080/sitemap.xml', 'https://user:password@example.com/sitemap.xml',
]);

it('bounds sitemap redirect chains', function (string $auditType): void {
    $sitemapRequests = 0;
    Http::fake(function (Request $request) use (&$sitemapRequests) {
        if (str_contains($request->url(), 'sitemap')) {
            $sitemapRequests++;

            return Http::response('', 301, ['Location' => '/sitemap-'.$sitemapRequests.'.xml']);
        }

        return Http::response('<html><body>Homepage</body></html>');
    });

    $finding = sitemapAuditFinding($auditType);

    expect($finding[$auditType === 'public' ? 'severity' : 'status'])->toBe('warning')
        ->and($sitemapRequests)->toBe(6);
})->with(['public', 'health']);

it('warns when a redirected sitemap cannot be reached', function (string $auditType): void {
    Http::fake([
        'https://example.com' => Http::response('<html><body>Homepage</body></html>'),
        'https://example.com/robots.txt' => Http::response('User-agent: *'),
        'https://example.com/sitemap.xml' => Http::response('', 301, ['Location' => '/sitemap_index.xml']),
        'https://example.com/sitemap_index.xml' => Http::failedConnection(),
    ]);

    $finding = sitemapAuditFinding($auditType);

    expect($finding[$auditType === 'public' ? 'severity' : 'status'])->toBe('warning');
})->with(['public', 'health']);
