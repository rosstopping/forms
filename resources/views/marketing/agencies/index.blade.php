@extends('layouts.marketing')
@section('title', 'SEO for agencies, without building an SEO team | Sitewell')
@section('concise_title', '1')
@section('meta_description', 'Offer ongoing SEO to your website clients with Sitewell behind your agency. Explore monitoring, audits, reporting and reviewed improvements. Join the agency beta.')
@section('structured_data')
    @include('marketing.agencies.schema')
@endsection
@section('content')
    <section class="py-12 sm:py-20">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <nav aria-label="Breadcrumb" class="flex gap-2 text-base text-ink/60 sm:text-sm"><a href="{{ route('marketing.home') }}" class="hover:text-garden">Home</a><span aria-hidden="true">/</span><span aria-current="page">For agencies</span></nav>
            <div class="mt-10 grid items-center gap-12 lg:grid-cols-[3fr_2fr]">
                <div>
                    <p class="font-mono text-base uppercase tracking-wide text-garden sm:text-sm">Your agency. More to offer.</p>
                    <h1 class="mt-5 max-w-[20ch] text-balance font-display text-5xl font-semibold tracking-tight sm:text-6xl lg:text-7xl">Offer SEO to every client.<br><span class="text-garden">Without building an SEO team.</span></h1>
                    <p class="mt-7 max-w-[48ch] text-pretty text-lg/8 text-ink/70">Keep the client relationship. Let Sitewell support the ongoing monitoring, search insights, reporting and improvement workflows behind your agency’s SEO service.</p>
                    <div class="mt-8 flex flex-wrap items-center gap-6"><a href="#join-beta" class="rounded-md bg-garden px-5 py-3 text-base font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden">Join the agency beta <span aria-hidden="true">↗</span></a><a href="#how-it-works" class="text-base font-medium underline decoration-ink/25 underline-offset-4 hover:decoration-garden">See how it works</a></div>
                    <p class="mt-5 max-w-[56ch] text-pretty text-base text-ink/55 sm:text-sm">For web designers, development agencies and the teams looking after client websites.</p>
                </div>
                <figure class="rounded-xl bg-lichen/60 p-6 sm:p-8">
                    <figcaption class="font-mono text-base uppercase tracking-wide text-moss sm:text-sm">The agency model</figcaption>
                    <ol role="list" class="mt-6 grid gap-3">
                        <li class="rounded-lg bg-white p-5 ring-1 ring-ink/10"><p class="text-lg font-semibold">Your agency</p><p class="mt-2 text-base text-ink/65">Your clients, your service, your priorities.</p></li>
                        <li aria-hidden="true" class="text-center text-2xl text-moss">↓</li>
                        <li class="rounded-lg bg-garden p-5 text-white"><p class="font-display text-3xl font-semibold tracking-tight">Sitewell</p><p class="mt-2 text-base text-white/85">Monitor · Understand · Improve · Report</p></li>
                        <li aria-hidden="true" class="text-center text-2xl text-moss">↓</li>
                        <li class="rounded-lg bg-white p-5 ring-1 ring-ink/10"><p class="text-lg font-semibold">Your client websites</p><p class="mt-2 text-base text-ink/65">Ongoing attention, beyond launch day.</p></li>
                    </ol>
                    <p class="mt-5 text-pretty text-base/6 text-moss sm:text-sm/6">The proposed agency service, built on Sitewell’s existing website tools.</p>
                </figure>
            </div>
        </div>
    </section>
    <section id="how-it-works" class="scroll-mt-8 border-y border-ink/10 py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="grid gap-12 lg:grid-cols-2"><h2 class="max-w-[24ch] text-balance font-display text-4xl font-semibold tracking-tight">The website is live.<br>The opportunity is still there.</h2><p class="max-w-[56ch] text-pretty text-base/8 text-ink/70">Clients keep asking about Google, content and getting more from their website. But offering SEO can mean more tools, more analysis and a new delivery problem. Sitewell brings the evidence and workflows together so your agency can build a service around them.</p></div>
            <ol role="list" class="mt-12 grid gap-12 md:grid-cols-3">
                @foreach ([['01', 'Agree the client’s priorities', 'Choose suitable websites, confirm access and define what your agency will monitor and deliver.'], ['02', 'Keep finding the useful work', 'Use regular audits, keyword monitoring and connected search data to identify issues and opportunities.'], ['03', 'Review, improve and report', 'Approve the right work, use supported delivery routes and explain what changed in a clear client update.']] as [$number, $heading, $copy])<li class="border-t border-ink/20 pt-6"><p class="font-mono text-base text-garden sm:text-sm">{{ $number }}</p><h3 class="mt-4 text-xl font-semibold">{{ $heading }}</h3><p class="mt-3 text-pretty text-base/7 text-ink/70">{{ $copy }}</p></li>@endforeach
            </ol>
        </div>
    </section>
    <section class="py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
            <div><p class="font-mono text-base uppercase tracking-wide text-garden sm:text-sm">One agency, many websites</p><h2 class="mt-4 max-w-[24ch] text-balance font-display text-4xl font-semibold tracking-tight">Know where your attention is needed.</h2><p class="mt-6 max-w-[48ch] text-pretty text-lg/8 text-ink/70">A client with a new issue. A useful content opportunity. A change waiting for approval. That is the picture an agency needs across its portfolio.</p><p class="mt-5 max-w-[56ch] text-pretty text-base/7 text-ink/65">Sitewell already supports website-specific workspaces and permissions. We’re exploring a dedicated agency overview to bring those priorities together.</p><a href="{{ route('marketing.agencies.show', 'manage-multiple-websites') }}" class="mt-6 inline-block text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Explore the portfolio direction <span aria-hidden="true">→</span></a></div>
            @include('marketing.agencies.preview', ['preview' => 'portfolio'])
        </div>
    </section>
    <section id="available-now" class="scroll-mt-8 border-y border-ink/10 bg-white/50 py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-base uppercase tracking-wide text-garden sm:text-sm">The tools behind your service</p><h2 class="mt-4 max-w-[30ch] text-balance font-display text-4xl font-semibold tracking-tight">More than another reporting tool.</h2><p class="mt-5 max-w-[65ch] text-pretty text-base/8 text-ink/70">These capabilities exist in Sitewell today. Availability depends on the website’s plan, configured connections and delivery compatibility. We’ll agree a suitable setup during the beta conversation.</p>
            <div class="mt-10 grid gap-12 md:grid-cols-2 lg:grid-cols-3">
                @foreach ([['seo-audits', 'Website audits', 'Keep checking website health and prioritise meaningful issues after launch.'], ['rank-tracking', 'Keyword monitoring', 'Follow agreed search terms and spot movement worth investigating.'], ['google-search-console', 'Search Console insights', 'Understand the queries and pages behind visibility, clicks and opportunities.'], ['google-business-profile', 'Local business care', 'Bring connected profile performance, reviews and updates into the conversation.'], ['automate-seo', 'Reviewed improvements', 'Move from a useful finding to content or page work through supported workflows.'], ['seo-reporting', 'Weekly summaries', 'Bring available performance, issues, opportunities and recorded work into a clear update.']] as [$slug, $heading, $copy])<div class="border-t border-ink/15 pt-5"><h3 class="text-xl font-semibold"><a href="{{ route('marketing.agencies.show', $slug) }}" class="hover:text-garden">{{ $heading }} <span aria-hidden="true" class="text-garden">↗</span></a></h3><p class="mt-3 text-pretty text-base/7 text-ink/70">{{ $copy }}</p></div>@endforeach
            </div>
            <div class="mt-12 grid gap-6 border-t border-ink/15 pt-8 md:grid-cols-[1fr_2fr]"><h3 class="text-xl font-semibold">Agency beta / planned direction</h3><p class="max-w-[68ch] text-pretty text-base/8 text-ink/70">Your own branding, agency-branded reports, a branded client dashboard and potentially custom domains. These are not available features today. We’re using the beta to understand what agencies need before promising a finished white-label programme.</p></div>
        </div>
    </section>
    <section class="py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
            <div><p class="font-mono text-base uppercase tracking-wide text-garden sm:text-sm">A service worth coming back for</p><h2 class="mt-4 max-w-[24ch] text-balance font-display text-4xl font-semibold tracking-tight">Your next opportunity may already be a client.</h2><p class="mt-6 max-w-[48ch] text-pretty text-lg/8 text-ink/70">If you look after 30 websites, you already have 30 relationships. Some of those clients may need a clearer plan for being found, staying useful and improving their website.</p><p class="mt-5 max-w-[56ch] text-pretty text-base/7 text-ink/65">Start with a small pilot. Price the review time, client communication and implementation as well as the tools. Build around real demand and a scope you can deliver consistently.</p><a href="{{ route('marketing.agencies.show', 'add-recurring-revenue') }}" class="mt-6 inline-block text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Think through the agency economics <span aria-hidden="true">→</span></a></div>
            <div class="border-l-2 border-garden pl-8"><p class="max-w-[25ch] text-balance font-display text-3xl font-medium tracking-tight sm:text-4xl">Your agency remains the name your clients trust.</p><p class="mt-6 max-w-[56ch] text-pretty text-base/8 text-ink/70">Sitewell provides the technology and automation underneath the service. Your team brings the client knowledge, decisions and ongoing relationship.</p><a href="{{ route('marketing.agencies.show', 'white-label-seo') }}" class="mt-6 inline-block text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Explore the white-label model <span aria-hidden="true">→</span></a></div>
        </div>
    </section>
    <section class="border-t border-ink/10 py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <h2 class="max-w-[30ch] text-balance font-display text-4xl font-semibold tracking-tight">Find the right starting point for your agency.</h2><p class="mt-5 max-w-[65ch] text-pretty text-base/8 text-ink/70">For freelance designers, development teams, WordPress specialists, digital agencies, design studios, maintenance providers and hosting companies. Start with the part of your service you want to improve.</p>
            <div class="mt-10 grid gap-12 md:grid-cols-2">
                @foreach (collect($pages)->groupBy('group', preserveKeys: true) as $group => $groupPages)<nav aria-label="{{ $group }}" class="border-t border-ink/15 pt-5"><h3 class="text-xl font-semibold">{{ $group }}</h3><ul role="list" class="mt-5 grid gap-3">@foreach ($groupPages as $slug => $resource)<li><a href="{{ route('marketing.agencies.show', $slug) }}" class="text-base text-ink/75 underline decoration-ink/20 underline-offset-4 hover:text-garden">{{ $resource['label'] }} <span aria-hidden="true">→</span></a></li>@endforeach</ul></nav>@endforeach
            </div>
        </div>
    </section>
    <section class="border-t border-ink/10 py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
            <h2 class="max-w-[24ch] text-balance font-display text-4xl font-semibold tracking-tight">Before you join.</h2>
            <div class="divide-y divide-ink/15 border-y border-ink/15">
                @foreach ([['Is the agency offering ready to buy off the shelf?', 'We are validating the agency offering through a beta. The underlying Sitewell tools exist, while agency packaging, pricing and white-label features are being shaped with participating agencies.'], ['Do we keep the client relationship?', 'That is the proposition. Your agency owns the relationship, scope and client conversation. We will agree access, communication and delivery responsibilities before a pilot.'], ['Can we use our own logo and domain?', 'Not yet. Agency branding, branded reports, client dashboards and custom domains are planned possibilities, not live self-service options.'], ['Does Sitewell automate all SEO work?', 'No. It automates repeated checks and supports analysis and improvement workflows. Strategy, accuracy, approvals and client priorities still need people.'], ['Can you work with any website?', 'Monitoring and implementation have different requirements. We will assess representative websites, access and supported delivery routes before agreeing your beta scope.'], ['Do you guarantee rankings or recurring income?', 'No. Search performance and commercial results depend on many factors. Start with a clear scope and measure the value and cost of delivery.']] as [$question, $answer])<details class="py-5"><summary class="cursor-pointer text-base font-medium focus-visible:outline-offset-4">{{ $question }}</summary><p class="mt-4 text-pretty text-base/7 text-ink/70">{{ $answer }}</p></details>@endforeach
            </div>
        </div>
    </section>
    @include('marketing.agencies.form')
@endsection
