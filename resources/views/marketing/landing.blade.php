@extends('layouts.marketing')

@section('title', $landing['seo_title'])
@section('meta_description', $landing['meta_description'])

@section('structured_data')
    <script type="application/ld+json">{!! $faqSchema !!}</script>
@endsection

@section('content')
    <section class="border-b border-ink/10 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">{{ $landing['eyebrow'] }}</p>
            <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">{{ $landing['title'] }}</h1>
            <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65">{{ $landing['description'] }}</p>
            <div class="mt-8 flex flex-wrap items-center gap-5">
                <a href="{{ route('marketing.free-site-audit') }}" class="rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Get started</a>
                <a href="{{ route('marketing.contact') }}" class="text-base font-medium underline decoration-ink/20 underline-offset-4 hover:decoration-ink sm:text-sm">Talk to a website specialist →</a>
            </div>
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Why businesses look for help</p>
                <h2 class="mt-4 max-w-[22ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $landing['problem_title'] }}</h2>
                <p class="mt-5 max-w-[56ch] text-pretty text-base text-ink/60 sm:text-sm">{{ $landing['problem'] }}</p>
            </div>
            <dl class="grid gap-8 sm:grid-cols-2">
                @foreach ($landing['benefits'] as [$title, $description])
                    <div class="border-t border-ink/15 pt-5">
                        <dt class="font-display text-2xl font-semibold tracking-tight">{{ $title }}</dt>
                        <dd class="mt-3 text-pretty text-base text-ink/60 sm:text-sm">{{ $description }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen/45 py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">What Sitewell manages</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $landing['service_title'] }}</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/60 sm:text-base">{{ $landing['service_description'] }}</p>
            </div>
            <dl class="mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($landing['services'] as [$title, $description])
                    <div class="border-t border-ink/15 pt-5">
                        <dt class="text-base font-medium sm:text-sm">{{ $title }}</dt>
                        <dd class="mt-2 text-pretty text-base text-ink/55 sm:text-sm">{{ $description }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">A managed process</p>
                <h2 class="mt-4 max-w-[22ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">A useful next step, then another</h2>
            </div>
            <dl class="grid gap-8 sm:grid-cols-3">
                @foreach ($landing['steps'] as $index => [$title, $description])
                    <div class="border-t border-ink/15 pt-5">
                        <dt><span class="font-mono text-sm text-garden">0{{ $index + 1 }}</span><span class="mt-5 block font-display text-2xl font-semibold tracking-tight">{{ $title }}</span></dt>
                        <dd class="mt-3 text-pretty text-base text-ink/60 sm:text-sm">{{ $description }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-ink py-20 text-paper sm:py-24">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-apricot">What makes this different</p>
                <h2 class="mt-4 max-w-[22ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $landing['difference_title'] }}</h2>
            </div>
            <p class="max-w-[48ch] text-pretty text-lg text-paper/70">{{ $landing['difference'] }}</p>
        </div>
    </section>

    <section class="py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">Questions, plainly answered</p>
            <h2 class="mt-4 max-w-[35ch] font-display text-4xl font-semibold tracking-tight text-balance">Questions about {{ strtolower($landing['eyebrow']) }}</h2>
            <div class="mt-10 max-w-4xl divide-y divide-ink/10 border-y border-ink/10">
                @foreach ($landing['faqs'] as [$question, $answer])
                    <details class="group py-5">
                        <summary class="flex list-none items-center justify-between gap-6 text-base font-medium sm:text-sm">{{ $question }}<span class="font-mono text-garden group-open:hidden">+</span><span class="hidden font-mono text-garden group-open:inline">−</span></summary>
                        <p class="mt-4 max-w-[70ch] text-pretty text-base text-ink/60 sm:text-sm">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="border-t border-ink/10 py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">Related help</p>
            <h2 class="mt-4 max-w-[35ch] font-display text-4xl font-semibold tracking-tight text-balance">Explore the next part of a better-managed website</h2>
            <div class="mt-10 grid gap-8 md:grid-cols-3">
                @foreach ($landing['related'] as [$slug, $title, $description])
                    <a href="{{ route('marketing.landing', $slug) }}" class="group border-t border-ink/15 pt-5">
                        <h3 class="font-display text-2xl font-semibold tracking-tight text-balance">{{ $title }}</h3>
                        <p class="mt-3 text-pretty text-base text-ink/60 sm:text-sm">{{ $description }}</p>
                        <p class="mt-5 text-base font-medium text-garden underline decoration-garden/25 underline-offset-4 group-hover:decoration-garden sm:text-sm">Learn more →</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
