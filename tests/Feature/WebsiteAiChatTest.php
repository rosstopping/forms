<?php

use App\Ai\Agents\WebsiteDataAssistant;
use App\Mail\WebsiteAiQuestionReported;
use App\Models\SearchConsoleMetric;
use App\Models\SeoKeyword;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use App\Models\WebsiteHealthReport;
use App\Services\WebsiteAiContext;
use App\Support\MembershipPlan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;

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
        ->assertRedirect(route('admin.websites.show', [$website, 'assistant' => 'open']));

    $record = WebsiteAiQuestion::query()->sole();
    expect($record->website_id)->toBe($website->id)
        ->and($record->user_id)->toBe($owner->id)
        ->and($record->status)->toBe('completed')
        ->and($record->answer)->toContain('meta description');

    $instructions = (new WebsiteDataAssistant($website->name, app(WebsiteAiContext::class)->for($website)))->instructions();
    expect((string) $instructions)->toContain('Northfield Roofing')
        ->toContain('roof repairs york')
        ->toContain('data_coverage')
        ->not->toContain('Private Competitor')
        ->not->toContain('secret query');
});

it('provides explicit first-party and estimated keyword movements with coverage limits', function (): void {
    [, $website] = completeWebsiteWorkspace();
    SearchConsoleMetric::factory()->for($website)->create([
        'month' => '2025-05-01', 'query' => 'roof repairs york',
        'dimension_key' => hash('sha256', 'roof repairs york'), 'position' => 18.4,
    ]);
    SearchConsoleMetric::factory()->for($website)->create([
        'month' => '2026-08-01', 'query' => 'roof repairs york',
        'dimension_key' => hash('sha256', 'roof repairs york'), 'position' => 5.2,
    ]);
    $snapshot = SeoSnapshot::factory()->for($website)->create(['snapshot_date' => '2026-08-14']);
    SeoKeyword::factory()->for($snapshot, 'snapshot')->for($website)->create([
        'keyword' => 'emergency roofer york', 'previous_position' => 31, 'position' => 8,
    ]);

    $context = app(WebsiteAiContext::class)->for($website);

    expect($context)->toContain('"query": "roof repairs york"')
        ->toContain('"first_month": "2025-05-01"')
        ->toContain('"latest_month": "2026-08-01"')
        ->toContain('"position_change": 13.2')
        ->toContain('"direction": "improved"')
        ->toContain('emergency roofer york')
        ->toContain('Provider-reported previous position');
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
        ->from(route('admin.websites.show', $website))
        ->post(route('admin.websites.assistant.questions.store', $website), ['question' => 'One more question?'])
        ->assertRedirect(route('admin.websites.show', $website))
        ->assertSessionHasErrors('question');

    expect(WebsiteAiQuestion::query()->count())->toBe(2);
    WebsiteDataAssistant::assertNeverPrompted();
});

it('records a traceable reference and reports provider failures', function (): void {
    [$owner, $website] = completeWebsiteWorkspace();
    Exceptions::fake();
    WebsiteDataAssistant::fake(fn (): never => throw new RuntimeException('Provider unavailable'));

    $this->actingAs($owner)
        ->post(route('admin.websites.assistant.questions.store', $website), ['question' => 'Which keywords improved?'])
        ->assertRedirect(route('admin.websites.show', [$website, 'assistant' => 'open']))
        ->assertSessionHas('error');

    $question = WebsiteAiQuestion::query()->sole();
    expect($question->status)->toBe('failed')
        ->and($question->error)->toBe("The assistant could not answer this question. Reference: WAI-{$question->id}.")
        ->and($question->failure_type)->toBe('RuntimeException')
        ->and($question->failure_detail)->toBe('Provider unavailable');
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Provider unavailable');
});

