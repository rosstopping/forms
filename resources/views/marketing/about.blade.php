@extends('layouts.marketing')

@section('title', 'About')
@section('meta_description', 'Learn why Sitewell brings website management, SEO, content, enquiries, and local visibility together under one specialist team.')

@section('content')
    <section class="marketing-hero">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="text-base font-medium sm:text-sm text-garden">About Sitewell</p>
            <h1 class="mt-5 max-w-[19ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-6xl">Your website needs looking after.</h1>
            <p class="mt-6 max-w-[60ch] text-pretty text-lg leading-8 text-ink/65">I’m Ross, a web developer based in Doncaster. I built Sitewell to keep improving business websites after launch.</p>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen py-12 sm:py-16">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div><p class="text-base font-medium sm:text-sm text-garden">Why we work this way</p><h2 class="mt-4 max-w-[19ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-5xl">What you can expect.</h2></div>
            <div class="grid gap-8 sm:grid-cols-2">
                @foreach ([['We do the work.', 'We make the changes your website needs and show you what we’ve done.'], ['SEO and website changes together.', 'We use search data to decide which pages and content to improve.'], ['Clear updates.', 'You know what changed, why it matters and what comes next.'], ['Your website stays yours.', 'Bring your existing website or have us build one. You can take it with you if you leave.']] as [$title, $copy])
                    <div class="border-t border-ink/15 pt-5"><h3 class="font-sans text-2xl font-medium tracking-tight text-balance">{{ $title }}</h3><p class="mt-3 text-pretty text-base leading-7 text-ink/60 sm:text-sm sm:leading-6">{{ $copy }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
