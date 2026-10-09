@php
    $isHub = !isset($page);
    $schemaUrl = $isHub ? route('marketing.agencies') : route('marketing.agencies.show', $slug);
    $schemaTitle = $isHub ? 'Offer SEO to your clients. We’ll do the work.' : $page['heading'];
    $breadcrumbs = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('marketing.home')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'For agencies', 'item' => route('marketing.agencies')],
    ];
    if (!$isHub) {
        $breadcrumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $page['label'], 'item' => $schemaUrl];
    }
    $entity = !$isHub && $page['kind'] === 'article'
        ? ['@type' => 'Article', 'headline' => $schemaTitle, 'author' => ['@type' => 'Organization', 'name' => 'Sitewell', 'url' => route('marketing.home')], 'mainEntityOfPage' => $schemaUrl]
        : ['@type' => 'WebPage', 'name' => $schemaTitle];
    $schema = ['@context' => 'https://schema.org', '@graph' => [
        [...$entity, 'url' => $schemaUrl, 'description' => $isHub ? 'Fully managed SEO and website care for web agencies, delivered by Ross and Sitewell.' : $page['description']],
        ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbs],
    ]];
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
