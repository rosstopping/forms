<?php

use App\Ai\Agents\BacklinkAnalyst;
use App\Jobs\AnalyzeProspect;
use App\Jobs\ProcessBacklinkAuditStage;
use App\Models\BacklinkAudit;
use App\Models\BacklinkDomainGap;
use App\Models\BacklinkOpportunity;
use App\Models\ContentGeneration;
use App\Models\ContentRequest;
use App\Models\ExternalApiUsage;
use App\Models\Prospect;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteCompetitor;
use App\Services\BacklinkAuditService;
use App\Services\BacklinkContentContext;
use App\Services\BacklinkOpportunityGenerator;
use App\Services\BacklinkProspectImporter;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\ContentOpportunityQueuer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['services.dataforseo.login' => 'test', 'services.dataforseo.password' => 'test']);
});

function backlinkAuditResponse(array $items, string $task = 'backlink-task'): array
{
    return ['status_code' => 20000, 'tasks' => [['id' => $task, 'status_code' => 20000, 'cost' => .02, 'result_count' => 1, 'result' => [['items' => $items, 'total_count' => count($items)]]]]];
}

test('managers request matching audits with up to three website competitors and fresh results are reused', function (): void {
    Queue::fake();
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $competitors = WebsiteCompetitor::factory()->count(3)->for($website)->create();
    $this->actingAs($website->owner)->post(route('admin.backlink-audits.store', $website), ['competitor_ids' => $competitors->modelKeys()])->assertRedirect();
    $audit = $website->backlinkAudits()->sole();
    expect($audit->competitors()->pluck('domain')->sort()->values()->all())->toBe($competitors->pluck('domain')->sort()->values()->all());
    Queue::assertPushed(ProcessBacklinkAuditStage::class, 1);
    $audit->update(['status' => 'completed', 'completed_at' => now(), 'stages' => BacklinkAuditService::STAGES]);
    $this->post(route('admin.backlink-audits.store', $website), ['competitor_ids' => $competitors->modelKeys()])->assertRedirect(route('admin.backlink-audits.show', [$website, $audit]));
    expect($website->backlinkAudits()->count())->toBe(1);
});

test('backlink audit access is website scoped and viewers cannot start paid work', function (): void {
    $audit = BacklinkAudit::factory()->create();
    $viewer = User::factory()->create();
    $audit->website->members()->attach($viewer, ['role' => 'viewer']);
    $this->actingAs($viewer)->get(route('admin.backlink-audits.show', [$audit->website, $audit]))->assertSuccessful();
    $this->post(route('admin.backlink-audits.store', $audit->website))->assertForbidden();
    $other = Website::factory()->create();
    $this->actingAs($other->owner)->get(route('admin.backlink-audits.show', [$other, $audit]))->assertNotFound();
    $audit->website->owner->update(['membership_tier' => 'essential']);
    $this->actingAs($audit->website->owner)->get(route('admin.backlink-audits.show', [$audit->website, $audit]))->assertRedirect(route('admin.billing.index'));
});

test('detailed link evidence is mapped and provider cost is checkpointed once', function (): void {
    $audit = BacklinkAudit::factory()->create();
    Http::fake(['*backlinks/backlinks/live' => Http::response(backlinkAuditResponse([['domain_from' => 'Publisher.Example', 'url_from' => 'https://publisher.example/story', 'url_to' => 'https://example.com/guide', 'anchor' => 'useful guide', 'dofollow' => true, 'domain_from_rank' => 72, 'page_from_rank' => 41, 'is_broken' => true, 'first_seen' => '2025-01-01 00:00:00 +00:00', 'last_seen' => '2026-08-01 00:00:00 +00:00']]))]);
    $service = app(BacklinkAuditService::class);
    $service->process($audit, 'current_links');
    $service->process($audit->fresh(), 'current_links');
    $link = $audit->links()->sole();
    expect($link->source_domain)->toBe('publisher.example')->and($link->broken)->toBeTrue()->and($link->dofollow)->toBeTrue()->and($link->source_domain_rank)->toBe(72);
    expect(ExternalApiUsage::where('backlink_audit_id', $audit->id)->count())->toBe(1);
    Http::assertSentCount(1);
});

test('malformed backlink evidence retains usage and leaves the stage incomplete', function (): void {
    $audit = BacklinkAudit::factory()->create();
    Http::fake(['*backlinks/backlinks/live' => Http::response(backlinkAuditResponse([['invalid' => true]]))]);
    expect(fn () => app(BacklinkAuditService::class)->process($audit, 'lost_links'))->toThrow(RuntimeException::class);
    expect($audit->fresh()->stages)->not->toContain('lost_links')->and($audit->links()->count())->toBe(0)->and(ExternalApiUsage::count())->toBe(1);
});

