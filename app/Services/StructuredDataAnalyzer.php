<?php

namespace App\Services;

use DOMXPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StructuredDataAnalyzer
{
    /** @var array<string, array<int, string>> */
    protected array $recommendedProperties = [
        'Article' => ['headline', 'image', 'datePublished', 'author'],
        'NewsArticle' => ['headline', 'image', 'datePublished', 'author'],
        'BlogPosting' => ['headline', 'image', 'datePublished', 'author'],
        'BreadcrumbList' => ['itemListElement'],
        'Event' => ['name', 'startDate', 'location'],
        'LocalBusiness' => ['name', 'address'],
        'Organization' => ['name', 'url', 'logo'],
        'Product' => ['name', 'image', 'offers|review|aggregateRating'],
        'WebSite' => ['name', 'url'],
    ];

    /** @return array<int, array<string, mixed>> */
    public function analyze(DOMXPath $xpath, string $url): array
    {
        $nodes = $xpath->query('//script[translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="application/ld+json"]');
        $blocks = $nodes === false ? [] : iterator_to_array($nodes);
        $invalidBlocks = 0;
        $entities = [];

        foreach ($blocks as $node) {
            try {
                $decoded = json_decode($node->textContent, true, flags: JSON_THROW_ON_ERROR);
                $entities = [...$entities, ...$this->rootEntities($decoded)];
            } catch (\JsonException) {
                $invalidBlocks++;
            }
        }

        $types = collect($entities)->flatMap(fn (array $entity): array => Arr::wrap($entity['@type'] ?? []))
            ->filter(fn (mixed $type): bool => is_string($type) && $type !== '')
            ->merge($this->markupTypes($xpath))
            ->unique()->values();
        $checks = [$this->check(
            'structured_data_syntax',
            'Structured data syntax',
            $invalidBlocks > 0 ? 'failed' : 'passed',
            $invalidBlocks > 0
                ? "{$invalidBlocks} of ".count($blocks).' JSON-LD blocks contain invalid JSON.'
                : count($blocks).' valid JSON-LD '.Str::plural('block', count($blocks)).' found.',
            ['blocks' => count($blocks), 'invalid_blocks' => $invalidBlocks, 'types' => $types->all()],
        )];

        if ($entities !== []) {
            $missingContext = collect($entities)->filter(fn (array $entity): bool => ! $this->hasSchemaContext($entity))->count();
            $checks[] = $this->check(
                'structured_data_context',
                'Schema.org context',
                $missingContext > 0 ? 'warning' : 'passed',
                $missingContext > 0 ? "{$missingContext} top-level structured data items do not declare a Schema.org context." : 'All top-level structured data items use a Schema.org context.',
            );
        }

        foreach ($entities as $entityIndex => $entity) {
            foreach (Arr::wrap($entity['@type'] ?? []) as $type) {
                if (! is_string($type) || ! isset($this->recommendedProperties[$type])) {
                    continue;
                }

                $missing = collect($this->recommendedProperties[$type])
                    ->reject(fn (string $property): bool => collect(explode('|', $property))->contains(fn (string $option): bool => filled(data_get($entity, $option))))
                    ->map(fn (string $property): string => str_replace('|', ', ', $property))
                    ->values();
                $checks[] = $this->check(
                    'structured_data_'.Str::snake($type).'_'.$entityIndex,
                    $type.' structured data',
                    $missing->isEmpty() ? 'passed' : 'warning',
                    $missing->isEmpty()
                        ? "The {$type} item includes the important properties checked by this report."
                        : "The {$type} item is missing recommended properties: ".$missing->implode(', ').'. Confirm they are visible and accurate before adding them.',
                    ['type' => $type, 'missing_properties' => $missing->all()],
                );
            }
        }

        return [...$checks, ...$this->opportunityChecks($xpath, $url, $types->all())];
    }

    /** @return array<int, array<string, mixed>> */
    protected function rootEntities(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return collect($decoded)->filter(fn (mixed $item): bool => is_array($item))->values()->all();
        }

        $graph = $decoded['@graph'] ?? null;

        if (is_array($graph)) {
            $context = $decoded['@context'] ?? null;

            return collect($graph)->filter(fn (mixed $item): bool => is_array($item))
                ->map(function (array $item) use ($context): array {
                    if (! isset($item['@context']) && $context !== null) {
                        $item['@context'] = $context;
                    }

                    return $item;
                })->values()->all();
        }

        return [$decoded];
    }

    protected function hasSchemaContext(array $entity): bool
    {
        return collect(Arr::wrap($entity['@context'] ?? []))
            ->contains(fn (mixed $context): bool => is_string($context) && Str::contains(Str::lower($context), 'schema.org'));
    }

    /** @param array<int, string> $types @return array<int, array<string, mixed>> */
    protected function opportunityChecks(DOMXPath $xpath, string $url, array $types): array
    {
        $checks = [];
        $path = (string) parse_url($url, PHP_URL_PATH);
        $isHomepage = $path === '' || $path === '/';
        $hasOrganization = collect($types)->contains(fn (string $type): bool => in_array($type, [
            'Organization', 'LocalBusiness', 'Store', 'Restaurant', 'FoodEstablishment', 'ProfessionalService',
            'MedicalBusiness', 'Dentist', 'LegalService', 'RealEstateAgent', 'TravelAgency', 'LodgingBusiness',
        ], true));

        if ($isHomepage && ! $hasOrganization) {
            $checks[] = $this->opportunity('organization', 'Organization schema opportunity', 'Consider Organization or the most specific LocalBusiness subtype on the homepage when the visible page contains accurate business name, URL, logo, contact, or address details.');
        }

        $hasArticleEvidence = (int) $xpath->evaluate('count(//article)') > 0
            || Str::lower(trim((string) $xpath->evaluate('string(//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="og:type"]/@content)'))) === 'article';

        if ($hasArticleEvidence && collect($types)->intersect(['Article', 'NewsArticle', 'BlogPosting'])->isEmpty()) {
            $checks[] = $this->opportunity('article', 'Article schema opportunity', 'This page appears to contain an article. Consider Article or BlogPosting markup using only the visible headline, author, publication date, and relevant images.');
        }

        $hasProductEvidence = Str::lower(trim((string) $xpath->evaluate('string(//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="og:type"]/@content)'))) === 'product'
            || (int) $xpath->evaluate('count(//*[@itemtype and contains(translate(@itemtype, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "schema.org/product")])') > 0;

        if ($hasProductEvidence && ! in_array('Product', $types, true)) {
            $checks[] = $this->opportunity('product', 'Product schema opportunity', 'This page appears to describe a product. Consider Product markup only for the product name, images, price, currency, availability, and other details customers can see on the page.');
        }

        $hasBreadcrumbEvidence = (int) $xpath->evaluate('count(//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "breadcrumb") or contains(translate(@aria-label, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "breadcrumb")])') > 0;

        if ($hasBreadcrumbEvidence && ! in_array('BreadcrumbList', $types, true)) {
            $checks[] = $this->opportunity('breadcrumb_list', 'Breadcrumb schema opportunity', 'Visible breadcrumbs were found without BreadcrumbList JSON-LD. Consider marking up the same navigation hierarchy and URLs.');
        }

        return $checks;
    }

    /** @return array<int, string> */
    protected function markupTypes(DOMXPath $xpath): array
    {
        $types = [];

        foreach (['//*[@itemtype]' => 'itemtype', '//*[@typeof]' => 'typeof'] as $query => $attribute) {
            $nodes = $xpath->query($query);

            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
                foreach (preg_split('/\s+/', trim($node->getAttribute($attribute))) ?: [] as $type) {
                    $types[] = Str::afterLast(rtrim($type, '/'), '/');
                }
            }
        }

        return collect($types)->filter()->unique()->values()->all();
    }

    /** @return array<string, mixed> */
    protected function opportunity(string $key, string $label, string $message): array
    {
        return $this->check('structured_data_opportunity_'.$key, $label, 'warning', $message, ['opportunity' => true]);
    }

    /** @param array<string, mixed> $details @return array<string, mixed> */
    protected function check(string $key, string $label, string $status, string $message, array $details = []): array
    {
        return compact('key', 'label', 'status', 'message', 'details');
    }
}
