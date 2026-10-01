<?php

use App\Jobs\CheckMarketingAuditAiVisibility;
use App\Models\WebsiteAudit;
use App\Services\AiVisibilityAnalyzer;
use App\Services\MarketingAuditAiVisibility;
use App\Services\MarketingAuditCompetitors;
use App\Services\OpenAiVisibilityProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('shows a bounded comparison of real shared Google positions and reuses it', function (): void {
    Cache::flush();
    config(['services.dataforseo.login' => 'test', 'services.dataforseo.password' => 'test']);
    Http::fake([
        '*/competitors_domain/live' => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'cost' => 0.01, 'result' => [['items' => [['domain' => 'rival.example', 'intersections' => 12]]]]]]]),
        '*/domain_intersection/live' => Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'cost' => 0.02, 'result' => [['items' => [['keyword_data' => ['keyword' => 'garden offices', 'keyword_info' => ['search_volume' => 500]], 'first_domain_serp_element' => ['rank_group' => 3], 'second_domain_serp_element' => ['rank_group' => 15]]]]]]]]),
    ]);

    $service = app(MarketingAuditCompetitors::class);
    $seo = ['organic_keywords' => 42, 'location_code' => 2826, 'language_code' => 'en'];
    $result = $service->forDomain('example.com', $seo);

    expect($result['domain'])->toBe('rival.example')
        ->and($result['shared_terms'])->toBe(12)
        ->and($result['terms'][0])->toMatchArray(['term' => 'garden offices', 'our_position' => 15, 'competitor_position' => 3])
        ->and($service->forDomain('example.com', $seo))->toBe($result);
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'domain_intersection/live') && $request->data()[0]['limit'] === 5);
});

it('records only observed AI mentions and citations for one unbranded question', function (): void {
    Cache::flush();
    $provider = Mockery::mock(OpenAiVisibilityProvider::class);
    $provider->shouldReceive('model')->twice()->andReturn('test-model');
    $provider->shouldReceive('check')->once()->andReturn(['text' => 'See https://example.com/garden-offices', 'citations' => [['url' => 'https://example.com/garden-offices']]]);
    $service = new MarketingAuditAiVisibility($provider, app(AiVisibilityAnalyzer::class));
    $question = $service->question('example.com', ['keywords' => [['term' => 'example garden offices'], ['term' => 'garden office fitters']]]);

    expect($question)->toBe('Which businesses would you recommend for garden office fitters?');
    expect($service->questions('example.com', ['keywords' => [['term' => 'example garden offices'], ['term' => 'garden office fitters'], ['term' => 'garden office builders'], ['term' => 'office pods uk']]]))
        ->toBe(['Which businesses would you recommend for garden office fitters?', 'Which businesses would you recommend for garden office builders?']);
    $result = $service->check('example.com', $question);
    expect($result)->toMatchArray(['status' => 'completed', 'website_mentioned' => true, 'website_cited' => true, 'provider' => 'OpenAI']);
    expect($service->check('example.com', $question))->toBe($result);
});

it('finishes a public AI check without blocking the saved audit', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'insights' => ['ai_visibility' => ['status' => 'pending', 'questions' => ['Which businesses would you recommend for garden offices?', 'Which businesses would you recommend for garden office fitters?']]]]);
    $service = Mockery::mock(MarketingAuditAiVisibility::class);
    $service->shouldReceive('available')->once()->andReturn(true);
    $service->shouldReceive('check')->twice()->andReturn(['status' => 'completed', 'website_mentioned' => false, 'website_cited' => false, 'checked_at' => now()->toIso8601String()]);

    (new CheckMarketingAuditAiVisibility($audit))->handle($service);

    expect(data_get($audit->fresh()->insights, 'ai_visibility.status'))->toBe('completed')
        ->and(data_get($audit->fresh()->insights, 'ai_visibility.results.0.website_cited'))->toBeFalse()
        ->and(data_get($audit->fresh()->insights, 'ai_visibility.results.1.website_cited'))->toBeFalse();
});

it('renders competitor positions and a sampled AI citation on the public report', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'findings' => [['severity' => 'passed']],
        'insights' => [
            'health_score' => 100,
            'pages_listed' => 3,
            'seo' => ['location_code' => 2826, 'language_code' => 'en', 'retrieved_at' => now()->toIso8601String(), 'organic_keywords' => 8, 'top_3_keywords' => 0, 'top_10_keywords' => 2, 'top_20_keywords' => 4, 'estimated_monthly_visits' => 12, 'referring_domains' => 2, 'sample_size' => 0, 'keywords' => []],
            'competitors' => ['domain' => 'rival.example', 'shared_terms' => 3, 'terms' => [['term' => 'garden offices', 'our_position' => 15, 'competitor_position' => 3]], 'others' => [['domain' => 'second.example', 'shared_terms' => 2]], 'retrieved_at' => now()->toIso8601String()],
            'ai_visibility' => ['status' => 'completed', 'questions' => ['Which businesses would you recommend for garden offices?'], 'results' => [['status' => 'completed', 'website_mentioned' => true, 'website_cited' => true, 'checked_at' => now()->toIso8601String()]]],
        ],
    ]);

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('rival.example')
        ->assertSee('second.example')
        ->assertSee('garden offices')
        ->assertSee('Your website was cited.');
});
