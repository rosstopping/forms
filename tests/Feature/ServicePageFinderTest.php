<?php

use App\Ai\Agents\ServicePageCoverageAnalyst;
use App\Jobs\FindMissingServicePages;
use App\Services\CompetitorPageFetcher;
use App\Services\ServicePageFinder;
use App\Services\WebsiteCrawler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['services.turnstile.marketing.enabled' => false]);
});

function finderPages(): array
{
    return [
        ['id' => 0, 'url' => 'https://example.com/', 'title' => 'Heating engineers', 'headings' => [], 'text' => 'We provide boiler servicing and emergency repairs across Doncaster.'],
        ['id' => 1, 'url' => 'https://example.com/servicing/', 'title' => 'Boiler servicing', 'headings' => [], 'text' => 'Our boiler servicing includes a full inspection.'],
    ];
}

function finderService(string $name = 'Emergency repairs', string $quote = 'We provide boiler servicing and emergency repairs across Doncaster.'): array
{
    return ['service' => $name, 'evidence_page_id' => 0, 'quote' => $quote, 'dedicated_page_ids' => [], 'suggested_title' => $name, 'questions' => ['When can an engineer attend?']];
}

it('renders a Digizu address-only form without running research', function (): void {
    Queue::fake();
    $this->get('/tools/missing-service-pages')->assertOk()->assertSee('Find missing pages')->assertSee('Digizu')->assertDontSee('name="email"', false)->assertDontSee('name="keyword"', false);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('normalises addresses and reuses pending checks', function (): void {
    Queue::fake();
    $first = $this->post('/tools/missing-service-pages', ['website_url' => 'example.com/services'])->assertRedirect();
    $second = $this->post('/tools/missing-service-pages', ['website_url' => 'https://example.com/'])->assertRedirect();
    expect($second->headers->get('Location'))->toBe($first->headers->get('Location'));
    Queue::assertPushed(FindMissingServicePages::class, fn ($job): bool => $job->url === 'https://example.com/');
    Queue::assertPushed(FindMissingServicePages::class, 1);
    $this->get($first->headers->get('Location'))->assertOk()->assertSee('Reading your website.');
});

it('rejects dangerous addresses before queueing', function (string $url): void {
    Queue::fake();
    $this->post('/tools/missing-service-pages', ['website_url' => $url])->assertSessionHasErrors('website_url');
    Queue::assertNothingPushed();
})->with(['http://127.0.0.1/', 'http://[::1]/', 'http://localhost/', 'https://user:pass@example.com', 'https://example.com:8443', 'file:///etc/passwd']);

it('enforces honeypot and security challenge', function (): void {
    Queue::fake();
    $this->post('/tools/missing-service-pages', ['website_url' => 'example.com', '_sitewell_check' => 'bot'])->assertSessionHasErrors('_sitewell_check');
    config(['services.turnstile.marketing.enabled' => true, 'services.turnstile.marketing.site_key' => 'key', 'services.turnstile.marketing.secret_key' => 'secret']);
    $this->post('/tools/missing-service-pages', ['website_url' => 'example.com'])->assertSessionHasErrors('website_url');
    Queue::assertNothingPushed();
});

it('rejects invented evidence, deduplicates suggestions and suppresses covered services', function (): void {
    $report = app(ServicePageFinder::class)->report(finderPages(), ['supported' => true, 'services' => [finderService(), finderService('Boiler servicing'), finderService('Invented', 'We offer invented services every day.'), finderService()]], true);
    expect($report['suggestions'])->toHaveCount(1)->and($report['suggestions'][0]['source_url'])->toBe('https://example.com/')
        ->and($report['covered'])->toHaveCount(1)->and($report['covered'][0]['url'])->toBe('https://example.com/servicing/')->and($report['partial'])->toBeTrue();
});

it('uses semantic coverage and rejects fabricated page IDs', function (): void {
    $covered = [...finderService('Annual boiler check'), 'dedicated_page_ids' => [1]];
    $invalid = [...finderService(), 'evidence_page_id' => 999];
    $report = app(ServicePageFinder::class)->report(finderPages(), ['supported' => true, 'services' => [$covered, $invalid]], false);
    expect($report['suggestions'])->toBeEmpty()->and($report['covered'])->toHaveCount(1);
    expect(app(ServicePageFinder::class)->report(finderPages(), ['supported' => false, 'services' => [finderService()]], false)['suggestions'])->toBeEmpty();
});

it('fetches only eligible own-site pages through the existing fetcher', function (): void {
    $fetcher = Mockery::mock(CompetitorPageFetcher::class);
    $fetcher->shouldReceive('sitemapLinks')->once()->andReturn([]);
    $fetcher->shouldReceive('fetch')->once()->with('https://example.com/', 'example.com', true)->andReturn(['final_url' => 'https://example.com/', 'title' => 'Heating engineers', 'main_content' => finderPages()[0]['text'], 'discovered_links' => ['https://example.com/servicing/', 'https://elsewhere.example/', 'https://example.com/checkout/', 'https://example.com/list.pdf', 'https://example.com/?page=2']]);
    $fetcher->shouldReceive('fetch')->once()->with('https://example.com/servicing/', 'example.com', true)->andReturn(['final_url' => 'https://example.com/servicing/', 'title' => 'Boiler servicing', 'main_content' => finderPages()[1]['text'], 'discovered_links' => []]);
    ServicePageCoverageAnalyst::fake([['supported' => true, 'services' => [finderService()]]])->preventStrayPrompts();
    $finder = new ServicePageFinder($fetcher);
    $report = $finder->cachedFind('https://example.com/');
    expect($finder->cachedFind('https://example.com/'))->toBe($report);
    expect($report['pages'])->toHaveCount(2)->and($report['suggestions'])->toHaveCount(1);
});

it('stores job results once and renders reports without more research', function (): void {
    $id = '00000000-0000-4000-8000-000000000001';
    Cache::put('service-page-finder:'.$id, ['status' => 'queued', 'url' => 'https://example.com/', 'started_at' => now()->timestamp]);
    $finder = Mockery::mock(ServicePageFinder::class);
    $finder->shouldReceive('cachedFind')->once()->andReturn(app(ServicePageFinder::class)->report(finderPages(), ['supported' => true, 'services' => [finderService()]], true));
    $job = new FindMissingServicePages($id, 'https://example.com/');
    $job->handle($finder);
    $job->handle($finder);
    $this->get('/tools/missing-service-pages/'.$id)->assertOk()->assertSee('Emergency repairs')->assertSee('noindex, nofollow')->assertDontSee('Sitewell');
    $this->getJson('/tools/missing-service-pages/'.$id.'/status')->assertExactJson(['status' => 'completed']);
    Http::assertNothingSent();
});

it('handles failed, empty, unsupported, expired and abandoned checks', function (): void {
    $id = '00000000-0000-4000-8000-000000000002';
    $this->get('/tools/missing-service-pages/'.$id)->assertNotFound();
    Cache::put('service-page-finder:'.$id, ['status' => 'queued', 'url' => 'https://example.com/', 'started_at' => now()->subMinutes(11)->timestamp]);
    $this->getJson('/tools/missing-service-pages/'.$id.'/status')->assertExactJson(['status' => 'failed']);
    (new FindMissingServicePages($id, 'https://example.com/'))->failed(new RuntimeException('private diagnostic'));
    $this->get('/tools/missing-service-pages/'.$id)->assertOk()->assertSee('We couldn’t complete')->assertDontSee('private diagnostic');
    foreach ([true, false] as $supported) {
        Cache::put('service-page-finder:'.$id, ['status' => 'completed', 'url' => 'https://example.com/', 'result' => app(ServicePageFinder::class)->report(finderPages(), ['supported' => $supported, 'services' => []], false)]);
        $this->get('/tools/missing-service-pages/'.$id)->assertOk()->assertSee($supported ? 'No clear gaps' : 'Not enough service information');
    }
});

it('escapes untrusted report content', function (): void {
    $id = '00000000-0000-4000-8000-000000000003';
    $pages = finderPages();
    $pages[0]['text'] = 'We provide <script>alert(1)</script> emergency repairs.';
    $result = app(ServicePageFinder::class)->report($pages, ['supported' => true, 'services' => [finderService('Emergency repairs', $pages[0]['text'])]], false);
    Cache::put('service-page-finder:'.$id, ['status' => 'completed', 'url' => 'https://example.com/', 'result' => $result]);
    $this->get('/tools/missing-service-pages/'.$id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('bounds sitemap reads and rejects external sitemap destinations', function (): void {
    $fetcher = Mockery::mock(CompetitorPageFetcher::class, [app(WebsiteCrawler::class)])->makePartial()->shouldAllowMockingProtectedMethods();
    $fetcher->shouldReceive('addresses')->andReturn(['93.184.216.34']);
    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<sitemapindex><sitemap><loc>https://example.com/services.xml</loc></sitemap><sitemap><loc>http://127.0.0.1/private.xml</loc></sitemap><sitemap><loc>https://elsewhere.example/secret.xml</loc></sitemap></sitemapindex>'),
        'https://example.com/services.xml' => Http::response('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/boiler-servicing/</loc></url><url><loc>https://other.example/private</loc></url></urlset>'),
    ]);
    expect($fetcher->sitemapLinks('https://example.com/', 'example.com'))->toBe(['https://example.com/boiler-servicing/']);
    Http::assertSentCount(2);
});

it('does not resolve XML external entities', function (): void {
    $fetcher = Mockery::mock(CompetitorPageFetcher::class, [app(WebsiteCrawler::class)])->makePartial()->shouldAllowMockingProtectedMethods();
    $fetcher->shouldReceive('addresses')->andReturn(['93.184.216.34']);
    Http::fake(['https://example.com/sitemap.xml' => Http::response('<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><urlset><url><loc>&xxe;</loc></url></urlset>')]);
    expect($fetcher->sitemapLinks('https://example.com/', 'example.com'))->toBe([]);
    Http::assertSentCount(1);
});

it('limits a single check to eight fetched pages and reports partial coverage', function (): void {
    $fetcher = Mockery::mock(CompetitorPageFetcher::class);
    $fetcher->shouldReceive('sitemapLinks')->once()->andReturn(array_map(fn ($n) => 'https://example.com/service-'.$n.'/', range(1, 20)));
    $fetcher->shouldReceive('fetch')->times(8)->andReturnUsing(fn ($url) => ['final_url' => $url, 'title' => 'Services', 'main_content' => finderPages()[0]['text'], 'discovered_links' => []]);
    ServicePageCoverageAnalyst::fake([['supported' => true, 'services' => []]])->preventStrayPrompts();
    $result = (new ServicePageFinder($fetcher))->find('https://example.com/');
    expect($result['pages'])->toHaveCount(8)->and($result['partial'])->toBeTrue();
});

it('rate limits paid research submissions', function (): void {
    Queue::fake();
    for ($n = 0; $n < 5; $n++) {
        $this->post('/tools/missing-service-pages', ['website_url' => 'example.com'])->assertRedirect();
    }
    $this->post('/tools/missing-service-pages', ['website_url' => 'example.com'])->assertStatus(429);
    Queue::assertPushed(FindMissingServicePages::class, 1);
});

it('does not mistake a homepage or case study for a dedicated service page', function (): void {
    $pages = finderPages();
    $pages[] = ['id' => 2, 'url' => 'https://example.com/case-studies/emergency-repairs/', 'title' => 'Emergency repairs', 'headings' => [], 'text' => 'An example project.'];
    $service = [...finderService(), 'dedicated_page_ids' => [0, 2]];
    $result = app(ServicePageFinder::class)->report($pages, ['supported' => true, 'services' => [$service]], false);
    expect($result['suggestions'])->toHaveCount(1)->and($result['covered'])->toBeEmpty();
});
