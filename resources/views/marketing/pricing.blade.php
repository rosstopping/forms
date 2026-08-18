@extends('layouts.marketing')

@section('title', 'Pricing')
@section('meta_description', 'Straightforward Sitewell plans for businesses that want a healthier website, better lead handling, and sustainable online growth.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">Straightforward monthly plans</p>
            <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Everything your website needs to work harder</h1>
            <p class="mt-6 max-w-[52ch] text-pretty text-lg text-ink/65 sm:text-base">Every plan includes a free website if you need one, plus the care needed to keep it healthy. Add deeper search insight, content recommendations, and local business tools as you grow.</p>
            <p class="mt-5 inline-flex rounded-full bg-lichen px-4 py-2 font-mono text-sm font-medium text-moss ring-1 ring-moss/15">Free website included with every plan</p>
        </div>
    </section>

    <section class="pb-20 sm:pb-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="@container">
                <div class="grid divide-y divide-ink/10 border-y border-ink/10 @3xl:grid-cols-3 @3xl:divide-x @3xl:divide-y-0">
                    @foreach (config('memberships.plans') as $tier => $plan)
                        @php($recommended = $tier === 'growth')
                        <div class="flex flex-col justify-between gap-10 px-0 py-10 @3xl:px-8 @3xl:first:pl-0 @3xl:last:pr-0">
                            <div>
                                <div class="flex items-baseline justify-between gap-4"><h2 class="font-display text-2xl font-semibold tracking-tight">{{ $plan['name'] }}</h2>@if ($recommended)<p class="font-mono text-sm text-garden">Most popular</p>@endif</div>
                                <p class="mt-3 text-pretty text-base text-ink/55 sm:text-sm">{{ $plan['description'] }}</p>
                                <div class="mt-8 flex items-baseline gap-1"><p class="font-display text-5xl font-semibold tracking-tight tabular-nums">£{{ $plan['price'] }}</p><p class="text-base text-ink/50 sm:text-sm">/month</p></div>
                                <p class="mt-3 font-mono text-sm text-ink/65">{{ $plan['summary'] }}</p>
                                <ul class="mt-8 grid gap-3" role="list">
                                    @foreach ($plan['features'] as $feature)<li class="text-base text-ink/70 sm:text-sm">— {{ $feature }}</li>@endforeach
                                </ul>
                            </div>
                            <a href="{{ route('marketing.contact', ['plan' => $tier]) }}" @class(['rounded-md px-3 py-3 text-center text-base font-medium sm:text-sm', 'bg-garden text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden' => $recommended, 'text-ink ring-1 ring-ink/20 hover:bg-white/40' => ! $recommended])>Choose {{ $plan['name'] }}</a>
                        </div>
                    @endforeach
                </div>
            </div>
            <p class="mt-5 text-pretty text-base text-ink/50 sm:text-sm">Prices exclude VAT. Each plan is designed around one business website. Need a tailored setup? We can help.</p>
        </div>
    </section>

    <section class="border-t border-ink/10 bg-lichen/45 py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div><p class="font-mono text-sm uppercase tracking-wide text-garden">Included in every plan</p><h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">A solid foundation, without the extras</h2></div>
            <dl class="grid gap-8 sm:grid-cols-2">
                @foreach ([['A free website if you need one', 'Essential, Growth, and Complete all include a professionally set-up website for businesses that need one.'], ['Guided setup', 'We set up your website, forms, notifications, and reporting rhythm.'], ['Human review', 'Content, fixes, posts, and review replies remain under your control.'], ['Clear portability', 'Your website and connected accounts remain yours.']] as [$title, $copy])
                    <div class="border-t border-ink/15 pt-4"><dt class="text-base font-medium sm:text-sm">{{ $title }}</dt><dd class="mt-2 text-pretty text-base text-ink/55 sm:text-sm">{{ $copy }}</dd></div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">Questions before you start</p>
            <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Pricing, plainly answered</h2>
            <div class="mt-12 max-w-4xl divide-y divide-ink/10 border-y border-ink/10">
                @foreach ([['Is a website really included?', 'Yes. Every plan includes a website if you need one. We will agree the scope when you get started.'], ['Can I change plans later?', 'Yes. Move up as you are ready for search, content, and Google Business Profile tools.'], ['Are form submissions limited?', 'No. Every plan includes unlimited form submissions, subject to fair-use and spam controls.'], ['What does setup include?', 'We set up your website, verify forms and notifications, and agree a clear reporting rhythm. Growth and Complete add the relevant Google connections.']] as [$question, $answer])
                    <details class="group py-5"><summary class="flex list-none items-center justify-between gap-6 text-base font-medium sm:text-sm">{{ $question }}<span class="shrink-0 font-mono text-garden group-open:hidden">+</span><span class="hidden shrink-0 font-mono text-garden group-open:inline">−</span></summary><p class="mt-4 max-w-[70ch] text-pretty text-base text-ink/60 sm:text-sm">{{ $answer }}</p></details>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
