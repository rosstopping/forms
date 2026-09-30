@extends('layouts.marketing')

@section('title', 'Journal')
@section('meta_description', 'Practical Sitewell guides to local SEO, Google visibility, website problems and better enquiries for UK small businesses.')

@section('content')
    <section class="marketing-hero">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="text-base font-medium sm:text-sm text-garden">The Sitewell journal</p>
            <h1 class="mt-5 max-w-[20ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-6xl">Practical website and SEO guides.</h1>
            <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Advice on search visibility, website problems and getting more enquiries.</p>
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
                <h2 class="font-sans text-3xl font-medium tracking-tight text-balance">{{ $category }}</h2>
                <div class="mt-8 grid gap-x-8 gap-y-12 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($group as $article)
                        <article class="flex flex-col justify-between gap-6 border-t border-ink/10 pt-6">
                            <div>
                                <h3 class="font-sans text-2xl font-medium tracking-tight text-balance"><a href="{{ route('marketing.article', $article['slug']) }}" class="hover:text-garden">{{ $article['title'] }}</a></h3>
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
    <x-marketing.cta />
@endsection
