@extends('layouts.marketing')
@section('title', $page['title'])
@section('concise_title', '1')
@section('meta_description', $page['description'])
@section('og_type', $page['kind'] === 'article' ? 'article' : 'website')
@section('structured_data')
    @include('marketing.agencies.schema')
@endsection
@section('content')
    <section class="border-b border-ink/10 py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-base text-ink/60 sm:text-sm"><a href="{{ route('marketing.home') }}" class="hover:text-garden">Home</a><span aria-hidden="true">/</span><a href="{{ route('marketing.agencies') }}" class="hover:text-garden">For agencies</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['label'] }}</span></nav>
            <p class="mt-10 font-mono text-base uppercase tracking-wide text-garden sm:text-sm">{{ $page['kind'] === 'article' ? 'Agency field notes' : 'Sitewell for agencies' }}</p>
            <h1 class="mt-4 max-w-[24ch] text-balance font-display text-4xl font-semibold tracking-tight sm:text-6xl">{{ $page['heading'] }}</h1>
            <p class="mt-6 max-w-[65ch] text-pretty text-lg/8 text-ink/70">{{ $page['intro'] }}</p>
            <div class="mt-8 flex flex-wrap items-center gap-6"><a href="{{ route('marketing.agencies') }}#join-beta" class="rounded-md bg-garden px-5 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden">Join the agency beta <span aria-hidden="true">↗</span></a><a href="{{ route('marketing.agencies') }}#how-it-works" class="text-base font-medium underline decoration-ink/25 underline-offset-4 hover:decoration-garden">See how it works</a></div>
            @if ($page['kind'] === 'article')<p class="mt-6 text-base text-ink/55 sm:text-sm">By Sitewell · A practical guide for agency owners</p>@endif
        </div>
    </section>
    <section class="py-14 sm:py-20">
        <div class="mx-auto grid max-w-7xl items-start gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_1fr] lg:px-10">
            <article class="grid min-w-0 gap-12">
                @foreach ($page['sections'] as $section)
                    <section>
                        <h2 class="max-w-[35ch] text-balance font-display text-3xl font-semibold tracking-tight">{{ $section['heading'] }}</h2>
                        <p class="mt-5 max-w-[68ch] text-pretty text-base/8 text-ink/75">{{ $section['body'] }}</p>
                        @isset($section['points'])<ul role="list" class="mt-5 grid list-disc gap-3 pl-5 text-base/7 text-ink/75">@foreach ($section['points'] as $point)<li>{{ $point }}</li>@endforeach</ul>@endisset
                        @isset($section['link'])<a href="{{ route('marketing.agencies.show', $section['link'][0]) }}" class="mt-5 inline-block text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">{{ $section['link'][1] }} <span aria-hidden="true">→</span></a>@endisset
                    </section>
                @endforeach
                @isset($page['visual'])@include('marketing.agencies.preview', ['preview' => $page['visual']])@endisset
                <aside class="border-l-2 border-garden pl-6" aria-label="Practical example"><p class="font-mono text-base uppercase tracking-wide text-garden sm:text-sm">In practice</p><h2 class="mt-3 text-balance text-xl font-semibold">{{ $page['example']['title'] }}</h2><p class="mt-4 max-w-[68ch] text-pretty text-base/8 text-ink/75">{{ $page['example']['body'] }}</p></aside>
                <section><h2 class="font-display text-3xl font-semibold tracking-tight">A few useful answers</h2><div class="mt-6 divide-y divide-ink/15 border-y border-ink/15">@foreach ($page['faqs'] as [$question, $answer])<details class="group py-5"><summary class="cursor-pointer text-base font-medium focus-visible:outline-offset-4">{{ $question }}</summary><p class="mt-4 max-w-[68ch] text-pretty text-base/7 text-ink/70">{{ $answer }}</p></details>@endforeach</div></section>
            </article>
            <aside class="grid gap-8 border-t border-ink/15 pt-6 lg:sticky lg:top-8" aria-label="Related agency resources">
                <div><p class="font-mono text-base uppercase tracking-wide text-garden sm:text-sm">Keep exploring</p><nav class="mt-4 grid gap-4">@foreach ($page['related'] as $related)<a class="text-base underline decoration-ink/20 underline-offset-4 hover:text-garden" href="{{ route('marketing.agencies.show', $related) }}">{{ config('agencies.pages')[$related]['label'] }} <span aria-hidden="true">→</span></a>@endforeach</nav></div>
                <div class="rounded-xl bg-lichen/60 p-6"><h2 class="text-lg font-semibold">Built with agencies</h2><p class="mt-3 text-pretty text-base/7 text-ink/70">Existing Sitewell tools underpin the offer. Agency branding, a dedicated portfolio dashboard and custom domains are still being explored.</p><a href="{{ route('marketing.agencies') }}#available-now" class="mt-4 inline-block text-base font-medium underline underline-offset-4 hover:text-garden">What’s available now?</a></div>
                <a href="{{ route('marketing.agencies') }}" class="text-base font-medium text-garden">← Back to Sitewell for agencies</a>
            </aside>
        </div>
    </section>
    @include('marketing.agencies.cta')
@endsection
