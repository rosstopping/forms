<?php

use App\Ai\Agents\WebsiteDataAssistant;
use App\Models\SearchConsoleMetric;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use App\Models\WebsiteHealthReport;
use App\Services\WebsiteAiContext;
use App\Support\MembershipPlan;

function completeWebsiteWorkspace(): array
{
    $owner = User::factory()->create([
        'membership_tier' => MembershipPlan::COMPLETE,
        'membership_status' => 'active',
    ]);
    $website = Website::factory()->for($owner, 'owner')->create(['name' => 'Northfield Roofing']);
    $website->domains()->create(['domain' => 'northfield.example', 'is_primary' => true]);

    return [$owner, $website];
}

it('answers complete members using only the selected website context', function (): void {
    [$owner, $website] = completeWebsiteWorkspace();
    WebsiteHealthReport::factory()->for($website)->create([
        'warning_checks' => 1,
        'checks' => [['label' => 'Meta description', 'status' => 'warning']],
    ]);
    SearchConsoleMetric::factory()->for($website)->create(['query' => 'roof repairs york', 'dimension_key' => hash('sha256', 'roof repairs york')]);
    $otherWebsite = Website::factory()->create(['name' => 'Private Competitor']);
    SearchConsoleMetric::factory()->for($otherWebsite)->create(['query' => 'secret query', 'dimension_key' => hash('sha256', 'secret query')]);
    WebsiteDataAssistant::fake([[
        'in_scope' => true,
        'answer' => 'The meta description warning needs attention.',
    ]])->preventStrayPrompts();

    $this->actingAs($owner)
        ->post(route('admin.websites.assistant.questions.store', $website), ['question' => 'What needs attention?'])
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'assistant']));

    $record = WebsiteAiQuestion::query()->sole();
    expect($record->website_id)->toBe($website->id)
        ->and($record->user_id)->toBe($owner->id)
        ->and($record->status)->toBe('completed')
        ->and($record->answer)->toContain('meta description');

    $instructions = (new WebsiteDataAssistant($website->name, app(WebsiteAiContext::class)->for($website)))->instructions();
    expect((string) $instructions)->toContain('Northfield Roofing')
        ->toContain('roof repairs york')
        ->not->toContain('Private Competitor')
        ->not->toContain('secret query');
});

it('blocks the assistant unless the website owner has an active complete membership', function (): void {
    $owner = User::factory()->create([
        'membership_tier' => MembershipPlan::GROWTH,
        'membership_status' => 'active',
    ]);
    $website = Website::factory()->for($owner, 'owner')->create();
    WebsiteDataAssistant::fake()->preventStrayPrompts();

    $this->actingAs($owner)
        ->post(route('admin.websites.assistant.questions.store', $website), ['question' => 'How are rankings?'])
        ->assertRedirect(route('admin.billing.index'));

    expect(WebsiteAiQuestion::query()->exists())->toBeFalse();
    WebsiteDataAssistant::assertNeverPrompted();
});

it('never allows a complete member to ask about an inaccessible website', function (): void {
    [, $website] = completeWebsiteWorkspace();
    $outsider = User::factory()->create([
        'membership_tier' => MembershipPlan::COMPLETE,
        'membership_status' => 'active',
    ]);
    WebsiteDataAssistant::fake()->preventStrayPrompts();

    $this->actingAs($outsider)
        ->post(route('admin.websites.assistant.questions.store', $website), ['question' => 'Show me the data'])
        ->assertForbidden();

    expect(WebsiteAiQuestion::query()->exists())->toBeFalse();
    WebsiteDataAssistant::assertNeverPrompted();
});

it('enforces the weekly question limit before prompting the agent', function (): void {
    [$owner, $website] = completeWebsiteWorkspace();
    config(['memberships.website_ai_questions_per_week' => 2]);
    WebsiteAiQuestion::factory()->count(2)->for($website)->for($owner, 'user')->create();
    WebsiteDataAssistant::fake()->preventStrayPrompts();

    $this->actingAs($owner)
        ->from(route('admin.websites.show', [$website, 'tab' => 'assistant']))
        ->post(route('admin.websites.assistant.questions.store', $website), ['question' => 'One more question?'])
        ->assertRedirect(route('admin.websites.show', [$website, 'tab' => 'assistant']))
        ->assertSessionHasErrors('question');

    expect(WebsiteAiQuestion::query()->count())->toBe(2);
    WebsiteDataAssistant::assertNeverPrompted();
});

it('shows a locked assistant preview to lower membership tiers', function (): void {
    $owner = User::factory()->create([
        'membership_tier' => MembershipPlan::GROWTH,
        'membership_status' => 'active',
    ]);
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('admin.websites.show', [$website, 'tab' => 'assistant']))
        ->assertSuccessful()
        ->assertSee('Ask Sitewell')
        ->assertSee('Upgrade to Complete')
        ->assertDontSee('What would you like to understand?');
});
