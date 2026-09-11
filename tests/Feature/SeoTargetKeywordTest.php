<?php

use App\Data\SerpResult;
use App\Data\SerpSearchResponse;
use App\Jobs\CheckSeoTargetKeywordRanking;
use App\Jobs\SendWeeklyRankingReport;
use App\Jobs\StartContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentRequest;
use App\Models\ExternalApiUsage;
use App\Models\GithubUserAuthorization;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteCompetitor;
use App\Services\CachedSerpProvider;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\CopilotAgentClient;
use App\Services\RankingReportBuilder;
use App\Services\SearchConsoleClient;
use App\Services\SeoTargetKeywordRankChecker;
use App\Services\SeoTargetKeywordSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('managers manage normalized targets without starting paid checks', function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $this->actingAs($owner)->post(route('admin.seo-target-keywords.store', $website), ['term' => '  Emergency   Plumber Barnsley ', 'priority' => 'high', 'note' => 'Priority service'])->assertRedirect();
    $target = $website->seoTargetKeywords()->sole();
    expect($target->term)->toBe('Emergency Plumber Barnsley')->and($target->normalized_term)->toBe('emergency plumber barnsley');
    $this->put(route('admin.seo-target-keywords.update', [$website, $target]), ['term' => 'Emergency Plumber Sheffield', 'priority' => 'normal', 'note' => 'Expanded service area'])->assertRedirect();
    $target = $target->fresh();
    expect($target->normalized_term)->toBe('emergency plumber sheffield')->and($target->priority)->toBe('normal');
    $this->post(route('admin.seo-target-keywords.store', $website), ['term' => 'emergency plumber sheffield', 'priority' => 'normal'])->assertSessionHasErrors('term');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
    $this->delete(route('admin.seo-target-keywords.archive', [$website, $target]))->assertRedirect();
    expect($target->fresh()->archived_at)->not->toBeNull();
    $this->post(route('admin.seo-target-keywords.restore', [$website, $target]))->assertRedirect();
    $this->post(route('admin.seo-target-keywords.check', [$website, $target]))->assertRedirect();
    Queue::assertPushed(CheckSeoTargetKeywordRanking::class, 1);
});

test('managers bulk add up to 20 normal priority target keywords from collapsed entry', function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    $website = Website::factory()->create();

    $this->actingAs($website->owner)
        ->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'targets']))
        ->assertSuccessful()
        ->assertSee('Bulk add keywords');

    $this->post(route('admin.seo-target-keywords.bulk-store', $website), [
        'bulk_terms' => "  Boiler repair Barnsley  \nBoiler installation Barnsley\n\nboiler repair barnsley\nEmergency plumber Barnsley",
    ])->assertRedirect()->assertSessionHas('status', '3 target keywords added.');

    expect($website->seoTargetKeywords()->pluck('normalized_term')->sort()->values()->all())->toBe([
        'boiler installation barnsley',
        'boiler repair barnsley',
        'emergency plumber barnsley',
    ])->and($website->seoTargetKeywords()->pluck('priority')->unique()->all())->toBe([SeoTargetKeyword::PRIORITY_NORMAL]);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('bulk target entry respects submitted and active keyword limits', function (): void {
    $emptyWebsite = Website::factory()->create();
    $this->actingAs($emptyWebsite->owner)->post(route('admin.seo-target-keywords.bulk-store', $emptyWebsite), [
        'bulk_terms' => collect(range(1, 20))->map(fn (int $number): string => "target keyword {$number}")->implode("\n"),
    ])->assertRedirect();
    expect($emptyWebsite->seoTargetKeywords()->count())->toBe(20);

    $website = Website::factory()->create();
    SeoTargetKeyword::factory()->count(19)->for($website)->create();
    $this->actingAs($website->owner);

    $this->post(route('admin.seo-target-keywords.bulk-store', $website), [
        'bulk_terms' => "one more keyword\na second keyword",
    ])->assertSessionHasErrors('bulk_terms');
    expect($website->seoTargetKeywords()->count())->toBe(19);

    $this->post(route('admin.seo-target-keywords.bulk-store', $website), [
        'bulk_terms' => collect(range(1, 21))->map(fn (int $number): string => "keyword {$number}")->implode("\n"),
    ])->assertSessionHasErrors('bulk_terms');
    expect($website->seoTargetKeywords()->count())->toBe(19);
});

