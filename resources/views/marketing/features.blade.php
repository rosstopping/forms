@extends('layouts.marketing')

@section('title', 'Features')
@section('meta_description', 'Explore Sitewell features for website health, forms, CRM, search visibility, content, and Google Business Profile management.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">The complete care loop</p>
            <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Everything you need after a website goes live</h1>
            <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Keep your website, customer conversations, and growth work in one dependable place.</p>
            <a href="{{ route('marketing.contact') }}" class="mt-8 inline-flex rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Get started</a>
        </div>
    </section>

    @foreach ([
        ['01', 'Never lose an enquiry', 'Forms and CRM', 'Give every form a dependable route into one clear lead inbox. Sitewell filters spam, sends a polite acknowledgement, and helps you keep every follow-up moving.', ['Automatic website and form discovery', 'Email notifications and automatic replies', 'Lead status, notes, and follow-up reminders', 'Spam review and delivery history']],
        ['02', 'Know what needs attention', 'Website health', 'Run scheduled checks that turn technical findings into clear, practical next steps for your business.', ['Crawl across the important pages', 'Accessibility, metadata, links, performance, and security checks', 'Weekly email reports in plain English', 'Tracked remediation and reviewable fixes']],
        ['03', 'See why a page is visible', 'Search performance', 'Connect Google Search Console to see the searches that bring people in, the landing page ranking for each query, and the metrics needed to choose the next useful improvement.', ['Queries and their ranking landing pages', 'Clicks, impressions, CTR, and average position', 'Sortable full performance views', 'Twenty-eight-day reporting window']],
        ['04', 'Show up well locally', 'Google Business Profile', 'On the complete plan, keep your Google Business Profile healthy with recommendations, generated post drafts, and customer review replies that stay under your control.', ['Profile health checks and recommendations', 'Generated post drafts for approval', 'Review replies in approval mode', 'Connected website and local presence view']],
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
