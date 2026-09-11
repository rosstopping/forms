@extends('layouts.marketing')

@section('title', 'Our work')
@section('meta_description', 'Explore websites currently supported by Sitewell across luxury travel, hospitality, events, ticketing, training, and professional services.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">Selected active clients</p>
            <h1 class="mt-5 max-w-[19ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Websites trusted to our ongoing care</h1>
            <p class="mt-6 max-w-[58ch] text-pretty text-lg leading-8 text-ink/65">Sitewell supports businesses with very different audiences, content, and commercial journeys. Here are four websites currently looked after by our team.</p>
        </div>
    </section>

    <section class="border-t border-ink/10 pb-20 sm:pb-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="grid divide-y divide-ink/10 lg:grid-cols-2 lg:divide-x">
                @foreach ($clients as $index => $client)
                    <article @class(['py-10 lg:p-10', 'lg:pl-0' => $index % 2 === 0, 'lg:pr-0' => $index % 2 === 1])>
                        <p class="font-mono text-sm uppercase tracking-wide text-garden">{{ $client['sector'] }}</p>
                        <h2 class="mt-4 font-display text-3xl font-semibold tracking-tight text-balance">{{ $client['name'] }}</h2>
                        <p class="mt-4 max-w-[54ch] text-pretty text-base leading-7 text-ink/60">{{ $client['description'] }}</p>
                        <ul class="mt-6 grid gap-2 text-base text-ink/65 sm:text-sm" role="list">
                            @foreach ($client['focus'] as $focus)<li>— {{ $focus }}</li>@endforeach
                        </ul>
                        <a href="{{ $client['url'] }}" rel="noreferrer" class="mt-7 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">Visit {{ $client['name'] }} →</a>
                    </article>
                @endforeach
            </div>
            <p class="border-t border-ink/10 pt-6 text-pretty text-base text-ink/50 sm:text-sm">These are active client websites, shown as examples of the businesses and website journeys we support. We have not attributed performance claims or testimonials to them.</p>
        </div>
    </section>

    <x-marketing.cta />
@endsection
