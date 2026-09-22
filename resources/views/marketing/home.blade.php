@extends('layouts.marketing')

@section('title', 'Managed business websites')
@section('meta_description', 'Website care and managed SEO for UK small businesses. We fix problems, update pages and keep your enquiries organised.')
@section('structured_data')
    @php
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Sitewell',
            'url' => route('marketing.home'),
        ];
    @endphp
    <script type="application/ld+json">
        @json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    </script>
@endsection

@section('content')
    <section class="bg-moss py-14 text-paper sm:py-20">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-base text-paper/80 sm:text-sm">UK website management &amp; SEO</p>
            <div class="mt-6 grid gap-10 lg:grid-cols-[7fr_4fr] lg:items-end">
                <div>
                    <h1 class="max-w-[17ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl">Your website,<br>properly looked after.</h1>
                    <p class="mt-6 max-w-[32ch] font-display text-2xl tracking-tight text-apricot sm:text-3xl">Website care. Updates. SEO.</p>
                </div>
                <div>
                    <p class="max-w-[40ch] text-pretty text-lg leading-8 text-paper/90">We fix problems, keep your pages up to date and help customers find you.</p>
                    <div class="mt-7 flex flex-wrap items-center gap-6 font-medium">
                        <a href="{{ route('marketing.how-it-works') }}" class="inline-flex min-h-12 items-center gap-5 rounded-md bg-paper px-4 py-3 text-ink hover:bg-lichen focus-visible:outline-paper">See how it works <span aria-hidden="true">→</span></a>
                        <a href="{{ route('marketing.contact') }}" class="inline-flex min-h-12 items-center underline decoration-paper/40 underline-offset-4 hover:decoration-paper">Talk to us</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen/45">
        <div class="mx-auto grid max-w-7xl divide-y divide-ink/10 px-5 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:px-8 lg:px-10">
            @foreach ([['Need a website?', 'We can build one for your business.'], ['Bring your website', 'We can look after the site you already have.'], ['Take it with you', 'Your website stays yours if you leave.']] as [$title, $copy])
                <div class="py-6 sm:px-6 sm:first:pl-0 sm:last:pr-0"><p class="font-display text-2xl font-semibold tracking-tight">{{ $title }}</p><p class="mt-2 text-base text-ink/55 sm:text-sm">{{ $copy }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="bg-paper py-14 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[4fr_7fr] lg:items-center lg:px-10">
            <div>
                <p class="font-mono text-base text-garden sm:text-sm">A look inside Sitewell</p>
                <h2 class="mt-4 max-w-[19ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">See Sitewell in action.</h2>
                <p class="mt-5 max-w-[38ch] text-pretty text-lg leading-8 text-ink/75">A quick tour of your website reports, updates and enquiries.</p>
                <p class="mt-6 font-medium"><a href="{{ route('marketing.contact') }}" class="inline-flex min-h-12 items-center gap-5 underline decoration-ink/30 underline-offset-4 hover:decoration-ink">Talk to us about your website <span aria-hidden="true">→</span></a></p>
            </div>
            <figure class="min-w-0">
                <iframe
                    src="https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6"
                    title="See how Sitewell looks after your website"
                    class="aspect-[2000/1299] w-full rounded-[min(1vw,8px)] bg-black outline-1 -outline-offset-1 outline-black/10"
                    loading="lazy"
                    allow="fullscreen"
                    allowfullscreen
                ></iframe>
                <figcaption class="mt-4 flex flex-wrap justify-between gap-2 text-base text-ink/70 sm:text-sm"><span>The Sitewell walkthrough</span><span>Website care, search &amp; enquiries</span></figcaption>
            </figure>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen/45 py-12 sm:py-16">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[4fr_7fr] lg:px-10">
            <div>
                <h2 class="max-w-[20ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">What we do each week.</h2>
                <p class="mt-5 max-w-[36ch] text-pretty text-lg leading-8 text-ink/75">Regular checks, page updates and a report of what’s been done.</p>
                <p class="mt-5 font-medium"><a href="{{ route('marketing.features') }}" class="inline-flex min-h-12 items-center underline decoration-ink/30 underline-offset-4 hover:decoration-ink">What the service covers →</a></p>
            </div>
            <ol role="list" class="divide-y divide-ink/15">
                @foreach ([
                    ['Check', 'Website health, broken links and enquiry delivery.'],
                    ['Prioritise', 'Agree which fixes and updates come first.'],
                    ['Improve', 'Fix issues and update your pages.'],
                    ['Measure', 'See completed work and track search progress on SEO plans.'],
                ] as [$heading, $copy])
                    <li class="grid grid-cols-[2rem_1fr] gap-x-4 gap-y-2 py-4 first:pt-0 last:pb-0 sm:grid-cols-[2rem_6rem_1fr]">
                        <p class="font-mono text-base text-garden sm:text-sm">0{{ $loop->iteration }}</p>
                        <h3 class="font-medium">{{ $heading }}</h3>
                        <p class="col-start-2 text-pretty text-base text-ink/75 sm:col-start-auto">{{ $copy }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="bg-apricot/35 py-12 sm:py-16">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[4fr_7fr] lg:px-10">
            <div>
                <h2 class="font-display text-3xl font-semibold tracking-tight sm:text-4xl">Hi, I’m Ross.</h2>
                <p class="mt-3 text-base text-ink/75">Founder of Sitewell. Based in the UK.</p>
                {{-- TODO: Add a genuine photograph of Ross when supplied. Confirm years of experience and exact business location before publishing those details. --}}
            </div>
            <div>
                <p class="max-w-[44ch] font-display text-2xl font-semibold tracking-tight text-pretty">Need a change? Have a question? Talk to me.</p>
                <p class="mt-4 max-w-[52ch] text-pretty text-base leading-7 text-ink/75">Tell me about your business and your website. We’ll work out what needs doing.</p>
                <div class="mt-6 flex flex-wrap items-center gap-6 font-medium">
                    <a href="{{ config('marketing.booking_url') }}" class="inline-flex min-h-12 items-center underline decoration-ink/30 underline-offset-4 hover:decoration-ink">Book a call with Ross →</a>
                    <a href="tel:+441302985828" class="inline-flex min-h-12 items-center tabular-nums underline decoration-ink/30 underline-offset-4 hover:decoration-ink">01302 985 828</a>
                </div>
            </div>
        </div>
    </section>

    {{-- TODO: Add a genuine, approved customer quote when available; no testimonial or ranking results have been invented. --}}
    <section class="py-10 sm:py-12">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 px-5 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-10">
            <h2 class="max-w-[30ch] font-display text-2xl font-semibold tracking-tight text-balance sm:text-3xl">Start with a look at your website.</h2>
            <p class="font-medium"><a href="{{ route('marketing.free-site-audit') }}" class="inline-flex min-h-12 items-center underline decoration-ink/30 underline-offset-4 hover:decoration-ink">Get your free website audit →</a></p>
        </div>
    </section>
@endsection
