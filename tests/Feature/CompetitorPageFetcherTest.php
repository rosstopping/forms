<?php

use App\Services\CompetitorPageFetcher;
use App\Services\WebsiteCrawler;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->fetcher = Mockery::mock(CompetitorPageFetcher::class, [app(WebsiteCrawler::class)])->makePartial()->shouldAllowMockingProtectedMethods();
    $this->fetcher->shouldReceive('addresses')->andReturn(['93.184.216.34']);
});

test('competitor fetching preserves exact paths and extracts useful main content', function (): void {
    Http::fake(['https://competitor.example/robots.txt' => Http::response('User-agent: *', 200), 'https://competitor.example/guides/install/' => Http::response('<html><head><title>Installation</title></head><body><nav>Ignore menu</nav><main><h1>Installation</h1><h2>Planning</h2><p>Useful installation advice.</p></main><script>Ignore script</script></body></html>', 200, ['Content-Type' => 'text/html'])]);
    $result = $this->fetcher->fetch('https://competitor.example/guides/install/', 'competitor.example');
    expect($result['requested_url'])->toBe('https://competitor.example/guides/install/')->and($result['main_content'])->toContain('Useful installation advice.')->not->toContain('Ignore menu', 'Ignore script')->and($result['headings'])->toHaveCount(2);
    Http::assertSentCount(2);
});

test('competitor fetching refuses robots disallows redirects outside the domain and oversized bodies', function (string $scenario): void {
    Http::fake(['*/robots.txt' => Http::response($scenario === 'robots' ? "User-agent: *\nDisallow: /private" : 'User-agent: *'), 'https://competitor.example/private/' => $scenario === 'redirect' ? Http::response('', 302, ['Location' => 'http://127.0.0.1/private']) : Http::response(str_repeat('a', 1048577), 200, ['Content-Type' => 'text/html'])]);
    expect(fn () => $this->fetcher->fetch('https://competitor.example/private/', 'competitor.example'))->toThrow(RuntimeException::class);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
})->with(['robots', 'redirect', 'size']);
