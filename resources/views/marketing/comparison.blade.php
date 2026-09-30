@extends('layouts.marketing')

@section('title', 'Compare Sitewell')
@section('meta_description', 'Compare Sitewell specialist website and SEO management with one-off web design, separate SEO agencies, and rented website packages.')

@section('content')
    <section class="marketing-hero">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="text-base font-medium sm:text-sm text-garden">A different working relationship</p>
            <h1 class="mt-5 max-w-[18ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-6xl">Compare your options.</h1>
            <p class="mt-6 max-w-[58ch] text-pretty text-lg leading-8 text-ink/65">Compare ongoing Sitewell support with a one-off website build or rented website package.</p>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen py-12 sm:py-16">
        <div class="mx-auto max-w-7xl overflow-x-auto px-5 sm:px-8 lg:px-10">
            <table class="w-full min-w-3xl text-left text-base sm:text-sm">
                <thead><tr class="border-b border-ink/15"><th class="py-4 pr-6 font-medium text-ink/50">What you need</th><th class="px-6 py-4 font-medium text-garden">Sitewell</th><th class="px-6 py-4 font-medium text-ink/50">One-off web build</th><th class="py-4 pl-6 font-medium text-ink/50">Rented website package</th></tr></thead>
                <tbody class="divide-y divide-ink/10">
                    @foreach ([
                        ['A website if you need one', 'Included', 'Separate project cost', 'Usually included'],
                        ['Bring an existing website', 'Yes', 'Not usually relevant', 'Often requires rebuilding'],
                        ['Ongoing website management', 'Included', 'Usually separate', 'Varies'],
                        ['Specialist SEO management', 'Available from Growth', 'Usually separate', 'Often limited'],
                        ['Lead and form oversight', 'Included', 'Usually handover only', 'Varies'],
                        ['Website portability', 'Take it with you', 'Usually yours', 'May end with the plan'],
                        ['Reason to stay', 'Ongoing specialist value', 'New projects as needed', 'Continued access to the site'],
                    ] as [$need, $sitewell, $build, $rental])
                        <tr><th class="py-5 pr-6 font-medium">{{ $need }}</th><td class="px-6 py-5 text-garden">{{ $sitewell }}</td><td class="px-6 py-5 text-ink/60">{{ $build }}</td><td class="py-5 pl-6 text-ink/60">{{ $rental }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <x-marketing.cta />
@endsection
