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
    <section class="overflow-hidden py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="grid gap-10 lg:grid-cols-[7fr_5fr] lg:items-end">
                <div>
                    <p class="font-mono text-sm uppercase tracking-wide text-garden">Website management for UK small businesses</p>
                    <h1 class="mt-5 max-w-[18ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl">Ongoing website management and SEO for UK small businesses</h1>
                </div>
                <div>
                    <p class="max-w-[52ch] text-pretty text-lg text-ink/65 sm:text-base">If your website helps bring in enquiries, it needs more than hosting and the occasional update. Sitewell gives you one specialist team to look after the website, improve its visibility, and keep it working for the business over time.</p>
                    <div class="mt-8 flex flex-wrap items-center gap-5">
                        <a href="{{ route('marketing.free-site-audit') }}" class="rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Start your free website audit</a>
                        <a href="{{ route('marketing.landing', 'website-management-services') }}" class="text-base font-medium text-ink underline decoration-ink/20 underline-offset-4 hover:decoration-ink sm:text-sm">Explore website management services →</a>
                    </div>
                    <p class="mt-4 max-w-[48ch] text-pretty text-sm text-ink/55">Start with a free website audit to see what needs attention first, then decide whether improving the existing site or handing over the ongoing management is the better next step.</p>
                </div>
            </div>
            <div class="mt-12 lg:mt-16"><x-marketing.product-preview /></div>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen/45">
        <div class="mx-auto grid max-w-7xl divide-y divide-ink/10 px-5 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:px-8 lg:px-10">
            @foreach ([['£0 upfront', 'A professional website is included if you need one.'], ['Bring your website', 'Connect the site your business already depends on.'], ['Take it with you', 'Your website is portable, so you are not trapped by the plan.']] as [$title, $copy])
                <div class="py-6 sm:px-6 sm:first:pl-0 sm:last:pr-0"><p class="font-display text-2xl font-semibold tracking-tight">{{ $title }}</p><p class="mt-2 text-base text-ink/55 sm:text-sm">{{ $copy }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="bg-ink py-16 text-paper sm:py-20">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-apricot">See Sitewell in action</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">A closer look at calmer website care</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-lg text-paper/65 sm:text-base">See how our specialists manage your website health, enquiries, search performance, and next growth opportunities as one service.</p>
            </div>

            <div class="mt-10 overflow-hidden rounded-[min(1vw,var(--radius-xl))] bg-paper shadow-2xl shadow-black/30 ring-1 ring-white/15 sm:mt-12">
                <iframe
                    src="https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6"
                    title="See how Sitewell looks after your website"
                    class="aspect-[2000/1299] w-full bg-black"
                    loading="lazy"
                    allow="fullscreen"
                    allowfullscreen
                ></iframe>

                <a href="{{ route('marketing.contact') }}" class="group flex flex-col gap-6 border-t border-ink/10 bg-paper p-6 text-ink hover:bg-lichen focus-visible:outline-offset-4 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div class="min-w-0">
                        <p class="font-mono text-sm uppercase tracking-wide text-garden">Your website could be next</p>
                        <p class="mt-3 max-w-[40ch] font-display text-2xl font-semibold tracking-tight text-balance sm:text-3xl">Let’s make your website work harder for your business</p>
                        <p class="mt-3 max-w-[56ch] text-pretty text-base text-ink/65 sm:text-sm">Tell us what you need and we will help you find the right next step.</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 font-medium">
                        <p class="text-base underline decoration-ink/20 underline-offset-4 group-hover:decoration-ink sm:text-sm">Talk to us</p>
                        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-garden text-paper group-hover:bg-moss" aria-hidden="true">→</span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="bg-apricot py-12 text-ink sm:py-16">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-5 sm:px-8 lg:flex-row lg:items-end lg:justify-between lg:px-10">
            <div class="min-w-0">
                <p class="font-mono text-sm uppercase tracking-wide text-ink/60">Local people, straightforward support</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">We’re a local, UK-based company</h2>
                <p class="mt-4 max-w-[56ch] text-pretty text-lg text-ink/65 sm:text-base">Have a question about your website? Call us and speak directly with our team.</p>
            </div>
            <a href="tel:+441302985828" class="group shrink-0 border-t border-ink/20 pt-5 focus-visible:outline-offset-4 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-10">
                <p class="font-mono text-sm uppercase tracking-wide text-ink/60">Call us</p>
                <p class="mt-3 font-display text-4xl font-semibold tracking-tight tabular-nums underline decoration-ink/20 underline-offset-8 group-hover:decoration-ink sm:text-5xl">01302 985 828</p>
            </a>
        </div>
    </section>

    <section class="border-y border-ink/10">
        <div class="mx-auto grid max-w-7xl px-5 sm:grid-cols-2 sm:px-8 lg:grid-cols-4 lg:px-10">
            @foreach ([['Build or bring your site', 'Start fresh or let our specialists take over the website you already use.'], ['Healthier website', 'Our team resolves health and SEO issues before they cost you business.'], ['Every lead captured', 'We make sure genuine enquiries stay visible from submission to follow-up.'], ['Managed search growth', 'Our SEO specialists turn search activity into stronger pages and useful new content.']] as [$title, $copy])
                <div class="border-t border-ink/10 py-7 first:border-t-0 sm:border-t-0 sm:px-6 sm:[&:nth-child(2n)]:border-l sm:[&:nth-child(2n)]:border-ink/10 sm:[&:nth-child(odd)]:pl-0 lg:[&:not(:first-child)]:border-l lg:[&:not(:first-child)]:border-ink/10 lg:[&:nth-child(odd)]:pl-6 lg:first:pr-6 lg:last:pr-0">
                    <p class="text-base font-medium sm:text-sm">{{ $title }}</p>
                    <p class="mt-2 text-base text-ink/55 sm:text-sm">{{ $copy }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-t border-ink/10 bg-[#fffefa] py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">What this looks like in practice</p>
            <h2 class="mt-4 max-w-[23ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Every feature has a job to do for your business</h2>
            <div class="mt-12 grid gap-5 md:grid-cols-2">
                @foreach ([
                    ['An enquiry arrives after hours', 'The customer receives an acknowledgement, the message stays safely in Sitewell, and your team sees the follow-up waiting for them.', 'forms-and-lead-management'],
                    ['A key page develops a problem', 'Our specialists identify the affected page, explain the business impact, and manage the work needed to put it right.', 'website-health-monitoring'],
                    ['A service page is close to page one', 'Our SEO specialist finds what is holding it back and strengthens the page around the opportunity.', 'seo-and-search-growth'],
                    ['Your website no longer fits', 'Build a new one with Sitewell or bring the one you already value. If you leave later, the website can come with you.', 'website-design-and-management'],
                ] as [$title, $copy, $slug])
                    <a href="{{ route('marketing.feature', $slug) }}" class="group rounded-xl bg-paper p-6 ring-1 ring-ink/10 hover:bg-lichen/35 sm:p-8"><p class="font-display text-2xl font-semibold tracking-tight">{{ $title }}</p><p class="mt-4 max-w-[52ch] text-pretty text-base leading-7 text-ink/60 sm:text-sm sm:leading-6">{{ $copy }}</p><p class="mt-6 text-sm font-medium text-garden underline decoration-garden/25 underline-offset-4 group-hover:decoration-garden">See how we manage it →</p></a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="border-t border-ink/10 py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div><p class="font-mono text-sm uppercase tracking-wide text-garden">Built around choice</p><h2 class="mt-4 max-w-[20ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Stay for the care, not because your website is trapped</h2></div>
            <div><p class="max-w-[58ch] text-pretty text-lg leading-8 text-ink/65">Many monthly website packages only work while the subscription continues. Sitewell gives you another option: bring an existing site into the service, or have us create one, with the freedom to take your website with you later.</p><p class="mt-5 max-w-[58ch] text-pretty text-base leading-7 text-ink/55">We will confirm the practical handover details before you get started. Your domain and connected business accounts remain yours.</p><a href="{{ route('marketing.feature', 'website-design-and-management') }}" class="mt-7 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">Read about website ownership and care →</a></div>
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">One dependable rhythm</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Capture, care for, improve</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/60 sm:text-base">A website that keeps working after launch captures every enquiry, stays healthy, and keeps improving in search over time.</p>
            </div>
            <dl class="mt-14 grid gap-10 lg:grid-cols-3">
                @foreach ([['01', 'Capture every enquiry', 'We manage your forms, customer acknowledgements, spam protection, and lead inbox so every potential customer gets the attention they deserve.'], ['02', 'Keep the website healthy', 'Our specialists monitor website health and SEO, resolve routine issues, and keep you informed with a clear weekly report.'], ['03', 'Grow search visibility', 'We interpret search performance, identify commercially useful opportunities, and improve the pages most likely to make a difference.']] as [$number, $title, $copy])
                    <div class="border-t border-ink/15 pt-6">
                        <dt><p class="font-mono text-base text-garden sm:text-sm">{{ $number }}</p><p class="mt-6 font-display text-2xl font-semibold tracking-tight">{{ $title }}</p></dt>
                        <dd class="mt-4 max-w-[56ch] text-pretty text-base text-ink/60 sm:text-sm">{{ $copy }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="bg-lichen/55 py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:items-center lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Everything in one place</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">A calm control room for your business website</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/60 sm:text-base">You do not need to become a website or SEO expert. Our specialists manage the checks, enquiries, reporting, and improvement work for you.</p>
                <a href="{{ route('marketing.features') }}" class="mt-7 inline-flex text-base font-medium underline decoration-ink/20 underline-offset-4 hover:decoration-ink sm:text-sm">Explore every feature →</a>
            </div>
            <div class="grid gap-4">
                @foreach ([['Forms & CRM', 'Managed submissions, spam review, customer replies, and follow-ups.'], ['Website & SEO care', 'Specialist monitoring, clear reporting, and practical fixes.'], ['Search growth', 'Commercial keyword opportunities and page improvements managed for you.'], ['Local presence', 'Specialist Google Business Profile management on the Complete plan.']] as [$title, $copy])
                    <div class="grid grid-cols-[7rem_1fr] gap-4 border-t border-ink/15 pt-4"><p class="font-mono text-base text-garden sm:text-sm">{{ $title }}</p><p class="text-pretty text-base text-ink/65 sm:text-sm">{{ $copy }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
