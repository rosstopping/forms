@extends('layouts.marketing')

@section('title', 'Journal')
@section('meta_description', 'Practical Sitewell guides to local SEO, Google visibility, website problems and better enquiries for UK small businesses.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">The Sitewell journal</p>
            <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Practical notes on looking after websites</h1>
            <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Start with the question in front of you: a search problem, a local opportunity, or a decision about where to spend your time.</p>
            <nav aria-label="Guide topics" class="mt-8">
                <ul role="list" class="flex flex-wrap gap-x-6 gap-y-4 text-base sm:text-sm">
                    @foreach (collect($articles)->groupBy('category') as $category => $group)
                        <li><a href="#{{ Illuminate\Support\Str::slug($category) }}" class="text-garden underline decoration-garden/30 underline-offset-4 hover:text-moss">{{ $category }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </section>
    @foreach (collect($articles)->groupBy('category') as $category => $group)
        <section id="{{ Illuminate\Support\Str::slug($category) }}" class="border-t border-ink/10 py-12 sm:py-16">
            <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                <h2 class="font-display text-3xl font-semibold tracking-tight text-balance">{{ $category }}</h2>
                <div class="mt-8 grid gap-x-8 gap-y-12 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($group as $article)
                        <article class="flex flex-col justify-between gap-6 border-t border-ink/10 pt-6">
                            <div>
                                <h3 class="font-display text-2xl font-semibold tracking-tight text-balance"><a href="{{ route('marketing.article', $article['slug']) }}" class="hover:text-garden">{{ $article['title'] }}</a></h3>
                                <p class="mt-4 text-pretty text-base text-ink/65">{{ $article['excerpt'] }}</p>
                            </div>
                            <div>
                                <p class="text-base text-ink/60 sm:text-sm">{{ $article['date'] }} · {{ $article['read_time'] }}</p>
                                <p class="mt-3 text-base font-medium sm:text-sm"><a href="{{ route('marketing.article', $article['slug']) }}" class="text-garden underline decoration-garden/25 underline-offset-4 hover:decoration-garden">Read article →</a></p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach
    <x-marketing.cta title="Need help applying this to your website?" text="Tell us which page, search problem or customer journey you want to improve." label="Discuss your website" />
@endsection
