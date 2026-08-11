@extends('layouts.marketing')

@section('title', 'Journal')
@section('meta_description', 'Practical notes from Sitewell on website health, forms, search performance, and business growth.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">The Sitewell journal</p>
            <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Practical notes on looking after websites</h1>
            <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Clear guidance for business owners who want a healthier website, more reliable lead handling, and better online visibility.</p>
        </div>
    </section>

    <section class="border-t border-ink/10 pb-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="grid divide-y divide-ink/10 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                @foreach ($articles as $article)
                    <article class="flex flex-col justify-between gap-12 py-10 lg:px-8 lg:first:pl-0 lg:last:pr-0">
                        <div><p class="font-mono text-sm uppercase tracking-wide text-garden">{{ $article['category'] }}</p><h2 class="mt-5 font-display text-3xl font-semibold tracking-tight text-balance"><a href="{{ route('marketing.article', $article['slug']) }}" class="hover:text-garden">{{ $article['title'] }}</a></h2><p class="mt-5 text-pretty text-base text-ink/60 sm:text-sm">{{ $article['excerpt'] }}</p></div>
                        <div><p class="font-mono text-sm text-ink/45">{{ $article['date'] }} · {{ $article['read_time'] }}</p><a href="{{ route('marketing.article', $article['slug']) }}" class="mt-4 inline-flex text-base font-medium underline decoration-ink/20 underline-offset-4 hover:decoration-ink sm:text-sm">Read article →</a></div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
