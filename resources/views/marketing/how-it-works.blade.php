@extends('layouts.marketing')

@section('title', 'How Sitewell works')
@section('meta_description', 'Start with a search audit, agree the priorities with Ross and see the website and SEO work completed. Learn how Sitewell connects to your existing website.')

@section('content')
    <section class="marketing-hero">
        <div class="mx-auto grid max-w-7xl gap-6 px-5 sm:px-8 lg:px-10">
            <h1 class="max-w-[24ch] text-4xl font-medium tracking-tight text-balance sm:text-5xl lg:text-6xl">How Sitewell looks after your website</h1>
            <p class="max-w-[56ch] text-lg text-ink/70">Start with a search audit, then agree the priorities with me. I’m Ross, the developer behind Sitewell. I’ll arrange the connection, carry out the agreed website and SEO work and keep you updated.</p>
            <div class="flex flex-wrap items-center gap-5">
                <a href="{{ route('marketing.free-site-audit') }}" class="inline-flex min-h-12 items-center justify-center rounded-full bg-garden px-5 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden">Get your free search audit</a>
                <a href="#walkthrough" class="inline-flex min-h-12 items-center text-base font-medium underline underline-offset-4">Watch the walkthrough</a>
            </div>
        </div>
    </section>

    <section class="py-12 sm:py-16" aria-labelledby="process-heading">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <h2 id="process-heading" class="max-w-[32ch] text-3xl font-medium tracking-tight sm:text-4xl">From the first review to ongoing improvements</h2>
            <ol role="list" class="mt-8 grid gap-8 md:grid-cols-3">
                @foreach ([
                    ['Agree the work', 'We’ll discuss your services, website and available search data, then agree the priorities, scope and cost before making changes.'],
                    ['Connect your website', 'I’ll choose the connection that suits your website and arrange access with you or your existing provider. You don’t need to set it up yourself.'],
                    ['Improve and review', 'I’ll work on the agreed content, technical fixes and SEO priorities. Your updates show completed work, available search measures and what comes next.'],
                ] as [$title, $copy])
                    <li class="grid content-start gap-3 border-t border-ink/10 pt-6"><h3 class="text-xl font-medium">{{ $title }}</h3><p class="text-base text-ink/70">{{ $copy }}</p></li>
                @endforeach
            </ol>
        </div>
    </section>

    <section id="walkthrough" class="bg-lichen py-12 sm:py-16" aria-labelledby="walkthrough-heading">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <h2 id="walkthrough-heading" class="text-3xl font-medium tracking-tight sm:text-4xl">See the work and reports in Sitewell</h2>
            <p class="mt-5 max-w-[64ch] text-lg text-ink/70">The walkthrough shows website changes, reports and search performance in one place. Search figures depend on the data connected to your website.</p>
            <div class="mt-8 overflow-hidden rounded-xl bg-white ring-1 ring-ink/10"><iframe src="https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6?hideEmbedTopBar=true&amp;hide_owner=true&amp;hide_title=true&amp;hide_share=true" title="See how Sitewell looks after your website" class="aspect-[2000/1299] w-full bg-black" loading="lazy" allow="fullscreen" allowfullscreen></iframe></div>
        </div>
    </section>

    <section class="py-12 sm:py-16" aria-labelledby="connections-heading">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <h2 id="connections-heading" class="text-3xl font-medium tracking-tight sm:text-4xl">Connecting your existing website</h2>
            <p class="mt-5 max-w-[64ch] text-lg text-ink/70">You can often keep your current website and hosting. The right connection depends on how it was built and the changes we agree to make.</p>
            <div class="mt-8 grid gap-8 lg:grid-cols-3">
                <article class="grid content-start gap-4 border-t border-ink/10 pt-6">
                    <h3 class="text-2xl font-medium tracking-tight">The Sitewell Pixel</h3>
                    <p class="text-base text-ink/70">For many custom-built and hosted websites, I can add a short code snippet to detect pages and apply approved changes such as titles and meta descriptions.</p>
                    <p class="text-base text-ink/70">It doesn’t replace your content editor or require a hosting move.</p>
                    <details class="border-t border-ink/10 pt-4"><summary class="cursor-pointer font-medium marker:text-garden">Pixel access and removal</summary><p class="mt-4 text-base text-ink/70">The Pixel doesn’t collect page-view analytics. It fails silently if it can’t load. Disabling it removes the changes it delivers.</p></details>
                </article>
                <article class="grid content-start gap-4 border-t border-ink/10 pt-6">
                    <h3 class="text-2xl font-medium tracking-tight">The WordPress connector</h3>
                    <p class="text-base text-ink/70">For WordPress sites, the connector delivers approved website releases while keeping WordPress administration available.</p>
                    <p class="text-base text-ink/70">Each release is checked before activation. Your website can stay on WordPress.</p>
                    <details class="border-t border-ink/10 pt-4"><summary class="cursor-pointer font-medium marker:text-garden">WordPress access and removal</summary><p class="mt-4 text-base text-ink/70">Releases are delivered over HTTPS and unsafe files are rejected. Deactivate the plugin to return to the original WordPress website immediately.</p></details>
                    <a href="{{ route('marketing.wordpress') }}" class="justify-self-start text-base text-garden underline underline-offset-4">Technical details for your provider</a>
                </article>
                <article class="grid content-start gap-4 border-t border-ink/10 pt-6">
                    <h3 class="text-2xl font-medium tracking-tight">Fully managed by Sitewell</h3>
                    <p class="text-base text-ink/70">For non-WordPress websites needing day-to-day management, I can handle connection setup, technical changes, deployments, monitoring and ongoing website care.</p>
                    <p class="text-base text-ink/70">We’ll agree hosting, access and responsibilities before work begins. You receive updates on the work completed.</p>
                </article>
            </div>
        </div>
    </section>

    <x-marketing.cta title="Discuss your website with Ross" text="Send me your website and tell me what you need help with. We’ll discuss the work and the best way to connect." label="Talk to Ross" route="marketing.contact" />
@endsection
