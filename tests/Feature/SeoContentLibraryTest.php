<?php

use Illuminate\Support\Str;

it('renders every new page with its own metadata, content, canonical and sitemap entry', function (): void {
    $sitemap = $this->get(route('marketing.sitemap'))->assertSuccessful();
    $pages = collect(config('seo_library.landing_pages'))->map(fn (array $page, string $slug): array => [$page, route('marketing.landing', $slug)])
        ->values()->merge(collect(config('seo_library.industries'))->map(fn (array $page, string $slug): array => [$page, route('marketing.industry', $slug)])->values())
        ->merge(collect(config('seo_library.articles'))->map(fn (array $page): array => [$page, route('marketing.article', $page['slug'])]));

    expect($pages)->toHaveCount(43);
    $titles = [];
    $descriptions = [];

    foreach ($pages as [$page, $url]) {
        $response = $this->get($url.'?utm_source=content-check')->assertSuccessful();
        $html = $response->getContent();
        $description = $page['meta_description'] ?? $page['excerpt'] ?? $page['description'];
        $response->assertSee('<title>'.e($page['seo_title']).'</title>', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('<meta property="og:url" content="'.$url.'">', false)
            ->assertSee('<meta property="og:title" content="'.e($page['seo_title']).'">', false)
            ->assertSee('<meta name="description" content="'.e($description).'">', false);
        expect(preg_match_all('/<h1\b/i', $html))->toBe(1);
        foreach ($page['sections'] as $section) {
            $response->assertSee($section['heading']);
        }
        $sitemap->assertSee('<loc>'.$url.'</loc>', false);
        $titles[] = $page['seo_title'];
        $descriptions[] = $description;
    }

    expect(array_unique($titles))->toHaveCount(43)
        ->and(array_unique($descriptions))->toHaveCount(43);
});

it('keeps all sitemap pages reachable through public links and checks internal link destinations', function (): void {
    $xml = simplexml_load_string($this->get(route('marketing.sitemap'))->assertSuccessful()->getContent());
    $urls = [];
    foreach ($xml->url as $entry) {
        $urls[] = (string) $entry->loc;
    }
    expect(array_unique($urls))->toHaveCount(count($urls));
    $documents = [];
    $inbound = [];
    $links = [];
    foreach ($urls as $url) {
        $html = $this->get($url)->assertSuccessful()->getContent();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $documents[$url] = $document;
        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            if (str_starts_with($href, '#')) {
                $href = $url.$href;
            }
            if (parse_url($href, PHP_URL_HOST) !== parse_url(url('/'), PHP_URL_HOST)) {
                continue;
            }
            $destination = Str::before(Str::before($href, '#'), '?');
            $links[$href] = $destination;
            if ($destination !== $url) {
                $inbound[$destination] = true;
            }
        }
    }
    foreach ($urls as $url) {
        expect(isset($inbound[$url]))->toBeTrue('No inbound link to '.$url);
    }
    foreach ($links as $href => $destination) {
        if ($destination === route('marketing.ppc.book')) {
            $response = $this->get($destination)->assertRedirect();
            expect(Str::before($response->headers->get('Location'), '?'))->toBe(Str::before(config('marketing.booking_url'), '?'));

            continue;
        }
        if (! isset($documents[$destination])) {
            $this->get($destination)->assertStatus(200);
        }
        if (str_contains($href, '#') && isset($documents[$destination])) {
            $fragment = Str::after($href, '#');
            $xpath = new DOMXPath($documents[$destination]);
            expect($xpath->query('//*[@id="'.$fragment.'"]')->length)->toBeGreaterThan(0, 'Broken fragment '.$href);
        }
    }
});

it('publishes article authorship and dates and keeps FAQ schema identical to visible answers', function (): void {
    foreach (config('seo_library.articles') as $article) {
        $url = route('marketing.article', $article['slug']);
        $html = $this->get($url)->assertSuccessful()->getContent();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
        $schema = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        expect($schema['@type'])->toBe('BlogPosting')
            ->and($schema['headline'])->toBe($article['title'])
            ->and($schema['author']['name'])->toBe('Sitewell')
            ->and($schema['publisher']['url'])->toBe(route('marketing.home'))
            ->and($schema['datePublished'])->toBe($article['date_iso'])
            ->and($schema['mainEntityOfPage'])->toBe($url);
        expect($html)->toContain('By Sitewell');
    }
    foreach (config('seo_library.landing_pages') as $slug => $page) {
        $response = $this->get(route('marketing.landing', $slug))->assertSuccessful();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $match);
        $schema = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        expect($schema['@type'])->toBe('FAQPage');
        foreach ($page['faqs'] as $index => [$question, $answer]) {
            $response->assertSee($question)->assertSee($answer);
            expect($schema['mainEntity'][$index]['name'])->toBe($question)
                ->and($schema['mainEntity'][$index]['acceptedAnswer']['text'])->toBe($answer);
        }
    }
});

it('improves existing topic URLs and rejects unknown library slugs', function (): void {
    foreach (config('seo_library.supplements') as $slug => $sections) {
        $route = array_key_exists($slug, config('marketing.landing_pages')) ? 'marketing.landing' : 'marketing.article';
        $response = $this->get(route($route, $slug))->assertSuccessful();
        foreach ($sections as $section) {
            $response->assertSee($section['heading']);
        }
    }
    $this->get('/journal/not-a-real-guide')->assertNotFound();
    $this->get('/for/not-a-real-industry')->assertNotFound();
    $this->get('/not-a-real-seo-service')->assertNotFound();
});
