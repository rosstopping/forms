<?php

use App\Ai\Agents\AuditServiceQuestionSelector;
use App\Services\MarketingAuditAiVisibility;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

it('uses evidenced own services regardless of client or informational rankings and caches selection', function (): void {
    Http::preventStrayRequests();
    $context = 'We build booking systems and bespoke websites for businesses. Our clients include VVIP Zante and Northern Belle.';
    AuditServiceQuestionSelector::fake([['supported' => true, 'client_names' => ['VVIP Zante', 'Northern Belle'], 'services' => [
        ['service' => 'booking systems', 'evidence' => 'We build booking systems and bespoke websites for businesses.'],
        ['service' => 'bespoke websites', 'evidence' => 'We build booking systems and bespoke websites for businesses.'],
    ]]])->preventStrayPrompts();
    $service = app(MarketingAuditAiVisibility::class);
    $questions = $service->questions('digizu.co.uk', ['keywords' => [['term' => 'vvip zante'], ['term' => 'google business posts']]], $context);
    expect($questions)->toBe(['Which businesses would you recommend for booking systems?', 'Which businesses would you recommend for bespoke websites?']);
    expect($service->questions('digizu.co.uk', null, $context))->toBe($questions);
    $selection = $service->selection('digizu.co.uk', $context);
    expect($selection['evidence'][0]['quote'])->toContain('We build booking systems')->and($selection['method'])->toBe('own_services_v2');
    AuditServiceQuestionSelector::assertPrompted(fn ($prompt): bool => ! str_contains($prompt->prompt, 'google business posts'));
    Http::assertNothingSent();
});

it('rejects clients own brand invented evidence duplicates and unsafe question fragments', function (): void {
    $context = 'We offer website design and SEO services at Digizu. Our client VVIP Zante sells event tickets.';
    $items = [
        ['service' => 'VVIP Zante', 'evidence' => 'Our client VVIP Zante sells event tickets.'],
        ['service' => 'Digizu', 'evidence' => 'We offer website design and SEO services at Digizu.'],
        ['service' => 'accountancy', 'evidence' => 'We offer accountancy and tax advice.'],
        ['service' => 'website design', 'evidence' => 'We offer website design and SEO services at Digizu.'],
        ['service' => 'website design', 'evidence' => 'We offer website design and SEO services at Digizu.'],
        ['service' => 'SEO services?', 'evidence' => 'We offer website design and SEO services at Digizu.'],
    ];
    $result = app(MarketingAuditAiVisibility::class)->validatedSelection('digizu.co.uk', $context, ['supported' => true, 'client_names' => ['VVIP Zante'], 'services' => $items]);
    expect($result['questions'])->toBe(['Which businesses would you recommend for website design?']);
});

it('never falls back to rankings when own service evidence is missing or unsupported', function (): void {
    AuditServiceQuestionSelector::fake([['supported' => false, 'client_names' => ['VVIP Zante'], 'services' => []]])->preventStrayPrompts();
    $service = app(MarketingAuditAiVisibility::class);
    $seo = ['keywords' => [['term' => 'vvip zante'], ['term' => 'seo content writing agency']]];
    expect($service->questions('digizu.co.uk', $seo))->toBe([]);
    expect($service->questions('digizu.co.uk', $seo, 'Our portfolio shows work for VVIP Zante and other client businesses.'))->toBe([]);
});

it('keeps missing or failed service question research from failing the full audit', function (): void {
    Exceptions::fake();
    AuditServiceQuestionSelector::fake(fn () => throw new RuntimeException('Provider unavailable'))->preventStrayPrompts();
    expect(app(MarketingAuditAiVisibility::class)->selection('example.com', 'We provide boiler servicing and central heating repairs for local homeowners.')['questions'])->toBe([]);
});

it('includes an evidenced SEO offer even when it follows design and software services', function (): void {
    $context = 'We offer website design, custom software and SEO services for small businesses.';
    $items = array_map(fn ($name) => ['service' => $name, 'evidence' => $context], ['website design', 'custom software', 'SEO services']);
    $result = app(MarketingAuditAiVisibility::class)->validatedSelection('agency.example', $context, ['supported' => true, 'client_names' => [], 'services' => $items]);
    expect($result['questions'])->toBe(['Which businesses would you recommend for SEO services?', 'Which businesses would you recommend for website design?']);
});

it('keeps questions specific to a non SEO business and never adds an unevidenced SEO offer', function (): void {
    $context = 'We provide boiler servicing, heating repairs and bathroom installation for homeowners.';
    $items = array_map(fn ($name) => ['service' => $name, 'evidence' => $context], ['boiler servicing', 'heating repairs', 'bathroom installation']);
    $items[] = ['service' => 'SEO services', 'evidence' => 'We provide SEO services for local businesses.'];
    $result = app(MarketingAuditAiVisibility::class)->validatedSelection('plumber.example', $context, ['supported' => true, 'client_names' => [], 'services' => $items]);
    expect($result['questions'])->toBe(['Which businesses would you recommend for boiler servicing?', 'Which businesses would you recommend for heating repairs?']);
});
