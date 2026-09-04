@extends('layouts.marketing')

@section('title', 'How Sitewell works')
@section('meta_description', 'See how Sitewell connects to an existing website using the Sitewell Pixel or the secure WordPress connector.')

@section('content')
    <section class="overflow-hidden border-b border-ink/10 py-16 sm:py-24">
        <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-[1.1fr_.9fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">How Sitewell works</p>
                <h1 class="mt-5 max-w-[18ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Connect your website without rebuilding it</h1>
                <p class="mt-6 max-w-[54ch] text-pretty text-lg leading-8 text-ink/65">Sitewell works alongside the website you already have. We connect it once, keep watch over the important details, and turn what we find into clear improvements for you to review.</p>
                <div class="mt-8 flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                    <a href="{{ route('marketing.contact') }}" class="inline-flex min-h-12 items-center justify-center rounded-md bg-garden px-5 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Talk through your setup</a>
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
                    <p class="font-mono text-sm uppercase tracking-wide text-garden">One simple care loop</p>
                    <h2 class="mt-4 max-w-[16ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">From connection to confident changes</h2>
                </div>
                <dl class="grid gap-8 sm:grid-cols-3">
                    @foreach ([
                        ['01', 'Connect', 'Choose the connection that suits your website and add it once.'],
                        ['02', 'Understand', 'Sitewell audits the site and turns technical findings into practical next steps.'],
                        ['03', 'Improve', 'Review proposed changes before approved work reaches your live website.'],
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
                <p class="mx-auto mt-5 max-w-[54ch] text-pretty text-base leading-7 text-ink/60">A guided explanation of both options will live here, so you can see what gets installed and how Sitewell stays safely connected.</p>
            </div>
            <div class="mt-10 aspect-video overflow-hidden rounded-2xl bg-ink text-paper shadow-xl ring-1 ring-ink/15">
                <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                    <span class="rounded-full border border-paper/20 px-3 py-1 font-mono text-xs uppercase tracking-wide text-paper/55">Video coming soon</span>
                    <p class="mt-5 max-w-[24ch] font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">Your Sitewell walkthrough will appear here</p>
                    <p class="mt-3 max-w-[42ch] text-pretty text-base text-paper/55 sm:text-sm">This space is ready for the recorded overview.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="max-w-3xl">
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Three ways to connect</p>
                <h2 class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">The right connection depends on your website</h2>
                <p class="mt-5 max-w-[58ch] text-pretty text-lg text-ink/65 sm:text-base">Both options let Sitewell care for an existing site. The difference is how approved improvements reach it.</p>
            </div>

            <div class="mt-14 grid gap-12 lg:grid-cols-3 lg:gap-0">
                <article class="lg:pr-10">
                    <p class="font-mono text-sm text-garden">01 / Works with almost any website</p>
                    <h3 class="mt-4 font-display text-3xl font-semibold tracking-tight text-balance">The Sitewell Pixel</h3>
                    <p class="mt-5 text-pretty text-base leading-7 text-ink/65">Add one small, asynchronous script before the closing head tag on every page. It gives Sitewell a dependable connection without replacing your CMS, changing your hosting, or blocking the page as it loads.</p>
                    <dl class="mt-8 grid gap-6">
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">Best for</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Custom-built, hosted, or non-WordPress websites where adding a short code snippet is the simplest route.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">What it enables</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Page detection and approved, targeted SEO or content changes such as titles and meta descriptions.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">How it stays safe</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">It fails silently, does not collect page-view analytics, and can be disabled to remove all Pixel-delivered changes immediately.</dd></div>
                    </dl>
                </article>

                <article class="border-t border-ink/10 pt-12 lg:border-t-0 lg:border-l lg:px-10 lg:pt-0">
                    <p class="font-mono text-sm text-garden">02 / Built for WordPress</p>
                    <h3 class="mt-4 font-display text-3xl font-semibold tracking-tight text-balance">The WordPress connector</h3>
                    <p class="mt-5 text-pretty text-base leading-7 text-ink/65">Install the Sitewell plugin and pair it with a one-time code. It creates a controlled route for approved website releases while keeping WordPress administration available.</p>
                    <dl class="mt-8 grid gap-6">
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">Best for</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Existing WordPress websites that need managed frontend updates without moving away from WordPress.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">What it enables</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Verified website releases delivered over HTTPS, with checks on every package before activation.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">How it stays safe</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Unsafe files are rejected. Deactivate the plugin to return to the original WordPress website immediately.</dd></div>
                    </dl>
                    <a href="{{ route('marketing.wordpress') }}" class="mt-8 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">Read the technical plugin details →</a>
                </article>

                <article class="border-t border-ink/10 pt-12 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-10">
                    <p class="font-mono text-sm text-garden">03 / Hands-off website care</p>
                    <h3 class="mt-4 font-display text-3xl font-semibold tracking-tight text-balance">Fully managed by Sitewell</h3>
                    <p class="mt-5 text-pretty text-base leading-7 text-ink/65">For non-WordPress websites where you want us to take full responsibility, we manage the technical connection and delivery process for you. There is no connector for your team to maintain.</p>
                    <dl class="mt-8 grid gap-6">
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">Best for</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Non-WordPress websites where you want one team to handle day-to-day website management.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">What we manage</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">Connection setup, technical changes, deployments, monitoring, and ongoing website care.</dd></div>
                        <div class="border-t border-ink/15 pt-4"><dt class="font-medium">How you stay in control</dt><dd class="mt-2 text-base leading-7 text-ink/55 sm:text-sm sm:leading-6">You receive clear recommendations and can review significant changes while we handle the technical work.</dd></div>
                    </dl>
                </article>
            </div>
        </div>
    </section>

    <section class="border-t border-ink/10 bg-[#fffefa] py-16 sm:py-24">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Not sure which one?</p>
                <h2 class="mt-4 max-w-[18ch] font-display text-4xl font-semibold tracking-tight text-balance">We’ll choose the lightest connection that fits</h2>
            </div>
            <div>
                <p class="max-w-[56ch] text-pretty text-lg leading-8 text-ink/65">If your site runs on WordPress, the connector will usually be the natural fit. For a non-WordPress site managed by another provider, the Pixel is the straightforward option. If you want Sitewell to take full responsibility for the website, choose the fully managed route.</p>
                <a href="{{ route('marketing.contact') }}" class="mt-7 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">Tell us about your website →</a>
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
