<?php

use App\Ai\Agents\AiVisibilityObserver;
use App\Ai\Agents\WeeklyOverviewWriter;
use App\Jobs\CheckAiVisibility;
use App\Jobs\SendWeeklyRankingReport;
use App\Mail\WeeklyRankingReport;
use App\Models\AiVisibilityPrompt;
use App\Models\AiVisibilityResult;
use App\Models\AiVisibilitySetting;
use App\Models\ExternalApiUsage;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\User;
use App\Models\Website;
use App\Services\AiVisibilityAnalyzer;
use App\Services\AiVisibilityPromptSuggestions;
use App\Services\AiVisibilityProviderRegistry;
use App\Services\AiVisibilityReport;
use App\Services\AiVisibilityScheduler;
use App\Services\RankingReportBuilder;
use App\Services\WeeklyReportGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Responses\Data\Usage;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));
    config(['ai.providers.openai.key' => 'fake', 'ai_visibility.providers.openai.enabled' => true, 'ai_visibility.providers.openai.model' => 'test-model', 'ai_visibility.providers.gemini.enabled' => false, 'ai_visibility.providers.perplexity.enabled' => false]);
    AiVisibilityObserver::fake(["1. **Other Heating** — Repairs\n2. **Rowglo** — Boiler repairs https://rowglo.co.uk"]);
    WeeklyOverviewWriter::fake(['This overview uses recorded evidence.']);
    Http::preventStrayRequests();
});

test('managers create edit disable and delete prompts while preserving response evidence', function (): void {
    $website = Website::factory()->create();
    $this->actingAs($website->owner);
    $data = ['prompt' => 'Who repairs boilers in Doncaster?', 'priority' => 'high', 'active' => true];
    $this->post(route('admin.ai-visibility.store', $website), $data)->assertRedirect(route('admin.ai-visibility.index', $website));
    $prompt = AiVisibilityPrompt::sole();
    $result = AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create();
    $this->put(route('admin.ai-visibility.update', [$website, $prompt]), [...$data, 'prompt' => 'Who installs boilers in Doncaster?', 'active' => false])->assertRedirect();
    expect($prompt->fresh()->active)->toBeFalse()->and($result->fresh()->prompt_snapshot)->toBe($data['prompt']);
    $this->delete(route('admin.ai-visibility.destroy', [$website, $prompt]))->assertRedirect();
    expect(AiVisibilityPrompt::count())->toBe(0)->and(AiVisibilityResult::count())->toBe(1);
    $this->get(route('admin.ai-visibility.show', [$website, $prompt]))->assertOk()->assertSee('historical evidence retained');
});

test('prompt limits duplicates foreign keywords and foreign prompts are rejected', function (): void {
    config(['ai_visibility.max_active_prompts' => 1]);
    $website = Website::factory()->create();
    $prompt = AiVisibilityPrompt::factory()->for($website)->create(['prompt' => 'Who repairs boilers?']);
    $this->actingAs($website->owner);
    $data = ['prompt' => 'Who repairs boilers!', 'priority' => 'normal', 'active' => true];
    $this->post(route('admin.ai-visibility.store', $website), $data)->assertSessionHasErrors('prompt');
    $this->post(route('admin.ai-visibility.store', $website), [...$data, 'prompt' => 'Who installs boilers?'])->assertSessionHasErrors('prompt');
    $keyword = SeoTargetKeyword::factory()->create();
    $this->put(route('admin.ai-visibility.update', [$website, $prompt]), [...$data, 'seo_target_keyword_id' => $keyword->id])->assertSessionHasErrors('seo_target_keyword_id');
    $foreign = AiVisibilityPrompt::factory()->create();
    $this->get(route('admin.ai-visibility.show', [$website, $foreign]))->assertNotFound();
    $this->delete(route('admin.ai-visibility.destroy', [$website, $foreign]))->assertNotFound();
});