test('backlink evidence rejects unsafe URLs and destinations outside the audited website', function (array $row): void {
    $audit = BacklinkAudit::factory()->create(['domain' => 'example.com']);
    Http::fake(['*backlinks/backlinks/live' => Http::response(backlinkAuditResponse([$row]))]);

    expect(fn () => app(BacklinkAuditService::class)->process($audit, 'current_links'))->toThrow(RuntimeException::class);
    expect($audit->links()->count())->toBe(0);
})->with([
    'unsafe source scheme' => [['url_from' => 'ftp://publisher.example/story', 'url_to' => 'https://example.com/guide']],
    'foreign destination' => [['url_from' => 'https://publisher.example/story', 'url_to' => 'https://attacker.example/guide']],
]);

test('linked pages trends and competitor gaps retain bounded provider evidence', function (): void {
    $audit = BacklinkAudit::factory()->create();
    $competitor = WebsiteCompetitor::factory()->for($audit->website)->create(['domain' => 'competitor.example']);
    $audit->competitors()->create(['website_competitor_id' => $competitor->id, 'domain' => $competitor->domain]);
    Http::fake(function ($request) {
        if (str_ends_with($request->url(), '/backlinks/domain_pages/live')) {
            return Http::response(backlinkAuditResponse([['page' => 'https://example.com/guide', 'page_summary' => ['backlinks' => 30, 'referring_domains' => 12, 'rank' => 44]]], 'pages-task'));
        }
        if (str_ends_with($request->url(), '/backlinks/timeseries_new_lost_summary/live')) {
            return Http::response(backlinkAuditResponse([['date' => '2026-08-01 00:00:00 +00:00', 'new_backlinks' => 8, 'lost_backlinks' => 2, 'new_referring_domains' => 3, 'lost_referring_domains' => 1]], 'trend-task'));
        }

        return Http::response(backlinkAuditResponse([['domain' => 'publisher.example', 'rank' => 65, 'domain_intersection' => ['1' => ['backlinks' => 4, 'rank' => 65]]]], 'gap-task'));
    });
    $service = app(BacklinkAuditService::class);
    $service->process($audit, 'own_pages');
    $service->process($audit->fresh(), 'trend');
    $service->process($audit->fresh(), 'gaps');
    expect($audit->pages()->sole()->referring_domains)->toBe(12)
        ->and($audit->fresh()->new_lost_trend[0]['new_backlinks'])->toBe(8)
        ->and($audit->domainGaps()->sole()->competitor_evidence)->toHaveKey('competitor.example')
        ->and(ExternalApiUsage::where('backlink_audit_id', $audit->id)->count())->toBe(3);
});

test('recovery and content opportunities stay grounded in stored evidence', function (): void {
    $audit = BacklinkAudit::factory()->create(['started_at' => now()]);
    $audit->links()->create(['fingerprint' => fake()->sha256(), 'state' => 'lost', 'source_domain' => 'publisher.example', 'source_url' => 'https://publisher.example/link', 'target_url' => 'https://example.com/old', 'source_domain_rank' => 70]);
    $page = $audit->pages()->create(['domain' => 'competitor.example', 'kind' => 'competitor', 'url' => 'https://competitor.example/guide', 'url_hash' => fake()->sha256(), 'backlinks' => 100, 'referring_domains' => 50, 'status' => 'completed', 'analysis' => ['title' => 'A guide', 'main_content' => 'Useful evidence']]);
    BacklinkAnalyst::fake([['opportunities' => [['title' => 'Create an original guide', 'page_ids' => [$page->id], 'user_need' => 'Understand the process', 'relevance_reason' => 'Matches the audience', 'existing_page_url' => '', 'content_format' => 'Guide', 'observations' => ['The page answers process questions'], 'hypotheses' => ['Useful detail may make it referenceable'], 'improvements' => ['Add verified examples'], 'outline' => ['Process', 'Examples'], 'internal_links' => ['Link from the service page']]]]]);
    app(BacklinkOpportunityGenerator::class)->generate($audit);
    expect($audit->opportunities()->where('type', 'lost_link')->count())->toBe(1)->and($audit->opportunities()->where('type', 'content')->sole()->evidence['source_urls'])->toBe([$page->url]);
});

