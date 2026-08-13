<?php

use App\Models\ContentRequest;
use App\Models\SeoKeyword;
use App\Models\SeoOpportunity;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;

test('website owners can add an seo recommendation to content todos', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    WebsiteRepository::factory()->for($website)->create();
    $snapshot = SeoSnapshot::factory()->for($website)->create();
    $keyword = SeoKeyword::factory()->for($website)->for($snapshot, 'snapshot')->create([
        'keyword' => 'garden office doncaster',
        'position' => 14,
        'search_volume' => 260,
        'search_intent' => 'commercial',
        'ranking_url' => 'https://example.com/garden-offices',
    ]);
    $opportunity = SeoOpportunity::factory()
        ->for($website)
        ->for($snapshot, 'snapshot')
        ->for($keyword, 'keyword')
        ->create([
            'summary' => 'The domain ranks at position 14 for an estimated 260 monthly searches.',
            'recommendation' => 'Strengthen the page with useful local project detail.',
            'metrics' => [
                'ranking_url' => 'https://example.com/garden-offices',
                'position' => 14,
                'search_volume' => 260,
                'search_intent' => 'commercial',
            ],
        ]);

    $this->actingAs($owner)
        ->get(route('admin.websites.show', [$website, 'tab' => 'seo']))
        ->assertSuccessful()
        ->assertSee('Add to action list');

    $this->actingAs($owner)
        ->post(route('admin.seo-opportunities.queue', [$website, $opportunity]))
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'seo']))
        ->assertSessionHas('status', 'SEO recommendation added to the content todos.');

    $opportunity->refresh();

    expect($opportunity->status)->toBe(SeoOpportunity::STATUS_QUEUED)
        ->and($opportunity->contentRequest)->toBeInstanceOf(ContentRequest::class)
        ->and($opportunity->contentRequest->instructions)->toContain('third-party ranking estimates')
        ->and($opportunity->contentRequest->instructions)->toContain('do not present them as Google Search Console data')
        ->and($opportunity->contentRequest->instructions)->toContain('garden office doncaster')
        ->and($opportunity->contentRequest->instructions)->toContain('https://example.com/garden-offices');

    $this->actingAs($owner)
        ->get(route('admin.websites.show', [$website, 'tab' => 'seo']))
        ->assertSuccessful()
        ->assertSee('Added to content todos');
});

test('removing an unprocessed content todo reopens its seo recommendation', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    WebsiteRepository::factory()->for($website)->create();
    $snapshot = SeoSnapshot::factory()->for($website)->create();
    $opportunity = SeoOpportunity::factory()->for($website)->for($snapshot, 'snapshot')->create();

    $this->actingAs($owner)->post(route('admin.seo-opportunities.queue', [$website, $opportunity]));
    $contentRequest = $opportunity->fresh()->contentRequest;

    $this->actingAs($owner)
        ->delete(route('admin.content-requests.destroy', [$website, $contentRequest]))
        ->assertRedirect(route('admin.websites.show', $website));

    expect($opportunity->fresh()->status)->toBe(SeoOpportunity::STATUS_OPEN)
        ->and($opportunity->fresh()->content_request_id)->toBeNull();
    $this->assertModelMissing($contentRequest);
});

test('seo recommendations cannot be queued twice or through another website', function (): void {
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    WebsiteRepository::factory()->for($website)->create();
    $snapshot = SeoSnapshot::factory()->for($website)->create();
    $opportunity = SeoOpportunity::factory()->for($website)->for($snapshot, 'snapshot')->create();

    $this->actingAs($owner)
        ->post(route('admin.seo-opportunities.queue', [$website, $opportunity]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('admin.seo-opportunities.queue', [$website, $opportunity]))
        ->assertUnprocessable();

    expect($website->contentRequests()->count())->toBe(1);

    $otherWebsite = Website::factory()->for($owner, 'owner')->create();
    WebsiteRepository::factory()->for($otherWebsite)->create();

    $this->actingAs($owner)
        ->post(route('admin.seo-opportunities.queue', [$otherWebsite, $opportunity]))
        ->assertNotFound();
});