test('outsiders cannot read and viewers cannot mutate ai visibility', function (): void {
    $website = Website::factory()->create();
    $viewer = User::factory()->create();
    $this->actingAs($viewer)->get(route('admin.ai-visibility.index', $website))->assertForbidden();
    $website->members()->attach($viewer, ['role' => 'viewer']);
    $this->get(route('admin.ai-visibility.index', $website))->assertOk()->assertDontSee('Save tracking settings');
    $this->post(route('admin.ai-visibility.store', $website), ['prompt' => 'Who repairs boilers?', 'active' => true, 'priority' => 'normal'])->assertForbidden();
    $this->post(route('admin.ai-visibility.check', $website))->assertForbidden();
    $this->put(route('admin.ai-visibility.settings', $website), [])->assertForbidden();
});

test('settings require genuine providers and suggestions are reviewable without overwriting prompts', function (): void {
    $website = Website::factory()->create();
    $this->actingAs($website->owner);
    $settings = ['enabled' => true, 'brand_name' => 'Rowglo', 'frequency_days' => 7, 'providers' => ['gemini']];
    $this->put(route('admin.ai-visibility.settings', $website), $settings)->assertSessionHasErrors('providers');
    $this->put(route('admin.ai-visibility.settings', $website), [...$settings, 'providers' => ['openai'], 'services' => "Boiler repair\nPlumbing", 'locations' => 'Doncaster'])->assertRedirect();
    expect(AiVisibilitySetting::sole()->services)->toBe(['Boiler repair', 'Plumbing']);
    foreach (['heating repairs Doncaster', 'boiler installation Doncaster', 'leaking taps Doncaster', 'bathroom plumbing Doncaster', 'radiator repairs Doncaster'] as $term) {
        SeoTargetKeyword::factory()->for($website)->create(['term' => $term]);
    }
    $ideas = app(AiVisibilityPromptSuggestions::class)->forWebsite($website);
    expect(count($ideas))->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(15)->and(AiVisibilityPrompt::count())->toBe(0);
    $manual = AiVisibilityPrompt::factory()->for($website)->create(['prompt' => $ideas[0]['prompt']]);
    $this->post(route('admin.ai-visibility.suggestions', $website))->assertSessionHas('aiPromptSuggestions');
    expect(collect(app(AiVisibilityPromptSuggestions::class)->forWebsite($website))->pluck('prompt'))->not->toContain($manual->prompt);
    expect($manual->fresh()->prompt)->toBe($ideas[0]['prompt']);
    AiVisibilityObserver::assertNeverPrompted();
});

test('brand and domain detection distinguish boundary matches mentions citations and positions', function (): void {
    $analyzer = app(AiVisibilityAnalyzer::class);
    $identity = ['names' => ['Rowglo', 'Rowglo Plumbing & Heating'], 'domains' => ['rowglo.co.uk']];
    $result = $analyzer->analyze("1. **Other Heating** — Repairs\n2. **Rowglo** — Boilers https://www.rowglo.co.uk/services\n3. **Other Heating** — Plumbing", [['url' => 'https://rowglo.co.uk/services'], ['url' => 'javascript:alert(1)']], $identity);
    expect($result)->toMatchArray(['brand_mentioned' => true, 'website_mentioned' => true, 'website_cited' => true, 'brand_position' => 2])
        ->and($result['competitors'])->toHaveCount(1)->and($result['competitors'][0]['name'])->toBe('Other Heating')->and($result['citations'])->toHaveCount(1);
    $prose = $analyzer->analyze('Rowglo can help. Visit https://rowglo.co.uk.', [], $identity);
    expect($prose)->toMatchArray(['brand_mentioned' => true, 'website_cited' => false, 'brand_position' => null]);
    $falseMatch = $analyzer->analyze('Rowglow and https://rowglo.co.uk.evil.com or https://evil.com/rowglo.co.uk', [['url' => 'https://rowglo.co.uk@evil.com'], ['url' => 'https://rowglo.co.uk.evil.com']], $identity);
    expect($falseMatch)->toMatchArray(['brand_mentioned' => false, 'website_mentioned' => false, 'website_cited' => false]);
    expect($analyzer->analyze('https://rowglo.co.uk', [], $identity))->toMatchArray(['brand_mentioned' => false, 'website_mentioned' => true]);
    expect($analyzer->mentionsBrand('ROWGLO PLUMBING AND HEATING', ['Rowglo Plumbing & Heating']))->toBeTrue();
    expect($analyzer->analyze("1. **Other Heating**\n3. **Rowglo**", [], $identity)['brand_position'])->toBeNull();
});

