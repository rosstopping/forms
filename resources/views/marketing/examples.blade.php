@extends('layouts.marketing')

@section('title', 'Examples')
@section('meta_description', 'See practical examples of the website, SEO, enquiry, content, and local visibility work Sitewell specialists manage.')

@section('content')
    <section class="marketing-hero">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="text-base font-medium sm:text-sm text-garden">What the service looks like</p>
            <h1 class="mt-5 max-w-[18ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-6xl">The work we do.</h1>
            <p class="mt-6 max-w-[58ch] text-pretty text-lg leading-8 text-ink/65">Examples of the work we handle. These are illustrative scenarios, not client results.</p>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10"><x-marketing.product-preview /></div>
    </section>

    <section class="py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="grid gap-10 lg:grid-cols-2">
                @foreach ([
                    ['Website health', 'A key service page links to an old enquiry address.', 'We identify the affected pages, correct the journey, and confirm the working route in the next report.'],
                    ['Search visibility', 'A commercially useful service query is already close to page one.', 'Our SEO specialist assesses the ranking page, strengthens its focus and content, and continues monitoring it.'],
                    ['Lead management', 'A quote request arrives outside office hours.', 'The customer receives a professional acknowledgement and the enquiry waits safely for the right person to follow up.'],
                    ['SEO content', 'Customers search for a service in an important location that the website barely explains.', 'We research, write, optimise, and add the right landing page within the existing website structure.'],
                    ['Local presence', 'A Google Business Profile has inconsistent details and unanswered customer reviews.', 'Our specialist corrects the agreed information and prepares thoughtful responses in the business’s voice.'],
                    ['Ongoing management', 'The website needs a new service added without disrupting the rest of the site.', 'We plan where it belongs, create the supporting page and calls to action, and look after the release.'],
                ] as [$area, $situation, $response])
                    <article class="border-t border-ink/15 pt-6"><p class="text-base font-medium sm:text-sm text-garden">{{ $area }}</p><h2 class="mt-4 font-sans text-2xl font-medium tracking-tight text-balance">{{ $situation }}</h2><p class="mt-4 text-pretty text-base leading-7 text-ink/60">{{ $response }}</p></article>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