test('target limits permissions gates and website isolation are enforced', function (): void {
    $website = Website::factory()->create();
    SeoTargetKeyword::factory()->count(20)->for($website)->create();
    $this->actingAs($website->owner)->post(route('admin.seo-target-keywords.store', $website), ['term' => 'one too many', 'priority' => 'normal'])->assertSessionHasErrors('term');
    $viewer = User::factory()->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $target = $website->seoTargetKeywords()->first();
    $this->actingAs($viewer)->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'targets']))->assertSuccessful()->assertSee($target->term);
    $this->post(route('admin.seo-target-keywords.bulk-store', $website), ['bulk_terms' => 'viewer keyword'])->assertForbidden();
    $this->post(route('admin.seo-target-keywords.check-all', $website))->assertForbidden();
    $this->post(route('admin.seo-target-keywords.check', [$website, $target]))->assertForbidden();
    $other = Website::factory()->create();
    $this->actingAs($other->owner)->post(route('admin.seo-target-keywords.check', [$other, $target]))->assertNotFound();
    $website->owner->update(['membership_tier' => 'essential']);
    $this->actingAs($website->owner)->post(route('admin.seo-target-keywords.check', [$website, $target]))->assertRedirect(route('admin.billing.index'));
    $this->post(route('admin.seo-target-keywords.check-all', $website))->assertRedirect(route('admin.billing.index'));
    $this->post(route('admin.seo-target-keywords.bulk-store', $website), ['bulk_terms' => 'gated keyword'])->assertRedirect(route('admin.billing.index'));
});

test('rank checks store exact and cached observations with cost only for provider calls', function (): void {
    $target = SeoTargetKeyword::factory()->create();
    $target->website->domains()->create(['domain' => 'www.example.com', 'is_primary' => true]);
    $provider = $this->mock(CachedSerpProvider::class);
    $provider->shouldReceive('searchForMarket')->twice()->andReturn(
        new SerpSearchResponse('dataforseo', 'serp/live', collect([new SerpResult(17, 'https://example.com/service/', 'example.com')]), .02, 'task-1', false, now()->toIso8601String()),
        new SerpSearchResponse('dataforseo', 'serp/live', collect(), 0, null, true, now()->toIso8601String()),
    );
    $checker = app(SeoTargetKeywordRankChecker::class);
    expect($checker->check($target)->position)->toBe(17)->and($checker->check($target)->status)->toBe(SeoTargetKeywordRanking::STATUS_NOT_FOUND);
    expect(ExternalApiUsage::query()->sole()->seo_target_keyword_id)->toBe($target->id)->and((float) ExternalApiUsage::query()->sole()->cost)->toBe(.02);
});

test('weekly opt in queues one isolated check per active target', function (): void {
    Queue::fake();
    $enabled = Website::factory()->create(['seo_weekly_snapshots_enabled' => true]);
    $enabled->domains()->create(['domain' => 'enabled.example', 'is_primary' => true]);
    $active = SeoTargetKeyword::factory()->for($enabled)->create();
    SeoTargetKeyword::factory()->for($enabled)->create(['archived_at' => now()]);
    SeoTargetKeyword::factory()->create();
    $this->artisan('seo:dispatch-weekly-snapshots')->assertSuccessful();
    Queue::assertPushed(CheckSeoTargetKeywordRanking::class, 1);
    Queue::assertPushed(CheckSeoTargetKeywordRanking::class, fn ($job): bool => $job->keyword->is($active));
});

