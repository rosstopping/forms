@extends('layouts.marketing')

@section('title', $page['seo_title'])
@section('concise_title', 'yes')
@section('meta_description', $page['meta_description'] ?? $page['description'])
@section('structured_data')
    @php
        $breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('marketing.home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Services', 'item' => route('marketing.features')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $page['eyebrow'], 'item' => request()->url()],
            ],
        ];
    @endphp
    <script type="application/ld+json">@json($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
    @if (isset($faqSchema))<script type="application/ld+json">{!! $faqSchema !!}</script>@endif
@endsection

@section('content')
    <section class="marketing-hero border-b border-ink/10">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <nav aria-label="Breadcrumb" class="text-base text-garden sm:text-sm"><a class="underline underline-offset-4 hover:text-moss" href="{{ route('marketing.home') }}">Home</a> / <a class="underline underline-offset-4 hover:text-moss" href="{{ route('marketing.features') }}">Services</a> / <span aria-current="page">{{ $page['eyebrow'] }}</span></nav>
            <h1 class="mt-8 max-w-[24ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-6xl">{{ $page['title'] }}</h1>
            <p class="mt-6 max-w-[60ch] text-pretty text-lg text-ink/70">{{ $page['description'] }}</p>
        </div>
    </section>
    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1fr_3fr] lg:px-10">
        <nav aria-label="On this page" class="text-base sm:text-sm">
            <p class="font-medium">On this page</p>
            <ul role="list" class="mt-5 grid gap-4">
                @foreach ($page['sections'] as $section)
                    <li><a class="text-ink/65 underline decoration-ink/20 underline-offset-4 hover:text-garden" href="#{{ Illuminate\Support\Str::slug($section['heading']) }}">{{ $section['heading'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <div class="prose min-w-0 max-w-[70ch]">
            <x-marketing.content-sections :sections="$page['sections']" />
            @if (! empty($page['faqs']))
                <h2>Before you get started</h2>
                @foreach ($page['faqs'] as [$question, $answer])
                    <h3>{{ $question }}</h3><p>{{ $answer }}</p>
                @endforeach
            @endif
        </div>
    </div>
    <x-marketing.cta :title="$page['cta']['title']" :text="$page['cta']['text']" :label="$page['cta']['label']" :route="$page['cta']['route']" />
@endsection