test('scheduler is opt in queues each due prompt and provider once and records frozen identity', function (): void {
    Queue::fake();
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'rowglo.co.uk', 'is_primary' => true]);
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    $settings = AiVisibilitySetting::factory()->for($website)->create();
    $scheduler = app(AiVisibilityScheduler::class);
    expect($scheduler->queue($website))->toBe(0);
    $settings->update(['enabled' => true, 'providers' => ['openai', 'gemini']]);
    expect($scheduler->queue($website))->toBe(1)->and($scheduler->queue($website))->toBe(0);
    $result = AiVisibilityResult::sole();
    expect($result->provider)->toBe('openai')->and($result->prompt_snapshot)->toBe($prompt->prompt)->and($result->identity_snapshot['domains'])->toBe(['rowglo.co.uk']);
    Queue::assertPushed(CheckAiVisibility::class, 1);
    $result->update(['status' => 'completed', 'checked_at' => now()]);
    $this->travel(7)->days();
    expect($scheduler->queue($website))->toBe(1);
});

test('queued check persists genuine response evidence once and cancelled prompts make no request', function (): void {
    $website = Website::factory()->create();
    AiVisibilitySetting::factory()->for($website)->create(['enabled' => true]);
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    $result = AiVisibilityResult::factory()->for($prompt, 'prompt')->create();
    $job = new CheckAiVisibility($result);
    $job->handle(app(AiVisibilityProviderRegistry::class), app(AiVisibilityAnalyzer::class));
    $job->handle(app(AiVisibilityProviderRegistry::class), app(AiVisibilityAnalyzer::class));
    expect($result->fresh())->toMatchArray(['status' => 'completed', 'brand_mentioned' => true, 'brand_position' => 2, 'website_cited' => false, 'attempts' => 1]);
    expect(ExternalApiUsage::where('request_type', 'ai_visibility_check')->count())->toBe(1);
    $other = AiVisibilityResult::factory()->create();
    $other->prompt->update(['active' => false]);
    (new CheckAiVisibility($other))->handle(app(AiVisibilityProviderRegistry::class), app(AiVisibilityAnalyzer::class));
    expect($other->fresh()->status)->toBe('cancelled');
});

test('failed and empty provider responses do not become zero visibility and retries are bounded', function (): void {
    AiVisibilityObserver::fake(fn () => throw new RuntimeException('Provider unavailable'));
    $website = Website::factory()->create();
    AiVisibilitySetting::factory()->for($website)->create(['enabled' => true]);
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    $result = AiVisibilityResult::factory()->for($prompt, 'prompt')->create();
    $job = new CheckAiVisibility($result);
    for ($attempt = 0; $attempt < 3; $attempt++) {
        expect(fn () => $job->handle(app(AiVisibilityProviderRegistry::class), app(AiVisibilityAnalyzer::class)))->toThrow(RuntimeException::class);
    }
    $job->handle(app(AiVisibilityProviderRegistry::class), app(AiVisibilityAnalyzer::class));
    expect($result->fresh()->status)->toBe('failed')->and($result->fresh()->attempts)->toBe(3)->and($result->fresh()->brand_mentioned)->toBeNull();
    $report = app(AiVisibilityReport::class)->forPeriod($website, today()->subDays(6), now()->endOfDay());
    expect($report)->toMatchArray(['score' => null, 'completed_checks' => 0, 'citation_rate' => null, 'failed_checks' => 1]);
});

test('visibility citation provider and competitor metrics use completed response counts', function (): void {
    $website = Website::factory()->create();
    $competitor = ['key' => 'other heating', 'name' => 'Other Heating', 'position' => 1];
    foreach (range(1, 5) as $i) {
        $prompt = AiVisibilityPrompt::factory()->for($website)->create();
        AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['brand_mentioned' => $i <= 2, 'brand_position' => $i <= 2 ? $i : null, 'website_cited' => $i === 1, 'provider' => $i <= 3 ? 'openai' : 'gemini', 'competitors' => [$competitor, $competitor]]);
    }
    AiVisibilityResult::factory()->for($website)->create(['status' => 'failed']);
    $report = app(AiVisibilityReport::class)->forPeriod($website, today()->subDays(6), now()->endOfDay());
    expect($report)->toMatchArray(['score' => 40.0, 'completed_checks' => 5, 'brand_appearances' => 2, 'citation_rate' => 20.0, 'average_position' => 1.5, 'prompts_visible' => 2, 'competitor_count' => 1])
        ->and($report['providers']['openai']['score'])->toBe(66.7)->and($report['providers']['gemini']['score'])->toBe(0.0)->and($report['competitors'][0]['appearances'])->toBe(5);
});

