<?php

use App\Models\CompetitorAudit;
use App\Models\CompetitorOpportunity;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Services\CompetitorContentContext;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\ContentOpportunityQueuer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('queuing competitor briefs deduplicates across audits and preserves evidence', function (): void {
    Queue::fake();
    $opportunity = CompetitorOpportunity::factory()->create();
    $other = CompetitorOpportunity::factory()->create(['website_id' => $opportunity->website_id, 'fingerprint' => $opportunity->fingerprint]);
    $queuer = app(ContentOpportunityQueuer::class);
    $first = $queuer->queueCompetitor($opportunity, $opportunity->website->owner);
    $second = $queuer->queueCompetitor($other, $opportunity->website->owner);
    expect($first->id)->toBe($second->id)->and($first->competitor_context)->toBe($opportunity->brief)->and($other->fresh()->status)->toBe('queued');
});

test('automatic context uses fresh relevant nonexcluded unqueued evidence and manual requests take priority', function (): void {
    Http::preventStrayRequests();
    $audit = CompetitorAudit::factory()->create(['status' => 'completed', 'completed_at' => now()]);
    $website = $audit->website;
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $plan = ContentPlan::factory()->for($website)->create();
    $generation = ContentGeneration::factory()->for($plan, 'plan')->create();
    $opportunity = CompetitorOpportunity::factory()->for($audit, 'audit')->create();
    $context = app(CompetitorContentContext::class);
    expect($context->forGeneration($generation, collect()))->toHaveCount(1);
    $manual = ContentRequest::factory()->for($website)->create();
    expect($context->forGeneration($generation, collect([$manual])))->toBe([]);
    $audit->competitor->update(['excluded' => true]);
    expect($context->forGeneration($generation, collect()))->toBe([]);
    $audit->competitor->update(['excluded' => false]);
    $audit->update(['completed_at' => now()->subDays(31)]);
    expect($context->forGeneration($generation, collect()))->toBe([]);
    Http::assertNothingSent();
});

test('competitor prompts preserve requirements and stay inside the generation budget', function (): void {
    $generation = ContentGeneration::factory()->create(['competitor_context' => [CompetitorOpportunity::factory()->make()->brief]]);
    $generation->plan->update(['audience' => str_repeat('A', 5000), 'guidance' => str_repeat('G', 20000)]);
    $generation->setRelation('contentRequests', collect([ContentRequest::factory()->make(['instructions' => str_repeat('I', 3000)]), ContentRequest::factory()->make(['instructions' => str_repeat('J', 3000)])]));
    $prompt = app(ContentGenerationPromptGenerator::class)->generate($generation);
    expect(mb_strlen($prompt))->toBeLessThanOrEqual(30000)->and($prompt)->toContain('Requirements:', 'Competitor research', 'Manual requests remain primary', 'verified business facts');
});

test('removing an unprocessed competitor content request reopens every linked opportunity', function (): void {
    Queue::fake();
    $opportunity = CompetitorOpportunity::factory()->create();
    $request = app(ContentOpportunityQueuer::class)->queueCompetitor($opportunity, $opportunity->website->owner);
    $this->actingAs($opportunity->website->owner)->delete(route('admin.content-requests.destroy', [$opportunity->website, $request]))->assertRedirect();
    expect($opportunity->fresh()->status)->toBe('open')->and($opportunity->fresh()->content_request_id)->toBeNull();
});
