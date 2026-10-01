@extends('layouts.marketing')

@section('title', $article['seo_title'] ?? $article['title'])
@section('meta_description', $article['excerpt'])
@section('og_type', 'article')
@if (isset($article['brief']))
    @section('concise_title', 'yes')
@endif
@section('structured_data')
    @php
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $article['title'],
            'description' => $article['excerpt'],
            'datePublished' => $article['date_iso'],
            'dateModified' => $article['date_modified'] ?? $article['date_iso'],
            'author' => ['@type' => 'Organization', 'name' => 'Sitewell', 'url' => route('marketing.about')],
            'publisher' => ['@type' => 'Organization', 'name' => 'Sitewell', 'url' => route('marketing.home')],
            'inLanguage' => 'en-GB',
            'mainEntityOfPage' => route('marketing.article', $article['slug']),
        ];
    @endphp
    <script type="application/ld+json">
        @json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    </script>
@endsection

@section('content')
    <article class="py-12 sm:py-16">
        <header class="mx-auto max-w-5xl px-5 sm:px-8 lg:px-10">
            <nav aria-label="Breadcrumb" class="text-base text-garden sm:text-sm"><a href="{{ route('marketing.home') }}" class="underline underline-offset-4 hover:text-moss">Home</a> / <a href="{{ route('marketing.journal') }}" class="underline underline-offset-4 hover:text-moss">Journal</a> / <span aria-current="page">{{ $article['category'] }}</span></nav>
            <p class="mt-10 text-base font-medium sm:text-sm text-garden">{{ $article['category'] }}</p>
            <h1 class="mt-5 max-w-[20ch] font-sans text-4xl font-medium tracking-tight text-pretty sm:text-6xl">{{ $article['title'] }}</h1>
            <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">{{ $article['excerpt'] }}</p>
            <p class="mt-6 font-mono text-sm text-ink/45">{{ $article['date'] }} · {{ $article['read_time'] }} · By <a href="{{ route('marketing.about') }}" class="underline underline-offset-4 hover:text-ink">Sitewell</a> @if (isset($article['date_modified'])) · Updated 28 September 2026 @endif</p>
        </header>
        <div class="prose mx-auto mt-16 max-w-[70ch] border-t border-ink/10 px-5 pt-12 sm:px-8 lg:px-10">
            <x-marketing.content-sections :sections="$article['sections']" />
        </div>
    </article>

    @if (isset($article['cta']))
        <x-marketing.cta :title="$article['cta']['title']" :text="$article['cta']['text']" :label="$article['cta']['label']" :route="$article['cta']['route']" :parameters="$article['cta']['parameters'] ?? []" />
    @else
        <x-marketing.cta />
    @endif
@endsection