test('historical comparisons flag coverage changes and identify genuine gains losses and opportunities', function (): void {
    $website = Website::factory()->create();
    $prompt = AiVisibilityPrompt::factory()->for($website)->create(['priority' => 'high']);
    AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['checked_at' => '2026-09-03', 'period_start' => '2026-08-31', 'brand_mentioned' => true, 'website_cited' => true]);
    AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['checked_at' => '2026-09-10', 'period_start' => '2026-09-07']);
    $reports = app(AiVisibilityReport::class);
    $report = $reports->forPeriod($website, Carbon::parse('2026-09-07'), Carbon::parse('2026-09-13')->endOfDay());
    expect($report)->toMatchArray(['score' => 0.0, 'previous_score' => 100.0, 'change' => -100.0, 'comparable' => true]);
    expect(array_column($report['notable_changes'], 'type'))->toContain('appearance_lost', 'citation_lost');
    expect(array_column($report['opportunities'], 'type'))->toContain('missing_brand', 'lost_appearance');
    $new = AiVisibilityPrompt::factory()->for($website)->create();
    AiVisibilityResult::factory()->for($new, 'prompt')->completed()->create(['checked_at' => '2026-09-10']);
    $changed = $reports->forPeriod($website, Carbon::parse('2026-09-07'), Carbon::parse('2026-09-13')->endOfDay());
    expect($changed)->toMatchArray(['coverage_changed' => true, 'change' => null]);
    expect($reports->trend($website, today()->subMonth(), now()))->toHaveCount(2);
});

test('strong google rankings create evidenced cross module opportunities', function (): void {
    $website = Website::factory()->create();
    $keyword = SeoTargetKeyword::factory()->for($website)->create(['term' => 'boiler repairs Doncaster']);
    SeoTargetKeywordRanking::factory()->for($keyword, 'targetKeyword')->create(['website_id' => $website->id, 'position' => 4, 'status' => 'ranked', 'observed_at' => now()->subDay()]);
    $prompt = AiVisibilityPrompt::factory()->for($website)->create(['seo_target_keyword_id' => $keyword->id]);
    AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create();
    $report = app(AiVisibilityReport::class)->forPeriod($website, today()->subDays(6), now()->endOfDay());
    expect($report['opportunities'][0]['type'])->toBe('google_ai_gap')->and($report['opportunities'][0]['google_position'])->toBe(4);
});

test('dedicated overview prompt detail main dashboard and navigation render stored data without checks', function (): void {
    Queue::fake();
    $website = Website::factory()->create();
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['brand_mentioned' => true, 'response_text' => '<script>alert("unsafe")</script> Rowglo']);
    $this->actingAs($website->owner)->get(route('admin.ai-visibility.index', $website))->assertOk()->assertSee('AI Visibility')->assertSee('Since first check')->assertDontSee('12 months')->assertSee('100%')->assertSee('Competitors')->assertSee('Opportunities');
    $this->get(route('admin.ai-visibility.show', [$website, $prompt]))->assertOk()->assertSee('Read response and matching evidence')->assertDontSee('<script>alert("unsafe")</script>', false);
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('View AI Visibility')->assertSee('1 of 1 tracked AI checks');
    AiVisibilityObserver::assertNeverPrompted();
    Queue::assertNothingPushed();
});

