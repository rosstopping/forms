@extends('layouts.ppc')

@section('structured_data')
    @php
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn (array $faq): array => [
            '@type' => 'Question', 'name' => $faq[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
        ], $faqs),
    ];
    @endphp
    <script type="application/ld+json">@json($faqSchema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@endsection

@section('content')
<section class="py-9 sm:py-14 lg:py-18">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[7fr_5fr] lg:items-center lg:px-10">
        <div class="grid content-start gap-5">
            <p class="font-mono text-base text-moss sm:text-sm">{{ $landing['eyebrow'] }}</p>
            <h1 class="max-w-[22ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">{{ $landing['heading'] }}</h1>
            <p class="max-w-[40ch] text-pretty text-xl font-medium text-moss sm:text-2xl">{{ $landing['promise'] }}</p>
            <p class="max-w-[48ch] text-pretty text-base text-ink/75 sm:text-lg">{{ $landing['intro'] }}</p>
            <p class="text-base tabular-nums"><strong class="font-semibold">From £{{ $price }}/month</strong> <span class="text-ink/70">· Cancel anytime.</span></p>
            <x-marketing.ppc-actions primary />
            <p class="text-base text-ink/65 sm:text-sm">No email or website access needed for the check.</p>
        </div>
        <aside aria-label="How Sitewell turns findings into work" class="grid gap-6 rounded-xl bg-moss p-6 text-paper sm:p-8">
            <div class="flex items-center justify-between gap-4 border-b border-paper/20 pb-5">
                <p class="font-display text-2xl font-semibold tracking-tight">A website that gets attention.</p>
                <p class="shrink-0 font-mono text-base text-apricot sm:text-sm">Every week</p>
            </div>
            <ol role="list" class="grid gap-6">
                @foreach ([['01', 'Find what needs attention', 'Website checks and useful search opportunities.'], ['02', 'Do something about it', 'Content and technical improvements, carried out for you.'], ['03', 'Show you the work', 'A weekly update on changes, progress and next steps.']] as [$number, $title, $copy])
                    <li class="flex items-start gap-5">
                        <p class="shrink-0 font-mono text-base text-apricot sm:text-sm">{{ $number }}</p>
                        <div class="grid gap-1"><p class="font-medium">{{ $title }}</p><p class="text-pretty text-base text-paper/80 sm:text-sm">{{ $copy }}</p></div>
                    </li>
                @endforeach
            </ol>
            <p class="border-t border-paper/20 pt-5 text-base text-paper/80 sm:text-sm">The check is the start. The ongoing work is the service.</p>
        </aside>
    </div>
</section>

<div class="border-y border-ink/10 bg-lichen/50 py-5">
    <ul role="list" class="mx-auto flex max-w-7xl flex-wrap justify-between gap-x-8 gap-y-3 px-5 text-base font-medium text-moss sm:px-8 sm:text-sm lg:px-10">
        <li>Based in Doncaster, working across the UK</li>
        <li>Work carried out for you</li>
        <li>No setup fee or long-term contract</li>
    </ul>
</div>

<section class="py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
        <div class="grid content-start gap-5">
            <p class="font-mono text-base text-moss sm:text-sm">Sound familiar?</p>
            <h2 class="max-w-[26ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{{ $landing['problem_title'] }}</h2>
            <p class="max-w-[48ch] text-pretty text-lg text-ink/75">{{ $landing['problem'] }}</p>
        </div>
        <div class="grid content-start gap-7">
            <div class="grid gap-3 border-b border-ink/15 pb-7">
                <p class="font-mono text-base text-ink/60 sm:text-sm">An audit on its own</p>
                <p class="max-w-[40ch] font-display text-2xl tracking-tight">“Here are the problems. Over to you.”</p>
                <p class="text-pretty text-base text-ink/70">The findings are useful. But someone still needs to act on them.</p>
            </div>
            <div class="grid gap-3">
                <p class="font-mono text-base text-garden sm:text-sm">With Sitewell</p>
                <p class="max-w-[40ch] font-display text-2xl tracking-tight text-moss">“Here is what needs improving. Let’s get it done.”</p>
                <p class="text-pretty text-base text-ink/70">We agree the priorities, do the work and keep checking the website.</p>
            </div>
        </div>
    </div>
</section>

<section id="work" class="border-y border-ink/10 bg-white/40 py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:px-10">
        <div class="grid gap-4">
            <p class="font-mono text-base text-moss sm:text-sm">What we actually work on</p>
            <h2 class="max-w-[26ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Useful changes. Relevant to your business.</h2>
        </div>
        <dl class="grid gap-12 md:grid-cols-3">
            @foreach ($landing['priorities'] as [$title, $copy])
                <div class="grid content-start gap-3 border-t border-ink/15 pt-5">
                    <dt class="text-lg font-medium">{{ $title }}</dt>
                    <dd class="text-pretty text-base text-ink/75">{{ $copy }}</dd>
                </div>
            @endforeach
        </dl>
        <div class="grid gap-4 border-l-2 border-garden pl-6">
            <p class="font-mono text-base text-moss sm:text-sm">An example of the work — not a customer result</p>
            <h3 class="max-w-[40ch] font-display text-2xl font-semibold tracking-tight text-balance">{{ $landing['example_title'] }}</h3>
            <p class="max-w-[80ch] text-pretty text-base text-ink/75">{{ $landing['example'] }}</p>
        </div>
    </div>
</section>

<section class="py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
        <div class="grid content-start gap-5">
            <p class="font-mono text-base text-moss sm:text-sm">A simple way to get started</p>
            <h2 class="max-w-[26ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Check first. Decide when you have seen the findings.</h2>
            <p class="max-w-[48ch] text-pretty text-lg text-ink/75">The initial check reviews public website signals. No account or commitment is needed to see what it finds.</p>
            <p class="text-base font-medium"><a href="{{ route('marketing.free-site-audit') }}" class="inline-flex min-h-12 items-center underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Check my website <span aria-hidden="true" class="pl-3">→</span></a></p>
        </div>
        <ol role="list" class="grid divide-y divide-ink/10">
            @foreach ([['Enter your website', 'We check public signals such as titles, headings, crawl access and security basics.'], ['Review the findings together', 'If you want help, we discuss your priorities and agree how to connect your site and search data.'], ['We make the improvements', 'Content, on-page and technical SEO work, prioritised around your website and plan.'], ['Get your weekly update', 'See completed work and available ranking, search and audit changes. Then we repeat.']] as [$title, $copy])
                <li class="flex items-start gap-5 py-5 first:pt-0 last:pb-0">
                    <p class="shrink-0 font-mono text-base text-garden sm:text-sm">0{{ $loop->iteration }}</p>
                    <div class="grid gap-2"><h3 class="font-medium">{{ $title }}</h3><p class="text-pretty text-base text-ink/75">{{ $copy }}</p></div>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section class="bg-moss py-14 text-paper sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
        <div class="grid content-start gap-5">
            <p class="font-mono text-base text-apricot sm:text-sm">Clear weekly reporting</p>
            <h2 class="max-w-[26ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Know what changed. And what comes next.</h2>
            <p class="max-w-[48ch] text-pretty text-lg text-paper/85">You should not have to chase someone to find out what your monthly fee is paying for.</p>
            <p class="text-base font-medium"><a href="https://www.loom.com/share/d406218f4a2843f7a7d8abbf804f2ba6" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center underline decoration-paper/40 underline-offset-4 hover:decoration-paper">Watch the real Sitewell walkthrough <span class="pl-3" aria-hidden="true">↗</span></a></p>
        </div>
        <dl class="grid divide-y divide-paper/20">
            @foreach ([['Work completed', 'The improvements made to your pages, content and website.'], ['Search and ranking changes', 'Selected keyword positions and Search Console clicks and impressions, when connected.'], ['Website health and next priorities', 'Audit findings, resolved issues and the opportunities worth working on next.']] as [$title, $copy])
                <div class="grid gap-2 py-5 first:pt-0 last:pb-0"><dt class="font-medium">{{ $title }}</dt><dd class="text-pretty text-base text-paper/85">{{ $copy }}</dd></div>
            @endforeach
        </dl>
    </div>
</section>

<section id="pricing" class="py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
        <div class="grid content-start gap-5">
            <p class="font-mono text-base text-moss sm:text-sm">One website. Ongoing care.</p>
            <h2 class="max-w-[26ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Managed SEO, with the price in plain sight.</h2>
            <div class="flex flex-wrap items-baseline gap-2 tabular-nums"><p class="font-display text-5xl font-semibold tracking-tight">£{{ $price }}</p><p class="text-lg text-ink/70">/month</p></div>
            <p class="font-medium">No setup fee. No long-term contract. Cancel anytime.</p>
            @if ($offer)
                <p class="max-w-[56ch] text-pretty text-base text-ink/70 sm:text-sm">Current Growth offer, available until {{ \Illuminate\Support\Carbon::parse($offer['ends_at'])->format('j F Y') }}. Standard Growth price: £{{ config('memberships.plans.growth.price') }}/month.</p>
            @endif
            <x-marketing.ppc-actions />
        </div>
        <div class="grid content-start gap-6">
            <h3 class="font-display text-2xl font-semibold tracking-tight">Included in Growth</h3>
            <ul role="list" class="grid gap-3 text-base text-ink/80">
                @foreach (['Regular website improvements, carried out for you', 'Weekly website audits and clear progress reporting', 'Google ranking tracking and search performance monitoring', 'On-page optimisation and technical SEO improvements', 'Content creation and optimisation within your plan', 'Local website content where relevant to your business'] as $included)
                    <li class="border-b border-ink/10 pb-3">{{ $included }}</li>
                @endforeach
            </ul>
            <p class="text-pretty text-base text-ink/70 sm:text-sm">Growth includes up to one scheduled content improvement per week, prepared for review. Google Business Profile management is on Complete. AI visibility checks depend on your setup and supported features; they do not cover every AI provider.</p>
            <p class="text-base sm:text-sm"><a href="{{ route('marketing.pricing') }}" class="font-medium underline decoration-ink/30 underline-offset-4 hover:decoration-ink">Compare the full plans and inclusions</a></p>
        </div>
    </div>
</section>

<section id="ross" class="border-y border-ink/10 bg-lichen/40 py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
        <div class="grid content-start gap-5"><p class="font-mono text-base text-moss sm:text-sm">A real person behind the work</p><h2 class="max-w-[26ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Hi, I’m Ross.<br>A web developer based in Doncaster.</h2></div>
        <div class="grid content-start gap-5">
            <p class="max-w-[48ch] text-pretty text-lg text-ink/75">Sitewell grew out of building and managing websites, and seeing how easily they get left behind after launch.</p>
            <p class="max-w-[48ch] text-pretty text-lg text-ink/75">I wanted a better way to keep checking a website, spot useful improvements and get the work done. That is what Sitewell is here to do.</p>
            <p class="font-medium"><a href="{{ route('marketing.ppc.book') }}" class="inline-flex min-h-12 items-center underline decoration-ink/30 underline-offset-4 hover:decoration-ink">Book a call with Ross <span class="pl-3" aria-hidden="true">↗</span></a></p>
        </div>
    </div>
</section>

<section class="py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
        <div class="grid content-start gap-5"><p class="font-mono text-base text-moss sm:text-sm">Before you get started</p><h2 class="max-w-[26ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Fair questions.<br>Straight answers.</h2></div>
        <div class="divide-y divide-ink/15 border-y border-ink/15">
            @foreach ($faqs as [$question, $answer])
                <details class="group py-5"><summary class="cursor-pointer font-medium marker:text-garden">{{ $question }}</summary><p class="mt-4 max-w-[56ch] text-pretty text-base text-ink/75">{{ $answer }}</p></details>
            @endforeach
        </div>
    </div>
</section>

<section class="border-t border-ink/10 bg-lichen/50 py-14 sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-7 px-5 sm:px-8 lg:px-10">
        <h2 class="max-w-[26ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">See how your website is performing.</h2>
        <p class="max-w-[48ch] text-pretty text-lg text-ink/75">Start with a check. See what needs attention. Then decide whether you want us to take care of it.</p>
        <x-marketing.ppc-actions />
        <nav aria-label="Related SEO services" class="flex flex-wrap gap-x-6 gap-y-3 pt-4 text-base text-ink/70 sm:text-sm">
            @foreach ($landing['related'] as $related)
                <a href="{{ route('marketing.ppc.'.$related) }}" class="underline decoration-ink/20 underline-offset-4 hover:text-ink">{{ config('ppc.pages.'.$related.'.heading') }}</a>
            @endforeach
        </nav>
    </div>
</section>
@endsection
