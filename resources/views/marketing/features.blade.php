@extends('layouts.marketing')

@section('title', 'Features')
@section('meta_description', 'Explore Sitewell features for forms, website health, search performance, content planning, and GitHub-connected delivery.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">The complete care loop</p>
            <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Everything you need after a website goes live</h1>
            <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Keep the signals, conversations, and improvements around every client website in one dependable place.</p>
            <a href="{{ route('marketing.contact') }}" class="mt-8 inline-flex rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Start onboarding</a>
        </div>
    </section>

    @foreach ([
        ['01', 'Never lose the enquiry', 'Forms and submissions', 'Give every website and every form the same dependable POST route. Sitewell detects the site, discovers the form, filters spam, keeps a full record, and sends the submission where it needs to go.', ['Automatic website and form discovery', 'Email notifications with useful reply-to details', 'Webhook delivery to HubSpot, Zapier, and other tools', 'Per-form recipients and redirect settings']],
        ['02', 'Know what needs attention', 'Website health', 'Run scheduled multi-page checks that turn technical findings into a clear, client-ready view of what is healthy, what changed, and what should happen next.', ['Crawl across the important pages', 'Accessibility, metadata, links, performance, and security checks', 'Report history and client-ready email summaries', 'Tracked remediation from finding to pull request']],
        ['03', 'See why a page is visible', 'Search performance', 'Connect Google Search Console to see the searches that bring people in, the landing page ranking for each query, and the metrics needed to choose the next useful improvement.', ['Queries and their ranking landing pages', 'Clicks, impressions, CTR, and average position', 'Sortable full performance views', 'Twenty-eight-day reporting window']],
        ['04', 'Ship progress without losing control', 'Content and GitHub delivery', 'Turn opportunities into planned content and health fixes, then send changes to the connected repository through a reviewable pull request.', ['Content requests tied to real search opportunities', 'Scheduled planning with human review', 'Repository-aware implementation', 'Pull requests instead of silent publishing']],
    ] as [$number, $headline, $label, $copy, $features])
        <section class="border-t border-ink/10 py-20 sm:py-28">
            <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
                <div><p class="font-mono text-sm text-garden">{{ $number }}</p><p class="mt-5 font-mono text-sm uppercase tracking-wide text-ink/50">{{ $label }}</p><h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $headline }}</h2></div>
                <div>
                    <p class="max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">{{ $copy }}</p>
                    <dl class="mt-10 grid gap-8 sm:grid-cols-2">
                        @foreach ($features as $feature)
                            <div class="border-t border-ink/15 pt-4"><dt class="text-base font-medium sm:text-sm">{{ $feature }}</dt><dd class="mt-2 text-base text-ink/50 sm:text-sm">Included as part of the shared Sitewell workspace.</dd></div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </section>
    @endforeach

    <x-marketing.cta />
@endsection
