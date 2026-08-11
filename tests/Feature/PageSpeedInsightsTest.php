<?php

use App\Models\WebsiteHealthReport;
use App\Services\PageSpeedInsightsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('it collects mobile and desktop lab and real-user performance metrics', function (): void {
    config()->set('forms.pagespeed.max_pages', 1);
    config()->set('forms.pagespeed.api_key', 'test-key');
    Http::preventStrayRequests();
    Http::fake(fn (Request $request) => Http::response(pageSpeedResponse(
        str_contains($request->url(), 'strategy=mobile') ? 0.48 : 0.94,
    )));

    $result = app(PageSpeedInsightsClient::class)->audit([
        'https://example.com/',
        'https://example.com/services',
    ]);

    expect($result['pages'])->toHaveCount(2)
        ->and($result['pages'][0]['strategy'])->toBe('mobile')
        ->and($result['pages'][0]['performance_score'])->toBe(48)
        ->and($result['pages'][0]['field']['lcp_ms'])->toBe(2800)
        ->and($result['pages'][0]['field']['inp_status'])->toBe('good')
        ->and($result['pages'][0]['field']['cls'])->toBe(0.12)
        ->and($result['pages'][0]['recommendations'][0]['title'])->toBe('Eliminate render-blocking resources')
        ->and(collect($result['checks'])->firstWhere('label', 'Mobile PageSpeed performance')['status'])->toBe('failed')
        ->and(collect($result['checks'])->firstWhere('label', 'Mobile Core Web Vitals')['status'])->toBe('warning')
        ->and(collect($result['checks'])->firstWhere('label', 'Desktop PageSpeed performance')['status'])->toBe('passed');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request['key'] === 'test-key' && $request['category'] === 'performance');
});

test('it records unavailable PageSpeed checks without exposing the API response', function (): void {
    config()->set('forms.pagespeed.max_pages', 1);
    Http::preventStrayRequests();
    Http::fake(['pagespeedonline.googleapis.com/*' => Http::response(['error' => ['message' => 'secret quota detail']], 429)]);

    $result = app(PageSpeedInsightsClient::class)->audit(['https://example.com/']);

    expect($result['pages'])->toHaveCount(2)
        ->and($result['pages'][0])->not->toHaveKey('error')
        ->and($result['checks'])->toBeEmpty();
});

test('the report presents field and lab performance separately', function (): void {
    $report = WebsiteHealthReport::factory()->create([
        'metrics' => ['pagespeed' => [[
            'url' => 'https://example.com/',
            'strategy' => 'mobile',
            'available' => true,
            'performance_score' => 72,
            'field' => ['available' => true, 'lcp_ms' => 2800, 'inp_ms' => 180, 'cls' => 0.12, 'lcp_status' => 'needs_improvement', 'inp_status' => 'good', 'cls_status' => 'needs_improvement'],
            'lab' => ['lcp_ms' => 3100, 'cls' => 0.08, 'tbt_ms' => 240, 'fcp_ms' => 1400, 'speed_index_ms' => 3200],
            'recommendations' => [['title' => 'Optimise images', 'savings_ms' => 450]],
        ]]],
    ]);

    $this->actingAs($report->website->owner)
        ->get(route('admin.website-health-reports.show', [$report->website, $report]))
        ->assertSuccessful()
        ->assertSee('Real-user Core Web Vitals')
        ->assertSee('Lab measurements')
        ->assertSee('Optimise images');
});

/** @return array<string, mixed> */
function pageSpeedResponse(float $score): array
{
    return [
        'loadingExperience' => ['metrics' => [
            'LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 2800],
            'INTERACTION_TO_NEXT_PAINT' => ['percentile' => 180],
            'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['percentile' => 12],
        ]],
        'lighthouseResult' => [
            'fetchTime' => '2026-08-11T12:00:00Z',
            'finalUrl' => 'https://example.com/',
            'categories' => ['performance' => ['score' => $score]],
            'audits' => [
                'largest-contentful-paint' => ['numericValue' => 3100, 'score' => 0.6, 'scoreDisplayMode' => 'numeric', 'id' => 'largest-contentful-paint', 'title' => 'Largest Contentful Paint'],
                'cumulative-layout-shift' => ['numericValue' => 0.08, 'score' => 1, 'scoreDisplayMode' => 'numeric', 'id' => 'cumulative-layout-shift', 'title' => 'Cumulative Layout Shift'],
                'total-blocking-time' => ['numericValue' => 240, 'score' => 0.7, 'scoreDisplayMode' => 'numeric', 'id' => 'total-blocking-time', 'title' => 'Total Blocking Time'],
                'first-contentful-paint' => ['numericValue' => 1400, 'score' => 0.95, 'scoreDisplayMode' => 'numeric', 'id' => 'first-contentful-paint', 'title' => 'First Contentful Paint'],
                'speed-index' => ['numericValue' => 3200, 'score' => 0.8, 'scoreDisplayMode' => 'numeric', 'id' => 'speed-index', 'title' => 'Speed Index'],
                'render-blocking-resources' => ['numericValue' => 0, 'score' => 0.2, 'scoreDisplayMode' => 'numeric', 'id' => 'render-blocking-resources', 'title' => 'Eliminate render-blocking resources', 'description' => 'Resources are delaying the first paint.', 'details' => ['overallSavingsMs' => 450]],
            ],
        ],
    ];
}
