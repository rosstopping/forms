<?php

use App\Services\MarketingAuditJourneyReview;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('extracts bounded journey observations without making requests or reading form values', function (): void {
    Http::preventStrayRequests();
    $html = <<<'HTML'
    <html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
    <h1>Local plumbing</h1><a href="/book?token=private">Book a visit</a><a href="mailto:hello@example.com?subject=secret">Email us</a>
    <form><label for="name">Name</label><input id="name" required value="PRIVATE VALUE"><input type="email" required><input type="hidden" name="token" value="PRIVATE TOKEN"><input type="submit" value="Send"></form>
    <img src="photo.jpg"><img src="decoration.jpg" alt="">
    </body></html>
    HTML;
    $review = app(MarketingAuditJourneyReview::class)->inspect($html, 'https://example.com/services/');
    $findings = collect($review['findings'])->keyBy('key');

    expect($review['status'])->toBe('completed')
        ->and($review['source_url'])->toBe('https://example.com/services/')
        ->and($review['sample_sha256'])->toBe(hash('sha256', $html))
        ->and($review['routes'][0]['destination'])->toBe('https://example.com/book')
        ->and($review['routes'][1]['destination'])->toBe('mailto:hello@example.com')
        ->and($findings['form_requirements']['observation'])->toContain('2 input controls, 2 marked required and 1 without a detected label')
        ->and($findings['image_descriptions']['observation'])->toContain('Images without an alt attribute: 1.')
        ->and(json_encode($review))->not->toContain('PRIVATE VALUE', 'PRIVATE TOKEN', 'token=private', 'subject=secret')
        ->and($review['limitations'])->toContain('booking completion have not been tested');
    Http::assertNothingSent();
});

it('recognises accessible labels and distinguishes suggestions from evidence', function (): void {
    $review = app(MarketingAuditJourneyReview::class)->inspect(<<<'HTML'
    <form><label>Name<input required></label><input aria-label="Email"><span id="phone-label">Phone</span><input aria-labelledby="phone-label"><input title="Address"><input disabled><input hidden></form>
    HTML, 'https://example.com');
    $finding = collect($review['findings'])->firstWhere('key', 'form_requirements');

    expect($finding['kind'])->toBe('HTML observation')
        ->and($finding['status'])->toBe('Observed')
        ->and($finding['observation'])->toContain('4 input controls, 1 marked required and 0 without a detected label')
        ->and($finding['recommendation'])->toContain('No form was submitted.')
        ->and($finding['evidence']['selector'])->toBe('(//form[not(ancestor::template)])[position()<=6]');
});

it('bounds evidence samples and removes unsafe destinations', function (): void {
    $html = '<a href="javascript:alert(1)">Book</a><a href="https://user:password@example.com/book">Book</a><a href="#booking">Book</a><a href="data:text/html,x">Contact</a>';
    for ($index = 0; $index < 20; $index++) {
        $html .= '<a href="/book/'.$index.'">Book '.$index.'</a><form><input value="secret"></form>';
    }
    $review = app(MarketingAuditJourneyReview::class)->inspect($html, 'https://example.com');

    expect($review['routes'])->toHaveCount(8)
        ->and(json_encode($review))->not->toContain('javascript:', 'password', 'data:text/html')
        ->and(collect($review['findings'])->firstWhere('key', 'form_requirements')['title'])->toBe('Form elements inspected: 6');
});

it('keeps absence observations limited to the returned HTML', function (): void {
    $review = app(MarketingAuditJourneyReview::class)->inspect('<div id="app"></div>', 'https://example.com');
    $findings = collect($review['findings'])->keyBy('key');

    expect($findings['enquiry_routes']['observation'])->toContain('Script-rendered routes may still exist')
        ->and($findings['form_requirements']['observation'])->toContain('another page or rendered by scripts')
        ->and($findings['mobile_viewport']['status'])->toBe('Review suggested')
        ->and($review['limitations'])->toContain('do not change the website health score');
});

it('returns unavailable for an empty page and labels truncated samples', function (): void {
    $service = app(MarketingAuditJourneyReview::class);
    expect($service->inspect('', 'https://example.com')['status'])->toBe('unavailable')
        ->and($service->inspect('', 'https://example.com')['findings'])->toBe([])
        ->and($service->inspect('<h1>Title</h1>'.str_repeat(' ', 2_000_000), 'https://example.com')['partial'])->toBeTrue();
});

it('flags declared zoom restrictions without claiming a mobile browser test', function (): void {
    $review = app(MarketingAuditJourneyReview::class)->inspect('<meta name="viewport" content="width=device-width, user-scalable=no">', 'https://example.com');
    $finding = collect($review['findings'])->firstWhere('key', 'mobile_zoom');

    expect($finding['status'])->toBe('Review suggested')
        ->and($finding['observation'])->toContain('some browsers')
        ->and($finding['evidence']['excerpt'])->toContain('user-scalable=no');
});

it('does not treat a non HTML response as evidence of missing website features', function (): void {
    $review = app(MarketingAuditJourneyReview::class)->inspect('{"message":"blocked"}', 'https://example.com', 'application/json');
    expect($review['status'])->toBe('unavailable')->and($review['findings'])->toBe([]);
});