test('managers queue ranking checks for every active target at once', function (): void {
    Queue::fake();
    $website = Website::factory()->create();
    $active = SeoTargetKeyword::factory()->count(3)->for($website)->create();
    $archived = SeoTargetKeyword::factory()->for($website)->create(['archived_at' => now()]);

    $this->actingAs($website->owner)
        ->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'targets']))
        ->assertSuccessful()
        ->assertSee('Check all rankings');

    $this->post(route('admin.seo-target-keywords.check-all', $website))
        ->assertRedirect()
        ->assertSessionHas('status', 'Ranking checks queued for 3 target keywords.');

    Queue::assertPushed(CheckSeoTargetKeywordRanking::class, 3);
    foreach ($active as $target) {
        Queue::assertPushed(CheckSeoTargetKeywordRanking::class, fn (CheckSeoTargetKeywordRanking $job): bool => $job->keyword->is($target));
    }
    Queue::assertNotPushed(CheckSeoTargetKeywordRanking::class, fn (CheckSeoTargetKeywordRanking $job): bool => $job->keyword->is($archived));
});

test('selection and prompt snapshots prioritize unranked high priority targets', function (): void {
    $generation = ContentGeneration::factory()->create();
    $website = $generation->plan->website;
    $ranked = SeoTargetKeyword::factory()->for($website)->create(['priority' => 'high', 'last_selected_at' => now()->subMonth()]);
    SeoTargetKeywordRanking::factory()->for($ranked, 'targetKeyword')->create(['website_id' => $website->id, 'position' => 5]);
    $unranked = SeoTargetKeyword::factory()->for($website)->create(['priority' => 'high', 'note' => 'Core commercial service']);
    $normal = SeoTargetKeyword::factory()->for($website)->create(['priority' => 'normal']);
    $selector = app(SeoTargetKeywordSelector::class);
    $active = $selector->active($website);
    expect($selector->select($active)?->id)->toBe($unranked->id);
    $generation->update(['seo_target_keyword_id' => $unranked->id, 'target_keyword_context' => $selector->snapshot($active)]);
    $prompt = app(ContentGenerationPromptGenerator::class)->generate($generation->fresh());
    expect($prompt)->toContain('## Primary objective and desired search intent', $unranked->term, 'Avoid keyword stuffing', 'existing-page versus new-page decision', '## Active strategic target terms')->and(mb_strlen($prompt))->toBeLessThanOrEqual(30000);
    expect($normal->fresh()->last_selected_at)->toBeNull();
});

test('target movements appear in reports and make new websites report eligible', function (): void {
    Queue::fake();
    $website = Website::factory()->create(['weekly_ranking_reports_enabled' => true]);
    $target = SeoTargetKeyword::factory()->for($website)->create();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['website_id' => $website->id, 'status' => 'not_found', 'position' => null, 'observed_at' => now()->subWeek()]);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['website_id' => $website->id, 'position' => 38, 'observed_at' => now()]);
    $row = app(RankingReportBuilder::class)->build($website)['targetKeywords']->sole();
    expect($row['movement'])->toBe('newly_ranked')->and($row['latest']->position)->toBe(38)->and($row['source'])->toContain('exact');
    $this->artisan('ranking-reports:dispatch')->assertSuccessful();
    Queue::assertPushed(SendWeeklyRankingReport::class, fn ($job): bool => $job->website->is($website));
});

test('generation selection is recorded only after Copilot accepts the task and manual work remains primary', function (): void {
    Queue::fake();
    $generation = ContentGeneration::factory()->create();
    $website = $generation->plan->website;
    $requester = $generation->requester;
    GithubUserAuthorization::factory()->for($requester, 'user')->create();
    $target = SeoTargetKeyword::factory()->for($website)->create(['priority' => 'high']);
    ContentRequest::factory()->for($website)->for($requester, 'creator')->create(['instructions' => 'Improve the verified pricing guide.']);
    $this->mock(SearchConsoleClient::class)->shouldNotReceive('performance');
    $this->mock(CopilotAgentClient::class)->shouldReceive('startTask')->once()->withArgs(fn ($authorization, $repository, string $prompt): bool => str_contains($prompt, 'Improve the verified pricing guide.') && str_contains($prompt, $target->term))->andReturn(['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa']);
    app()->call([new StartContentGeneration($generation), 'handle']);
    expect($generation->fresh()->seo_target_keyword_id)->toBeNull()->and($generation->fresh()->target_keyword_context)->toHaveCount(1)->and($target->fresh()->last_selected_at)->toBeNull();
});

