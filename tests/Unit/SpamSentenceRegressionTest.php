<?php

use App\Services\SpamDetector;
use Tests\TestCase;

uses(TestCase::class);

it('does not turn sentence punctuation into a spam link', function (string $message): void {
    expect(app(SpamDetector::class)->isSpam([
        'name' => 'Example Customer',
        'email' => 'customer@example.com',
        'message' => $message,
    ]))->toBeFalse();
})->with([
    'normal sentences' => 'We need a new website. The current one is difficult to update.',
    'paragraphs' => "The platform should be efficient.\n\nThe next phase is development.",
    'lowercase following sentence' => 'We need a quote. please let us know the next steps.',
]);

it('continues to quarantine real and obfuscated links', function (string $message): void {
    expect(app(SpamDetector::class)->isSpam([
        'name' => 'Example Sender',
        'email' => 'sender@example.com',
        'message' => $message,
    ]))->toBeTrue();
})->with([
    'full URL' => 'Visit https://example.com/brief',
    'www' => 'Visit www.example.com',
    'bare domain' => 'Visit gnosis.link/brief',
    'obfuscated' => 'Visit brnd .li/delist',
    'spaced separator' => 'Visit brnd . li/delist',
]);
