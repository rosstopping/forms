@extends('layouts.marketing')

@section('title', $industry['eyebrow'])
@section('meta_description', $industry['description'])

@section('content')
    <section class="py-16 sm:py-24"><div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10"><p class="font-mono text-sm uppercase tracking-wide text-garden">Website & SEO management for {{ $industry['eyebrow'] }}</p><h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">{{ $industry['title'] }}</h1><p class="mt-6 max-w-[60ch] text-pretty text-lg leading-8 text-ink/65">{{ $industry['description'] }}</p><a href="{{ route('marketing.contact') }}" class="mt-8 inline-flex rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss sm:text-sm">Talk to a specialist</a></div></section>

    <section class="border-y border-ink/10 bg-lichen/40 py-20 sm:py-28"><div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10"><p class="font-mono text-sm uppercase tracking-wide text-garden">What makes these websites demanding</p><div class="mt-10 grid gap-10 lg:grid-cols-3">@foreach ($industry['challenges'] as [$title, $copy])<div class="border-t border-ink/15 pt-6"><h2 class="font-display text-3xl font-semibold tracking-tight text-balance">{{ $title }}</h2><p class="mt-4 text-pretty text-base leading-7 text-ink/60">{{ $copy }}</p></div>@endforeach</div></div></section>

    <section class="py-20 sm:py-28"><div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10"><div><p class="font-mono text-sm uppercase tracking-wide text-garden">What we manage</p><h2 class="mt-4 max-w-[20ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">One team across the website and search journey</h2></div><ul class="grid gap-5 sm:grid-cols-2" role="list">@foreach ($industry['management'] as $item)<li class="border-t border-ink/15 pt-5 text-base font-medium">{{ $item }}</li>@endforeach</ul></div></section>

    <section class="border-t border-ink/10 bg-[#fffefa] py-16 sm:py-20"><div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:items-center lg:px-10"><div><p class="font-mono text-sm uppercase tracking-wide text-garden">Active clients in this sector</p><h2 class="mt-4 font-display text-3xl font-semibold tracking-tight text-balance">Experience grounded in working websites</h2></div><div class="flex flex-wrap gap-3">@foreach ($industry['clients'] as $client)<span class="rounded-full bg-lichen px-4 py-2 text-base font-medium text-moss ring-1 ring-moss/10 sm:text-sm">{{ $client }}</span>@endforeach</div></div></section>

    <x-marketing.cta />
@endsection
