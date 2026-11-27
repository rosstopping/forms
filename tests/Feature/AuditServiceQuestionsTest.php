<?php

use App\Ai\Agents\AuditServiceQuestionSelector;
use App\Services\AiVisibilityAnalyzer;
use App\Services\MarketingAuditAiVisibility;
use App\Services\OpenAiVisibilityProvider;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

function buyerQuestion(string $service, string $evidence, string $question, string $intent = 'provider', string $location = '', string $locationEvidence = ''): array
{
    return ['service' => $service, 'evidence' => $evidence, 'question' => $question, 'intent' => $intent, 'location' => $location, 'location_evidence' => $locationEvidence];
}

it('uses complete AI generated buyer questions and caches selection without ranking keywords', function (): void {
    Http::preventStrayRequests();
    $context = 'Digizu provides monthly SEO services and website design for small businesses. Our clients include VVIP Zante.';
    AuditServiceQuestionSelector::fake([['supported' => true, 'business_name' => 'Digizu', 'client_names' => ['VVIP Zante'], 'services' => [
        buyerQuestion('monthly SEO services', 'Digizu provides monthly SEO services and website design for small businesses.', 'Who can manage monthly SEO for my small business?'),
        buyerQuestion('website design', 'Digizu provides monthly SEO services and website design for small businesses.', 'How much does a small business website design cost?', 'price'),
    ]]])->preventStrayPrompts();
    $service = app(MarketingAuditAiVisibility::class);
    $questions = $service->questions('digizu.co.uk', ['keywords' => [['term' => 'vvip zante']]], $context);
    expect($questions)->toBe(['Who can manage monthly SEO for my small business?', 'How much does a small business website design cost?']);
    expect($service->questions('digizu.co.uk', null, $context))->toBe($questions);
    expect($service->selection('digizu.co.uk', $context)['method'])->toBe('natural_buyer_questions_v1');
    AuditServiceQuestionSelector::assertPrompted(fn ($prompt): bool => ! str_contains($prompt->prompt, '"keywords"'));
    Http::assertNothingSent();
});

it('supports evidenced event booking and pricing questions for an unrelated business', function (): void {
    $offer = 'We sell tickets for White Party and VIP boat party events in Zante.';
    $items = [
        buyerQuestion('White Party', $offer, 'Where can I book the White Party in Zante?', 'booking', 'Zante', $offer),
        buyerQuestion('VIP boat party', $offer, 'How much does a VIP boat party in Zante cost?', 'price', 'Zante', $offer),
    ];
    $result = app(MarketingAuditAiVisibility::class)->validatedSelection('events.example', $offer, ['supported' => true, 'business_name' => 'Event Seller', 'client_names' => [], 'services' => $items]);
    expect($result['questions'])->toBe(array_column($items, 'question'));
    expect(implode(' ', $result['questions']))->not->toContain('SEO');
});

it('rejects the old fixed template company hints client names and invented evidence', function (): void {
    $offer = 'Digizu provides SEO services for small businesses. Our client VVIP Zante sells event tickets.';
    $items = [
        buyerQuestion('SEO services', $offer, 'Which businesses would you recommend for SEO services?'),
        buyerQuestion('SEO services', $offer, 'Can Digizu manage SEO for my small business?'),
        buyerQuestion('SEO services', $offer, 'Can VVIP Zante provide SEO services for me?'),
        buyerQuestion('accountancy', 'We provide accountancy to local businesses.', 'Who provides accountancy for my small business?'),
    ];
    expect(app(MarketingAuditAiVisibility::class)->validatedSelection('digizu.co.uk', $offer, ['supported' => true, 'business_name' => 'Digizu', 'client_names' => ['VVIP Zante'], 'services' => $items])['questions'])->toBe([]);
});