it('lets a user report an answer and emails every admin once', function (): void {
    Mail::fake();
    [$owner, $website] = completeWebsiteWorkspace();
    $firstAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $secondAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $question = WebsiteAiQuestion::factory()->for($website)->for($owner, 'user')->create();

    $this->actingAs($owner)
        ->post(route('admin.websites.assistant.questions.report', [$website, $question]), [
            'reason' => 'The stored query history should answer this.',
        ])
        ->assertRedirect(route('admin.websites.show', [$website, 'assistant' => 'open']))
        ->assertSessionHas('status', 'Thanks — this answer has been reported for investigation.');

    expect($question->fresh()->reported_at)->not->toBeNull()
        ->and($question->fresh()->report_reason)->toBe('The stored query history should answer this.');
    Mail::assertQueued(WebsiteAiQuestionReported::class, 2);
    Mail::assertQueued(WebsiteAiQuestionReported::class, fn (WebsiteAiQuestionReported $mail): bool => $mail->hasTo($firstAdmin->email));
    Mail::assertQueued(WebsiteAiQuestionReported::class, fn (WebsiteAiQuestionReported $mail): bool => $mail->hasTo($secondAdmin->email));

    $this->actingAs($owner)
        ->post(route('admin.websites.assistant.questions.report', [$website, $question]), ['reason' => 'Duplicate'])
        ->assertSessionHas('status', 'This answer has already been reported.');
    Mail::assertQueued(WebsiteAiQuestionReported::class, 2);
});

it('does not let users report another users answer', function (): void {
    [$owner, $website] = completeWebsiteWorkspace();
    $otherUser = User::factory()->create();
    $question = WebsiteAiQuestion::factory()->for($website)->for($otherUser, 'user')->create();

    $this->actingAs($owner)
        ->post(route('admin.websites.assistant.questions.report', [$website, $question]))
        ->assertForbidden();

    expect($question->fresh()->reported_at)->toBeNull();
});

it('lets an admin return a reported request to the weekly allowance only once', function (): void {
    [$owner, $website] = completeWebsiteWorkspace();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    config(['memberships.website_ai_questions_per_week' => 1]);
    $question = WebsiteAiQuestion::factory()->for($website)->for($owner, 'user')->create([
        'reported_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.website-ai-question-reports.show', $question))
        ->assertSuccessful()
        ->assertSee('Return to allowance');
    $this->post(route('admin.website-ai-question-reports.credit', $question))
        ->assertRedirect(route('admin.website-ai-question-reports.show', $question));

    expect($question->fresh()->credited_at)->not->toBeNull()
        ->and($question->fresh()->credited_by_user_id)->toBe($admin->id);

    $creditedAt = $question->fresh()->credited_at;
    $this->post(route('admin.website-ai-question-reports.credit', $question))->assertRedirect();
    expect($question->fresh()->credited_at->equalTo($creditedAt))->toBeTrue();

    WebsiteDataAssistant::fake([['in_scope' => true, 'answer' => 'Credited question replaced.']]);
    $this->actingAs($owner)
        ->post(route('admin.websites.assistant.questions.store', $website), ['question' => 'Try again?'])
        ->assertRedirect(route('admin.websites.show', [$website, 'assistant' => 'open']));
    expect(WebsiteAiQuestion::query()->whereBelongsTo($owner)->count())->toBe(2);
});

it('shows a locked assistant preview to lower membership tiers', function (): void {
    $owner = User::factory()->create([
        'membership_tier' => MembershipPlan::GROWTH,
        'membership_status' => 'active',
    ]);
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Ask Sitewell')
        ->assertSee('Complete feature')
        ->assertDontSee('What would you like to understand?');
});

it('renders the assistant as a fixed chat widget instead of a website tab', function (): void {
    [$owner, $website] = completeWebsiteWorkspace();

    $this->actingAs($owner)
        ->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('id="website-assistant"', false)
        ->assertSee('fixed right-3 bottom-3', false)
        ->assertSee('Which keywords have improved or declined recently?')
        ->assertSee('How have clicks and impressions changed over the last six months?')
        ->assertDontSee('website-tab-assistant', false)
        ->assertDontSee('website-panel-assistant', false);
});
