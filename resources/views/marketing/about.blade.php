@extends('layouts.marketing')

@section('title', 'About')
@section('meta_description', 'Learn why Sitewell brings website management, SEO, content, enquiries, and local visibility together under one specialist team.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">About Sitewell</p>
            <h1 class="mt-5 max-w-[19ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Websites deserve ownership after launch</h1>
            <p class="mt-6 max-w-[60ch] text-pretty text-lg leading-8 text-ink/65">Sitewell exists because too many business websites are handed over and quietly left behind. We give growing businesses a specialist team responsible for keeping the website healthy, visible, useful, and commercially relevant.</p>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen/40 py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div><p class="font-mono text-sm uppercase tracking-wide text-garden">Why we work this way</p><h2 class="mt-4 max-w-[19ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Good management connects the details</h2></div>
            <div class="grid gap-8 sm:grid-cols-2">
                @foreach ([['Specialists, not another login', 'The value is the judgement and work of the people looking after your website—not asking you to operate another piece of software.'], ['SEO connected to the website', 'Search analysis is most useful when the same team can improve the page, content, structure, and customer journey.'], ['Plain accountability', 'You should know who is responsible, what deserves attention, what has changed, and what happens next.'], ['Long-term value without lock-in', 'Bring your website, build one with us, and take it with you if you leave. The relationship should be earned through ongoing value.']] as [$title, $copy])
                    <div class="border-t border-ink/15 pt-5"><h3 class="font-display text-2xl font-semibold tracking-tight text-balance">{{ $title }}</h3><p class="mt-3 text-pretty text-base leading-7 text-ink/60 sm:text-sm sm:leading-6">{{ $copy }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-20 sm:py-28"><div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-3 lg:px-10">@foreach ([['UK based', 'Speak directly with a UK team that understands the businesses and websites it supports.'], ['Commercially practical', 'We prioritise work that supports trust, enquiries, useful visibility, and the services you want to grow.'], ['Clear and collaborative', 'We handle the specialist work while keeping important business decisions and priorities visible to you.']] as [$title, $copy])<div class="border-t border-ink/15 pt-6"><h2 class="font-display text-3xl font-semibold tracking-tight text-balance">{{ $title }}</h2><p class="mt-4 text-pretty text-base leading-7 text-ink/60">{{ $copy }}</p></div>@endforeach</div></section>

    <x-marketing.cta />
@endsection
