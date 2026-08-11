@extends('layouts.marketing')

@section('title', 'Website care for agencies')
@section('meta_description', 'Sitewell brings form submissions, health monitoring, search performance, and reviewable website improvements into one calm workspace.')

@section('content')
    <section class="py-16 sm:py-24 lg:py-28">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-[9fr_11fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Website care, made visible</p>
                <h1 class="mt-5 max-w-[16ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl">Every client site, well looked after</h1>
                <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Sitewell monitors health, captures every lead, explains search performance, and helps ship improvements—so your clients stay visible, secure, and growing.</p>
                <div class="mt-8 flex flex-wrap items-center gap-5">
                    <a href="{{ route('marketing.contact') }}" class="rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Start onboarding</a>
                    <a href="{{ route('marketing.features') }}" class="text-base font-medium text-ink underline decoration-ink/20 underline-offset-4 hover:decoration-ink sm:text-sm">See how it works →</a>
                </div>
            </div>
            <x-marketing.product-preview />
        </div>
    </section>

    <section class="border-y border-ink/10">
        <div class="mx-auto grid max-w-7xl px-5 sm:grid-cols-2 sm:px-8 lg:grid-cols-4 lg:px-10">
            @foreach ([['Health and secure', 'Issues spotted early, clients stay confident.'], ['Every lead captured', 'No enquiry missed, every time.'], ['Search performance up', 'Clear data, better decisions.'], ['Content that compounds', 'Planned, published, and performing.']] as [$title, $copy])
                <div class="border-t border-ink/10 py-7 first:border-t-0 sm:border-t-0 sm:px-6 sm:[&:nth-child(2n)]:border-l sm:[&:nth-child(2n)]:border-ink/10 sm:[&:nth-child(odd)]:pl-0 lg:[&:not(:first-child)]:border-l lg:[&:not(:first-child)]:border-ink/10 lg:[&:nth-child(odd)]:pl-6 lg:first:pr-6 lg:last:pr-0">
                    <p class="text-base font-medium sm:text-sm">{{ $title }}</p>
                    <p class="mt-2 text-base text-ink/55 sm:text-sm">{{ $copy }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">One dependable rhythm</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Capture, understand, improve</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/60 sm:text-base">Sitewell connects the day-to-day signals that tell you whether a website is doing its job.</p>
            </div>
            <dl class="mt-14 grid gap-10 lg:grid-cols-3">
                @foreach ([['01', 'Capture every enquiry', 'Use one dependable form route across every client site, with automatic form discovery, spam protection, email delivery, and webhooks.'], ['02', 'Understand what is working', 'Bring health findings and Search Console queries, ranking pages, clicks, impressions, and positions into one useful view.'], ['03', 'Improve with confidence', 'Turn opportunities into content requests and repository fixes, delivered through pull requests for a clean human review.']] as [$number, $title, $copy])
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
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Built for the handover</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">A calm control room for every client site</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/60 sm:text-base">Clients connect the services they own. Your team gets a consistent operating view without shared passwords, scattered spreadsheets, or another technical portal to explain.</p>
                <a href="{{ route('marketing.features') }}" class="mt-7 inline-flex text-base font-medium underline decoration-ink/20 underline-offset-4 hover:decoration-ink sm:text-sm">Explore every feature →</a>
            </div>
            <div class="grid gap-4">
                @foreach ([['Forms', 'Submissions, delivery history, spam review, and per-form routing.'], ['Search', 'Queries paired with the pages that rank, sortable across the full result set.'], ['Health', 'Scheduled multi-page checks with clear findings and client-ready reports.'], ['Delivery', 'GitHub-connected content and fixes proposed through reviewable pull requests.']] as [$title, $copy])
                    <div class="grid grid-cols-[7rem_1fr] gap-4 border-t border-ink/15 pt-4"><p class="font-mono text-base text-garden sm:text-sm">{{ $title }}</p><p class="text-pretty text-base text-ink/65 sm:text-sm">{{ $copy }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
