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

test('target limits permissions gates and website isolation are enforced', function (): void {
    $website = Website::factory()->create();
    SeoTargetKeyword::factory()->count(20)->for($website)->create();
    $this->actingAs($website->owner)->post(route('admin.seo-target-keywords.store', $website), ['term' => 'one too many', 'priority' => 'normal'])->assertSessionHasErrors('term');
    $viewer = User::factory()->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    $target = $website->seoTargetKeywords()->first();
    $this->actingAs($viewer)->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'targets']))->assertSuccessful()->assertSee($target->term);
    $this->post(route('admin.seo-target-keywords.check', [$website, $target]))->assertForbidden();
    $other = Website::factory()->create();
    $this->actingAs($other->owner)->post(route('admin.seo-target-keywords.check', [$other, $target]))->assertNotFound();
    $website->owner->update(['membership_tier' => 'essential']);
    $this->actingAs($website->owner)->post(route('admin.seo-target-keywords.check', [$website, $target]))->assertRedirect(route('admin.billing.index'));
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
    $website = Website::factory()->create(['health_reports_enabled' => true]);
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
