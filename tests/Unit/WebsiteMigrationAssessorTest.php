<?php

use App\Services\WebsiteMigrationAssessor;

it('assesses easy medium hard and unknown migration difficulty', function (int $pageCount, int $maximumPages, array $pages, string $difficulty): void {
    $result = (new WebsiteMigrationAssessor)->assess($pageCount, $maximumPages, $pages);

    expect($result['migration_difficulty'])->toBe($difficulty)
        ->and($result['migration_difficulty_reason'])->not->toBeEmpty();
})->with([
    'easy brochure website' => [8, 20, [['url' => 'https://example.com/services', 'title' => 'Services']], 'easy'],
    'medium configured page limit' => [21, 20, [['url' => 'https://example.com/services', 'title' => 'Services']], 'medium'],
    'medium booking system' => [8, 20, [['url' => 'https://example.com/booking', 'title' => 'Book online']], 'medium'],
    'hard ecommerce site' => [8, 20, [['url' => 'https://example.com/checkout', 'title' => 'Checkout']], 'hard'],
    'hard large site' => [41, 100, [['url' => 'https://example.com/services', 'title' => 'Services']], 'hard'],
    'unknown empty crawl' => [0, 20, [], 'unknown'],
]);