test('rank checks retain the best organic result per domain from the same paid or cached response', function (): void {
    $target = SeoTargetKeyword::factory()->create();
    $target->website->domains()->create(['domain' => 'www.example.com', 'is_primary' => true]);
    $this->mock(CachedSerpProvider::class)->shouldReceive('searchForMarket')->once()->andReturn(
        new SerpSearchResponse('dataforseo', 'serp/live', collect([
            new SerpResult(20, 'https://rival.example/second', 'rival.example'),
            new SerpResult(17, 'https://example.com/service', 'www.EXAMPLE.com'),
            new SerpResult(3, 'https://rival.example/best', 'WWW.RIVAL.EXAMPLE'),
            new SerpResult(0, 'https://invalid.example', 'invalid.example'),
            new SerpResult(101, 'https://outside.example', 'outside.example'),
        ]), 0, null, true, now()->subDays(2)->toIso8601String()),
    );
    $ranking = app(SeoTargetKeywordRankChecker::class)->check($target)->fresh();
    expect($ranking->position)->toBe(17)
        ->and($ranking->organic_results)->toBe([
            ['domain' => 'rival.example', 'position' => 3, 'url' => 'https://rival.example/best'],
            ['domain' => 'example.com', 'position' => 17, 'url' => 'https://example.com/service'],
        ])
        ->and($ranking->observed_at->toDateString())->toBe(now()->subDays(2)->toDateString());
    expect(ExternalApiUsage::count())->toBe(0);
});

test('target comparison shows tracked competitors from the same successful observation without provider calls', function (): void {
    Http::preventStrayRequests();
    $this->mock(CachedSerpProvider::class)->shouldNotReceive('searchForMarket');
    $website = Website::factory()->create();
    $target = SeoTargetKeyword::factory()->for($website)->create();
    foreach (['ahead.example', 'behind.example', 'absent.example'] as $domain) {
        WebsiteCompetitor::factory()->for($website)->create(['domain' => $domain]);
    }
    WebsiteCompetitor::factory()->for($website)->create(['domain' => 'excluded.example', 'excluded' => true]);
    WebsiteCompetitor::factory()->create(['domain' => 'other-website.example']);
    $ranking = SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'position' => 17, 'observed_at' => now()->subDays(2),
        'organic_results' => [
            ['domain' => 'ahead.example', 'position' => 3, 'url' => 'https://ahead.example/service'],
            ['domain' => 'behind.example', 'position' => 22, 'url' => 'https://behind.example/service'],
        ],
    ]);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'status' => 'failed', 'position' => null, 'observed_at' => now(),
    ]);
    $viewer = User::factory()->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $response = $this->actingAs($viewer)->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'targets']))->assertSuccessful();
    $html = $response->getContent();
    $start = strpos($html, '<section class="overflow-hidden rounded-xl border bg-white shadow-sm" aria-labelledby="target-keywords-title">');
    $section = substr($html, $start, strpos($html, '</section>', $start) - $start);
    expect($section)->toContain('Competitor comparison', '#17', '#3', '#22', '14 places ahead of you', '5 places behind you', 'absent.example', 'Not in top 100', 'https://ahead.example/service', $ranking->observed_at->format('j M Y, H:i'), 'Latest check failed')
        ->not->toContain('excluded.example', 'other-website.example', 'Check now');
    Http::assertNothingSent();
});

test('comparison distinguishes missing historical evidence from an unranked website', function (?array $results, string $message): void {
    $website = Website::factory()->create();
    WebsiteCompetitor::factory()->for($website)->create(['domain' => 'rival.example']);
    $target = SeoTargetKeyword::factory()->for($website)->create();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'status' => 'not_found', 'position' => null,
        'organic_results' => $results,
    ]);
    $this->actingAs($website->owner)->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'targets']))
        ->assertSuccessful()->assertSee($message);
})->with([
    'legacy observation' => [null, 'Older checks do not contain competitor results.'],
    'empty results' => [[], 'Neither in top 100'],
    'competitor ranks but website does not' => [[['domain' => 'rival.example', 'position' => 8, 'url' => 'https://rival.example/']], 'Ranks above you'],
]);