test('weekly overview and the existing email share genuine ai evidence and scheduling stays in the existing pipeline', function (): void {
    Queue::fake();
    $website = Website::factory()->create(['weekly_ranking_reports_enabled' => true]);
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['checked_at' => '2026-09-10', 'period_start' => '2026-09-07', 'brand_mentioned' => true]);
    $overview = app(WeeklyReportGenerator::class)->generate($website, today());
    expect($overview->snapshot['ai_visibility'])->toMatchArray(['score' => 100, 'completed_checks' => 1]);
    WeeklyOverviewWriter::assertPrompted(fn ($prompt) => ! str_contains($prompt->prompt, '100% visibility'));
    expect($overview->overview)->toContain('100% visibility');
    $mail = new WeeklyRankingReport($website, app(RankingReportBuilder::class)->build($website), $overview);
    expect($mail->render())->toContain('Explore AI Visibility', '1 of 1 completed AI checks');
    $this->artisan('ranking-reports:dispatch')->assertSuccessful();
    Queue::assertPushed(SendWeeklyRankingReport::class, fn ($job) => $job->website->id === $website->id);
});

test('empty answers fail and cited completed answers persist their real source URLs', function (): void {
    $website = Website::factory()->create();
    AiVisibilitySetting::factory()->for($website)->create(['enabled' => true]);
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    $result = AiVisibilityResult::factory()->for($prompt, 'prompt')->create();
    AiVisibilityObserver::fake(['']);
    expect(fn () => (new CheckAiVisibility($result))->handle(app(AiVisibilityProviderRegistry::class), app(AiVisibilityAnalyzer::class)))->toThrow(RuntimeException::class);
    expect($result->fresh()->status)->toBe('failed');
    AiVisibilityObserver::fake([new AgentResponse('test', 'Rowglo is an option.', new Usage(10, 5), new Meta('openai', 'test-model', collect([new UrlCitation('https://rowglo.co.uk', 'Rowglo')])))]);
    (new CheckAiVisibility($result))->handle(app(AiVisibilityProviderRegistry::class), app(AiVisibilityAnalyzer::class));
    expect($result->fresh()->website_cited)->toBeTrue()->and($result->fresh()->citations[0]['url'])->toBe('https://rowglo.co.uk')->and($result->fresh()->attempts)->toBe(2);
});

test('edited prompts cancel queued work and expired memberships never incur checks', function (): void {
    Queue::fake();
    $website = Website::factory()->create();
    AiVisibilitySetting::factory()->for($website)->create(['enabled' => true]);
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    $result = AiVisibilityResult::factory()->for($prompt, 'prompt')->create();
    $prompt->update(['prompt' => 'Who repairs heat pumps in Doncaster?']);
    (new CheckAiVisibility($result))->handle(app(AiVisibilityProviderRegistry::class), app(AiVisibilityAnalyzer::class));
    expect($result->fresh()->status)->toBe('cancelled');
    $website->owner->update(['membership_status' => 'cancelled', 'membership_current_period_end' => now()->subDay()]);
    expect(app(AiVisibilityScheduler::class)->queue($website->fresh()))->toBe(0);
    AiVisibilityObserver::assertNeverPrompted();
});

test('stale queued work is recoverable and manual checks respect the configured site cap', function (): void {
    Queue::fake();
    config(['ai_visibility.max_active_prompts' => 1]);
    $website = Website::factory()->create();
    AiVisibilitySetting::factory()->for($website)->create(['enabled' => true]);
    $first = AiVisibilityPrompt::factory()->for($website)->create();
    $second = AiVisibilityPrompt::factory()->for($website)->create();
    $result = AiVisibilityResult::factory()->for($first, 'prompt')->create(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
    expect(app(AiVisibilityScheduler::class)->queue($website, $second))->toBe(0);
    expect(app(AiVisibilityScheduler::class)->queue($website))->toBe(1);
    expect(AiVisibilityResult::count())->toBe(1);
    Queue::assertPushed(CheckAiVisibility::class, fn ($job) => $job->result->id === $result->id);
});

test('provider changes new appearances citation gains and competing lists produce distinct evidence', function (): void {
    $website = Website::factory()->create();
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    $competitor = ['key' => 'other heating', 'name' => 'Other Heating', 'position' => 1];
    foreach (['openai', 'gemini'] as $provider) {
        AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['provider' => $provider, 'checked_at' => '2026-09-03', 'period_start' => '2026-08-31']);
        AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['provider' => $provider, 'checked_at' => '2026-09-10', 'period_start' => '2026-09-07', 'brand_mentioned' => true, 'website_cited' => $provider === 'openai', 'brand_position' => 2, 'competitors' => [$competitor]]);
    }
    $report = app(AiVisibilityReport::class)->forPeriod($website, Carbon::parse('2026-09-07'), Carbon::parse('2026-09-13')->endOfDay());
    expect(array_column($report['notable_changes'], 'type'))->toContain('appearance_gained', 'citation_gained', 'new_competitor', 'provider_improved');
    expect(array_column($report['opportunities'], 'type'))->toContain('competitor_ahead', 'missing_citation');
    expect($report['providers']['openai']['change'])->toBe(100.0);
});