it('rejects invented locations prices unrelated topics and URL hints', function (): void {
    $offer = 'We provide boiler servicing and heating repairs to homeowners in Doncaster.';
    $items = [
        buyerQuestion('boiler servicing', $offer, 'Who offers boiler servicing in Leeds?', 'provider', 'Leeds', 'We serve Leeds.'),
        buyerQuestion('boiler servicing', $offer, 'Who offers boiler servicing for £50?', 'price'),
        buyerQuestion('boiler servicing', $offer, 'Can I book a boat party in Doncaster?', 'booking'),
        buyerQuestion('boiler servicing', $offer, 'Who offers boiler servicing at example.com?'),
    ];
    expect(app(MarketingAuditAiVisibility::class)->validatedSelection('plumber.example', $offer, ['supported' => true, 'services' => $items])['questions'])->toBe([]);
});

it('does not fabricate fallback questions when generation is unsupported invalid or unavailable', function (): void {
    Exceptions::fake();
    $service = app(MarketingAuditAiVisibility::class);
    expect($service->questions('example.com', ['keywords' => [['term' => 'SEO services']]]))->toBe([]);
    expect($service->validatedSelection('example.com', 'We offer website design for local businesses.', ['supported' => true, 'services' => [buyerQuestion('website design', 'We offer website design for local businesses.', '')]])['questions'])->toBe([]);
    AuditServiceQuestionSelector::fake(fn () => throw new RuntimeException('Unavailable'))->preventStrayPrompts();
    expect($service->selection('example.com', 'We offer website design for local businesses.')['questions'])->toBe([]);
});

it('deduplicates and caps generated questions at two without generating another template', function (): void {
    $offer = 'We offer boiler servicing and heating repairs for homeowners.';
    $one = buyerQuestion('boiler servicing', $offer, 'Who can service my boiler before winter?');
    $two = buyerQuestion('heating repairs', $offer, 'How much do heating repairs usually cost?', 'price');
    $three = buyerQuestion('boiler servicing', $offer, 'Can I book boiler servicing online?', 'booking');
    $result = app(MarketingAuditAiVisibility::class)->validatedSelection('plumber.example', $offer, ['supported' => true, 'services' => [$one, $one, $two, $three]]);
    expect($result['questions'])->toBe([$one['question'], $two['question']]);
});

it('sends only the generated question to the independent answer stage', function (): void {
    $provider = Mockery::mock(OpenAiVisibilityProvider::class);
    $provider->shouldReceive('forPublicAudit')->once()->andReturnSelf();
    $provider->shouldReceive('model')->once()->andReturn('test-model');
    $question = 'Who can manage monthly SEO for my small business?';
    $provider->shouldReceive('check')->once()->with($question, 'test-model')->andReturn(['text' => 'No supported recommendation.', 'citations' => []]);
    $service = new MarketingAuditAiVisibility($provider, app(AiVisibilityAnalyzer::class));
    $result = $service->check('digizu.co.uk', $question);
    expect($result['website_mentioned'])->toBeFalse()->and($result['website_cited'])->toBeFalse();
});

it('rejects questions addressed to the AI as the supplier', function (): void {
    $offer = 'We build booking systems and customer portals for businesses.';
    $item = buyerQuestion('booking systems', $offer, 'Can you build a booking system for my business?');
    expect(app(MarketingAuditAiVisibility::class)->validatedSelection('agency.example', $offer, ['supported' => true, 'services' => [$item]])['questions'])->toBe([]);
});

it('rejects seller addressed questions and published-price targeting without a fabricated replacement', function (): void {
    $offer = 'We offer website design at £149 per month and SEO services.';
    $items = [
        buyerQuestion('website design', $offer, 'Which provider offers website design for £149 per month?'),
        buyerQuestion('SEO services', $offer, 'How much do your SEO services cost?', 'price'),
    ];
    expect(app(MarketingAuditAiVisibility::class)->validatedSelection('agency.example', $offer, ['supported' => true, 'services' => $items])['questions'])->toBe([]);
});
