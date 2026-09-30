@extends('layouts.marketing')

@section('title', $industry['eyebrow'])
@section('meta_description', $industry['description'])

@section('content')
    <section class="marketing-hero"><div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10"><p class="text-base font-medium sm:text-sm text-garden">Website & SEO management for {{ $industry['eyebrow'] }}</p><h1 class="mt-5 max-w-[20ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-6xl">{{ $industry['title'] }}</h1><p class="mt-6 max-w-[60ch] text-pretty text-lg leading-8 text-ink/65">{{ $industry['description'] }}</p><a href="{{ route('marketing.contact') }}" class="mt-8 inline-flex rounded-full bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss sm:text-sm">Talk to a specialist</a></div></section>

    <section class="border-y border-ink/10 bg-lichen py-12 sm:py-16"><div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10"><p class="text-base font-medium sm:text-sm text-garden">What makes these websites demanding</p><div class="mt-10 grid gap-10 lg:grid-cols-3">@foreach ($industry['challenges'] as [$title, $copy])<div class="border-t border-ink/15 pt-6"><h2 class="font-sans text-3xl font-medium tracking-tight text-balance">{{ $title }}</h2><p class="mt-4 text-pretty text-base leading-7 text-ink/60">{{ $copy }}</p></div>@endforeach</div></div></section>

    <section class="py-12 sm:py-16"><div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10"><div><p class="text-base font-medium sm:text-sm text-garden">What we manage</p><h2 class="mt-4 max-w-[20ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-5xl">One team across the website and search journey</h2></div><ul class="grid gap-5 sm:grid-cols-2" role="list">@foreach ($industry['management'] as $item)<li class="border-t border-ink/15 pt-5 text-base font-medium">{{ $item }}</li>@endforeach</ul></div></section>

    <section class="border-t border-ink/10 bg-lichen py-16 sm:py-20"><div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:items-center lg:px-10"><div><p class="text-base font-medium sm:text-sm text-garden">Active clients in this sector</p><h2 class="mt-4 font-sans text-3xl font-medium tracking-tight text-balance">Experience grounded in working websites</h2></div><div class="flex flex-wrap gap-3">@foreach ($industry['clients'] as $client)<span class="rounded-full bg-lichen px-4 py-2 text-base font-medium text-moss ring-1 ring-moss/10 sm:text-sm">{{ $client }}</span>@endforeach</div></div></section>

    @php
        $sectorGuides = match (request()->route('industry')) {
            'travel-and-hospitality' => ['hotels-and-holiday-accommodation', 'restaurants'],
            'events-and-experiences' => ['wedding-venues'],
            'training-and-professional-services' => ['solicitors', 'accountants', 'personal-trainers', 'online-coaches'],
            default => [],
        };
    @endphp
    @if ($sectorGuides !== [])
        <section class="border-t border-ink/10 py-16 sm:py-20">
            <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                <h2 class="font-sans text-3xl font-medium tracking-tight text-balance">A closer look at your search journey</h2>
                <p class="mt-5 max-w-[56ch] text-pretty text-base text-ink/65">These guides explain the service information and customer decisions that shape SEO in each business.</p>
                <ul role="list" class="mt-8 grid gap-5 sm:grid-cols-2">
                    @foreach ($sectorGuides as $slug)
                        <li><a class="text-garden underline decoration-garden/30 underline-offset-4 hover:text-moss" href="{{ route('marketing.industry', $slug) }}">{{ config("marketing.industries.{$slug}.title") }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
    <x-marketing.cta />
@endsection