test('supported history ranges provider filters and website switching preserve the ai section', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create();
    $other = Website::factory()->for($user, 'owner')->create();
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['checked_at' => now()->subDays(400), 'period_start' => today()->subDays(400)->startOfWeek()]);
    AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['provider' => 'gemini', 'brand_mentioned' => true]);
    $this->actingAs($user)->get(route('admin.ai-visibility.index', [$website, 'provider' => 'openai', 'range' => 365]))->assertOk()->assertSee('12 months')->assertViewHas('report', fn ($report) => $report['completed_checks'] === 0);
    $this->get(route('admin.ai-visibility.index', [$website, 'provider' => 'gemini']))->assertOk()->assertSee('Since first check')->assertDontSee('12 months');
    $this->post(route('admin.current-website.update'), ['website_id' => $other->id, 'section' => 'ai-visibility'])->assertRedirect(route('admin.websites.section', [$other, 'ai-visibility']));
    $this->get(route('admin.websites.section', [$other, 'ai-visibility']))->assertOk()->assertSee('Your first visibility baseline');
});

test('suggestions deduplicate minor service wording and retain remaining reviewed ideas', function (): void {
    $website = Website::factory()->create();
    AiVisibilitySetting::factory()->for($website)->create(['services' => ['boiler repairs'], 'locations' => ['Doncaster']]);
    SeoTargetKeyword::factory()->for($website)->create(['term' => 'boiler repair Doncaster']);
    SeoTargetKeyword::factory()->for($website)->create(['term' => 'plumbing Doncaster']);
    $ideas = app(AiVisibilityPromptSuggestions::class)->forWebsite($website);
    expect($ideas)->toHaveCount(2);
    $this->actingAs($website->owner)->withSession(['aiPromptSuggestions' => $ideas])->post(route('admin.ai-visibility.store', $website), [...$ideas[0], 'active' => true, 'priority' => 'normal'])->assertSessionHas('aiPromptSuggestions', [$ideas[1]]);
});

test('weekly narrative cannot substitute invented ai visibility for the verified summary', function (): void {
    WeeklyOverviewWriter::fake(['AI visibility increased to 99% on ChatGPT.']);
    $website = Website::factory()->create();
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create(['checked_at' => now()->subDay()]);
    $overview = app(WeeklyReportGenerator::class)->generate($website, today());
    expect($overview->overview)->toContain('0% visibility')->not->toContain('99%', 'ChatGPT')->and($overview->narrative_source)->toBe('deterministic');
});

test('explicitly readding a deleted prompt restores its history without bypassing the active cap', function (): void {
    config(['ai_visibility.max_active_prompts' => 1]);
    $website = Website::factory()->create();
    $prompt = AiVisibilityPrompt::factory()->for($website)->create();
    AiVisibilityResult::factory()->for($prompt, 'prompt')->completed()->create();
    $prompt->delete();
    $other = AiVisibilityPrompt::factory()->for($website)->create();
    $data = ['prompt' => $prompt->prompt, 'active' => true, 'priority' => 'high'];
    $this->actingAs($website->owner)->post(route('admin.ai-visibility.store', $website), $data)->assertSessionHasErrors('prompt');
    expect($prompt->fresh()->trashed())->toBeTrue();
    $other->update(['active' => false]);
    $this->post(route('admin.ai-visibility.store', $website), $data)->assertRedirect();
    expect($prompt->fresh()->trashed())->toBeFalse()->and($prompt->fresh()->results()->count())->toBe(1)->and($prompt->fresh()->priority)->toBe('high');
});