test('content briefs deduplicate and snapshot backlink evidence', function (): void {
    Queue::fake();
    $opportunity = BacklinkOpportunity::factory()->create(['evidence' => ['title' => 'Original research guide', 'observations' => ['Competitor pages have links'], 'hypotheses' => ['Original research may earn citations'], 'source_urls' => ['https://competitor.example/guide'], 'data_source' => 'dataforseo_estimate']]);
    $user = $opportunity->website->owner;
    $first = app(ContentOpportunityQueuer::class)->queueBacklink($opportunity, $user);
    $second = app(ContentOpportunityQueuer::class)->queueBacklink($opportunity, $user);
    expect($first->id)->toBe($second->id)->and(ContentRequest::count())->toBe(1);
    $generation = ContentGeneration::factory()->create();
    $context = app(BacklinkContentContext::class)->forGeneration(collect([$first]));
    $generation->update(['backlink_context' => $context]);
    expect($generation->fresh()->backlink_context)->toBe($context);
    expect(app(BacklinkContentContext::class)->forPrompt($context, 4000))->toContain('untrusted reference material')->toContain('Original research guide');

    $generation->update(['backlink_context' => [[...$context[0], 'observations' => [str_repeat('provider evidence ', 1000)]]]]);
    expect(mb_strlen(app(ContentGenerationPromptGenerator::class)->generate($generation->fresh())))->toBeLessThanOrEqual(30000);
});

test('only administrators save gap domains to outreach and importing never starts analysis', function (): void {
    Queue::fake();
    $gap = BacklinkDomainGap::factory()->create(['domain' => 'publisher.example']);
    $manager = $gap->audit->website->owner;
    $this->actingAs($manager)->post(route('admin.backlink-domain-gaps.outreach', [$gap->audit->website, $gap]))->assertForbidden();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->post(route('admin.backlink-domain-gaps.outreach', [$gap->audit->website, $gap]))->assertRedirect();
    $prospect = Prospect::query()->sole();
    expect($prospect->status)->toBe('new')->and($prospect->analysis_status)->toBe('pending')->and($gap->fresh()->prospect_id)->toBe($prospect->id);
    Queue::assertNotPushed(AnalyzeProspect::class);
    app(BacklinkProspectImporter::class)->import($gap->fresh(), $admin);
    expect(Prospect::count())->toBe(1)->and($prospect->activities()->where('type', 'backlink_opportunity_imported')->count())->toBe(1);
});

test('failed audit stages expose a resumable checkpoint', function (): void {
    Queue::fake();
    $audit = BacklinkAudit::factory()->create(['stages' => ['overview']]);
    $audit->website->domains()->create(['domain' => $audit->domain, 'is_primary' => true]);
    $job = new ProcessBacklinkAuditStage($audit, 'current_links');
    $job->failed(new RuntimeException('Provider failed'));
    expect($audit->fresh()->status)->toBe('failed')->and($audit->fresh()->errors)->toHaveKey('current_links');

    app(BacklinkAuditService::class)->request($audit->website, []);
    Queue::assertPushed(ProcessBacklinkAuditStage::class, fn (ProcessBacklinkAuditStage $queued): bool => $queued->audit->id === $audit->id && $queued->stage === 'current_links');
});

test('stored referring domains are searchable and audit views do not spend', function (): void {
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $snapshot = $website->seoSnapshots()->create(['provider' => 'dataforseo', 'domain' => 'example.com', 'location_code' => 2826, 'language_code' => 'en', 'status' => 'completed', 'snapshot_date' => today()]);
    $snapshot->referringDomains()->createMany([['website_id' => $website->id, 'domain' => 'publisher.example', 'domain_rank' => 70, 'backlinks_count' => 4], ['website_id' => $website->id, 'domain' => 'other.example', 'domain_rank' => 20, 'backlinks_count' => 1]]);
    $audit = BacklinkAudit::factory()->for($website)->create(['status' => 'completed', 'completed_at' => now()]);
    $this->actingAs($website->owner)->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'backlinks', 'backlink_search' => 'publisher']))->assertSuccessful()->assertSee('publisher.example')->assertDontSee('other.example');
    $this->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'backlinks', 'backlink_min_rank' => 70]))->assertSuccessful()->assertSee('publisher.example')->assertDontSee('other.example');
    $this->get(route('admin.backlink-audits.show', [$website, $audit]))->assertSuccessful()->assertSee('Backlink audit');
    Http::assertNothingSent();
});
