@extends('layouts.marketing')

@section('title', 'How Sitewell works')
@section('meta_description', 'See how Sitewell specialists take over the ongoing management of your website, SEO, enquiries, and local visibility.')

@section('content')
    <section class="overflow-hidden border-b border-ink/10 py-16 sm:py-24">
        <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-[1.1fr_.9fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">How Sitewell works</p>
                <h1 class="mt-5 max-w-[18ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Your website and SEO, managed by specialists</h1>
                <p class="mt-6 max-w-[54ch] text-pretty text-lg leading-8 text-ink/65">Bring us the website you already have or start with a new one. Our specialists take responsibility for its health, search visibility, enquiries, and ongoing improvements.</p>
                <div class="mt-8 flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                    <a href="{{ route('marketing.contact') }}" class="inline-flex min-h-12 items-center justify-center rounded-md bg-garden px-5 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Talk to a specialist</a>
                    <a href="#walkthrough" class="text-base font-medium text-ink underline decoration-ink/25 underline-offset-4 hover:decoration-ink sm:text-sm">Watch the overview ↓</a>
                </div>
            </div>

            <div class="relative mx-auto w-full max-w-lg py-7" aria-label="Your website connects to Sitewell using either the Pixel or WordPress connector">
                <div class="absolute top-1/2 left-[18%] h-px w-[64%] -translate-y-1/2 bg-ink/15" aria-hidden="true"></div>
                <div class="relative grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                    <div class="rounded-xl bg-[#fffefa] p-5 text-center ring-1 ring-ink/10 shadow-sm">
                        <p class="font-mono text-xs uppercase tracking-wide text-ink/45">Your</p>
                        <p class="mt-2 font-display text-xl font-semibold tracking-tight">Website</p>
                    </div>
                    <div class="flex size-12 items-center justify-center rounded-full bg-lichen font-mono text-xs font-medium text-garden ring-8 ring-paper">or</div>
                    <div class="rounded-xl bg-ink p-5 text-center text-paper shadow-xl">
                        <p class="font-mono text-xs uppercase tracking-wide text-paper/50">Connected to</p>
                        <p class="mt-2 font-display text-xl font-semibold tracking-tight">Sitewell</p>
                    </div>
                </div>
                <div class="relative mt-4 flex justify-center gap-2 text-xs text-ink/55">
                    <span class="rounded-full bg-paper px-3 py-1.5 ring-1 ring-ink/10">Pixel</span>
                    <span class="rounded-full bg-paper px-3 py-1.5 ring-1 ring-ink/10">WordPress</span>
                    <span class="rounded-full bg-paper px-3 py-1.5 ring-1 ring-ink/10">Fully managed</span>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="grid gap-10 lg:grid-cols-[2fr_3fr]">
                <div>
                    <p class="font-mono text-sm uppercase tracking-wide text-garden">One accountable team</p>
                    <h2 class="mt-4 max-w-[16ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">From handover to ongoing improvement</h2>
                </div>
                <dl class="grid gap-8 sm:grid-cols-3">
                    @foreach ([
                        ['01', 'Understand', 'We learn how your business wins work, which services matter, and what the website needs to do better.'],
                        ['02', 'Take responsibility', 'Our specialists set up or take over the website, reporting, enquiries, and SEO included in your plan.'],
                        ['03', 'Manage and improve', 'We monitor performance, complete routine improvements, and involve you when a business decision needs your input.'],
                    ] as [$number, $title, $copy])
                        <div class="border-t border-ink/15 pt-5">
                            <dt><span class="font-mono text-sm text-garden">{{ $number }}</span><span class="mt-4 block font-display text-2xl font-semibold tracking-tight">{{ $title }}</span></dt>
                            <dd class="mt-3 text-pretty text-base leading-7 text-ink/60 sm:text-sm sm:leading-6">{{ $copy }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </section>

    <section id="walkthrough" class="border-y border-ink/10 bg-lichen/35 py-16 sm:py-24">
        <div class="mx-auto max-w-5xl px-5 sm:px-8 lg:px-10">
            <div class="text-center">
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Video walkthrough</p>
                <h2 class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">See the whole connection in a few minutes</h2>
                <p class="mx-auto mt-5 max-w-[54ch] text-pretty text-base leading-7 text-ink/60">See how our specialists bring website health, enquiries, search performance, and ongoing improvement into one managed service.</p>
            </div>
            <div class="mt-10 overflow-hidden rounded-2xl bg-ink shadow-xl ring-1 ring-ink/15"><iframe src="https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6" title="See how Sitewell looks after your website" class="aspect-[2000/1299] w-full bg-black" loading="lazy" allow="fullscreen" allowfullscreen></iframe></div>
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="max-w-3xl">
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Three ways to connect</p>
                <h2 class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">The right connection depends on your website</h2>
                <p class="mt-5 max-w-[58ch] text-pretty text-lg text-ink/65 sm:text-base">We choose the most appropriate way to manage your website. These are the technical options our team can use behind the scenes.</p>
            </div>

            <div class="mt-14 grid gap-12 lg:grid-cols-3 lg:gap-0">
                <article class="lg:pr-10">
                    <p class="font-mono text-sm text-garden">01 / Works with almost any website</p>
                    <h3 class="mt-4 font-display text-3xl font-semibold tracking-tight text-balance">The Sitewell Pixel</h3>
                    <p class="mt-5 text-pretty text-base leading-7 text-ink/65">For many existing websites, our team can add a lightweight connection without replacing your CMS or changing your hosting.</p>
                    <dl class="mt-8 grid gap-6">
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">Best for</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Custom-built, hosted, or non-WordPress websites where adding a short code snippet is the simplest route.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">What it enables</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Page detection and approved, targeted SEO or content changes such as titles and meta descriptions.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">How it stays safe</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">It fails silently, does not collect page-view analytics, and can be disabled to remove all Pixel-delivered changes immediately.</dd></div>
                    </dl>
                </article>

                <article class="border-t border-ink/10 pt-12 lg:border-t-0 lg:border-l lg:px-10 lg:pt-0">
                    <p class="font-mono text-sm text-garden">02 / Built for WordPress</p>
                    <h3 class="mt-4 font-display text-3xl font-semibold tracking-tight text-balance">The WordPress connector</h3>
                    <p class="mt-5 text-pretty text-base leading-7 text-ink/65">For WordPress websites, our secure connector lets our specialists manage approved releases while keeping WordPress administration available.</p>
                    <dl class="mt-8 grid gap-6">
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">Best for</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Existing WordPress websites that need managed frontend updates without moving away from WordPress.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">What it enables</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Verified website releases delivered over HTTPS, with checks on every package before activation.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">How it stays safe</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Unsafe files are rejected. Deactivate the plugin to return to the original WordPress website immediately.</dd></div>
                    </dl>
                    <a href="{{ route('marketing.wordpress') }}" class="mt-8 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">Technical details for your website provider →</a>
                </article>

                <article class="border-t border-ink/10 pt-12 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-10">
                    <p class="font-mono text-sm text-garden">03 / Hands-off website care</p>
                    <h3 class="mt-4 font-display text-3xl font-semibold tracking-tight text-balance">Fully managed by Sitewell</h3>
                    <p class="mt-5 text-pretty text-base leading-7 text-ink/65">If you want one team to take full responsibility, our specialists manage the website, hosting connection, changes, releases, and ongoing care for you.</p>
                    <dl class="mt-8 grid gap-6">
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">Best for</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Non-WordPress websites where you want one team to handle day-to-day website management.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">What we manage</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Connection setup, technical changes, deployments, monitoring, and ongoing website care.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">How you stay informed</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">You receive clear updates while our specialists handle the day-to-day technical and SEO work.</dd></div>
                    </dl>
                </article>
            </div>
        </div>
    </section>

    <section class="border-t border-ink/10 bg-[#fffefa] py-16 sm:py-24">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Not sure which one?</p>
                <h2 class="mt-4 max-w-[18ch] font-display text-4xl font-semibold tracking-tight text-balance">We’ll handle the setup that fits</h2>
            </div>
            <div>
                <p class="max-w-[56ch] text-pretty text-lg leading-8 text-ink/65">You do not need to choose a connector or understand the implementation. Tell us about the website and how much responsibility you want us to take; our specialists will recommend and manage the right setup.</p>
                <a href="{{ route('marketing.contact') }}" class="mt-7 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">Tell us about your website →</a>
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
