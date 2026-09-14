@extends('layouts.marketing')

@section('title', 'Managed business websites')
@section('meta_description', 'Ongoing website management and SEO for UK small businesses, with dependable website care, lead handling, and a free website audit to show what needs attention.')
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
            <div class="mt-6 grid gap-12 lg:grid-cols-[7fr_4fr] lg:items-end">
                <div>
                    <h1 class="max-w-[17ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl">Your website,<br>properly looked after.</h1>
                    <p class="mt-6 max-w-[32ch] font-display text-2xl tracking-tight text-apricot sm:text-3xl">We do the work. You get on with business.</p>
                </div>
                <div>
                    <p class="max-w-[40ch] text-pretty text-lg leading-8 text-paper/90">We manage your website, SEO and enquiries every week — fixing problems, improving pages and making sure your website keeps doing its job.</p>
                    <div class="mt-7 flex flex-wrap items-center gap-6 font-medium">
                        <a href="{{ route('marketing.how-it-works') }}" class="inline-flex min-h-12 items-center gap-5 rounded-md bg-paper px-4 py-3 text-ink hover:bg-lichen focus-visible:outline-paper">See how it works <span aria-hidden="true">→</span></a>
                        <a href="{{ route('marketing.contact') }}" class="inline-flex min-h-12 items-center underline decoration-paper/40 underline-offset-4 hover:decoration-paper">Talk to us</a>
                    </div>
                </div>
            </div>
            <div class="mt-10 flex flex-col gap-3 border-t border-paper/25 pt-6 sm:mt-12 sm:flex-row sm:items-baseline sm:justify-between">
                <p class="font-medium">Website included. £0 upfront.</p>
                <p class="max-w-[52ch] text-pretty text-base text-paper/80 sm:text-right sm:text-sm">Care from £{{ config('memberships.plans.essential.price') }}/month, excl. VAT. Managed SEO on Growth and Complete.</p>
            </div>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen/45">
        <div class="mx-auto grid max-w-7xl divide-y divide-ink/10 px-5 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:px-8 lg:px-10">
            @foreach ([['£0 upfront', 'A professional website is included if you need one.'], ['Bring your website', 'Connect the site your business already depends on.'], ['Take it with you', 'Your website is portable, so you are not trapped by the plan.']] as [$title, $copy])
                <div class="py-6 sm:px-6 sm:first:pl-0 sm:last:pr-0"><p class="font-display text-2xl font-semibold tracking-tight">{{ $title }}</p><p class="mt-2 text-base text-ink/55 sm:text-sm">{{ $copy }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="bg-paper py-14 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[4fr_7fr] lg:items-center lg:px-10">
            <div>
                <p class="font-mono text-base text-garden sm:text-sm">A look inside Sitewell</p>
                <h2 class="mt-4 max-w-[19ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">See the work.<br>Not just a report.</h2>
                <p class="mt-5 max-w-[38ch] text-pretty text-lg leading-8 text-ink/75">See what we’ve found, what we’re working on and what’s changed. This walkthrough shows how we keep your website care, SEO and enquiries in view.</p>
                <p class="mt-4 max-w-[38ch] text-pretty text-base leading-7 text-ink/75">We manage the work. You can see what’s happening without learning another system.</p>
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
                <h2 class="max-w-[20ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">We don’t build your website and disappear.</h2>
                <p class="mt-5 max-w-[36ch] text-pretty text-lg leading-8 text-ink/75">We keep checking, fixing and improving it. Here’s how the work moves forward each week.</p>
                <p class="mt-5 font-medium"><a href="{{ route('marketing.features') }}" class="inline-flex min-h-12 items-center underline decoration-ink/30 underline-offset-4 hover:decoration-ink">What the service covers →</a></p>
            </div>
            <ol role="list" class="divide-y divide-ink/15">
                @foreach ([
                    ['Check', 'Website health, broken links and enquiry delivery.'],
                    ['Prioritise', 'The problems that matter, and the pages worth improving.'],
                    ['Improve', 'Practical fixes and agreed updates, handled for you.'],
                    ['Measure', 'A clear weekly report. Search progress tracked on managed SEO plans.'],
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

    @if (config('marketing.clients'))
        <section class="py-10 sm:py-12">
            <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                <h2 class="text-base font-medium text-ink/75">Some of the businesses we look after</h2>
                <ul role="list" class="mt-5 grid gap-x-10 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (config('marketing.clients') as $client)
                        <li>
                            <p class="font-display text-xl font-semibold tracking-tight"><a href="{{ $client['url'] }}" class="inline-flex min-h-12 items-center hover:text-garden">{{ $client['name'] }}</a></p>
                            <p class="text-base text-ink/70 sm:text-sm">{{ $client['sector'] }}</p>
                        </li>
                    @endforeach
                </ul>
                {{-- TODO: Expand with an approved client screenshot and verified results when available. Do not infer performance claims from the client list. --}}
            </div>
        </section>
    @endif

    <section class="border-t border-ink/10 py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="grid gap-10 lg:grid-cols-[4fr_7fr] lg:items-end">
                <div>
                    <h2 class="max-w-[20ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">The website.<br>The care.<br>One monthly price.</h2>
                </div>
                <p class="max-w-[52ch] text-pretty text-lg leading-8 text-ink/75">A website is included if you need one. £0 upfront, with the build scope agreed before we start. Already have a site? We’ll review it and agree the right setup, including hosting.</p>
            </div>
            <div class="mt-8 border-t border-ink/20">
                @foreach (\App\Support\MembershipPlan::all() as $tier => $plan)
                    @php
                        $offer = $tier === 'growth' ? \App\Support\MembershipPlan::activeGrowthOffer() : null;
                        $summary = match ($tier) {
                            'essential' => 'Website care, practical fixes and managed enquiries.',
                            'growth' => 'Essential, plus managed SEO and up to one content improvement a week.',
                            'complete' => 'Growth, plus up to three content improvements a week and Google Business Profile management.',
                        };
                    @endphp
                    <div class="grid gap-4 border-b border-ink/20 py-6 sm:grid-cols-[1fr_2fr_1fr] sm:gap-10">
                        <h3 class="font-display text-2xl font-semibold tracking-tight">{{ $plan['name'] }}</h3>
                        <div>
                            <p class="max-w-[44ch] text-pretty text-base leading-7 text-ink/75">{{ $summary }}</p>
                            <p class="mt-2 font-medium"><a href="{{ route('marketing.contact', ['plan' => $tier]) }}" class="inline-flex min-h-12 items-center text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Discuss {{ $plan['name'] }} →</a></p>
                        </div>
                        <div class="sm:text-right">
                            <p class="font-display text-3xl font-semibold tracking-tight tabular-nums">£{{ $offer['price'] ?? $plan['price'] }}</p>
                            <p class="mt-1 text-base text-ink/75 sm:text-sm">/month, excl. VAT</p>
                            @if ($offer)
                                <p class="mt-2 text-base text-garden sm:text-sm">{{ $offer['discount_percentage'] }}% off £{{ $plan['price'] }} until {{ \Illuminate\Support\Carbon::parse($offer['ends_at'])->format('j F Y') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 grid gap-6 text-base leading-7 text-ink/75 lg:grid-cols-2 lg:gap-10">
                <p class="max-w-[56ch]">Your domain and connected accounts remain yours. Take your website with you if you leave; we confirm handover details before you start.</p>
                <p class="max-w-[56ch]">One business website per plan. Cancel to stop future renewal, normally at the end of your paid billing period.</p>
            </div>
            <p class="mt-3 flex flex-wrap gap-6 font-medium"><a href="{{ route('marketing.pricing') }}" class="inline-flex min-h-12 items-center underline decoration-ink/30 underline-offset-4 hover:decoration-ink">Compare everything included →</a><a href="{{ route('marketing.terms') }}" class="inline-flex min-h-12 items-center underline decoration-ink/30 underline-offset-4 hover:decoration-ink">Read the terms</a></p>
        </div>
    </section>

    <section class="bg-apricot/35 py-12 sm:py-16">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[4fr_7fr] lg:px-10">
            <div>
                <h2 class="font-display text-3xl font-semibold tracking-tight sm:text-4xl">Hi, I’m Ross.</h2>
                <p class="mt-3 text-base text-ink/75">The person behind Sitewell. UK based.</p>
                {{-- TODO: Add a genuine photograph of Ross when supplied. Confirm years of experience and exact business location before publishing those details. --}}
            </div>
            <div>
                <p class="max-w-[44ch] font-display text-2xl font-semibold tracking-tight text-pretty">I started Sitewell because launching a website should be the start of looking after it.</p>
                <p class="mt-4 max-w-[52ch] text-pretty text-base leading-7 text-ink/75">The software helps us keep track. The service is about doing the work, keeping you informed and being there when you need to talk.</p>
                <div class="mt-6 flex flex-wrap items-center gap-6 font-medium">
                    <a href="{{ config('marketing.booking_url') }}" class="inline-flex min-h-12 items-center rounded-md border border-ink/30 px-4 py-3 hover:bg-paper/50">Book a call with Ross →</a>
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
