@extends('layouts.marketing')

@section('title', 'Compare Sitewell')
@section('meta_description', 'Compare Sitewell specialist website and SEO management with one-off web design, separate SEO agencies, and rented website packages.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">A different working relationship</p>
            <h1 class="mt-5 max-w-[18ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">One specialist team, beyond launch day</h1>
            <p class="mt-6 max-w-[58ch] text-pretty text-lg leading-8 text-ink/65">Sitewell combines the website, SEO, enquiries, content, and ongoing care that businesses are often left to coordinate across several suppliers.</p>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-[#fffefa] py-16 sm:py-24">
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

    <section class="py-20 sm:py-28"><div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10"><div><p class="font-mono text-sm uppercase tracking-wide text-garden">The practical difference</p><h2 class="mt-4 max-w-[20ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Less coordination, clearer responsibility</h2></div><div class="grid gap-8 sm:grid-cols-2">@foreach ([['One team understands the whole picture', 'Website decisions take account of enquiries, search performance, content, and your wider business priorities.'], ['Reporting leads to action', 'Our specialists do not stop at identifying a problem; they manage the agreed improvement.'], ['Your website remains portable', 'You stay because the ongoing service is useful, not because leaving means losing the website.'], ['You still make business decisions', 'We handle specialist work and involve you when wording, positioning, or priorities need your knowledge.']] as [$title, $copy])<div class="border-t border-ink/15 pt-5"><h3 class="font-display text-2xl font-semibold tracking-tight text-balance">{{ $title }}</h3><p class="mt-3 text-pretty text-base leading-7 text-ink/60 sm:text-sm sm:leading-6">{{ $copy }}</p></div>@endforeach</div></div></section>

    <x-marketing.cta />
@endsection
