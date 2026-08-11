@extends('layouts.marketing')

@section('title', 'Pricing')
@section('meta_description', 'Simple Sitewell packages for freelancers, growing studios, and established agency teams.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">Straightforward monthly plans</p>
            <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Care that scales with your client list</h1>
            <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Start with the sites you manage today. Move plans whenever your portfolio grows.</p>
        </div>
    </section>

    <section class="pb-20 sm:pb-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="@container">
                <div class="grid divide-y divide-ink/10 border-y border-ink/10 @3xl:grid-cols-3 @3xl:divide-x @3xl:divide-y-0">
                    @foreach ([
                        ['Foundation', 'For freelancers and small teams.', '49', 'Up to 3 sites', ['Unlimited form submissions', 'Spam protection', 'Website health monitoring', 'Search Console reporting', 'Basic content planner', 'Email reports'], false],
                        ['Studio', 'For growing agencies and web studios.', '129', 'Up to 15 sites', ['Everything in Foundation', 'Webhook delivery', 'Advanced health checks', 'Content planner and calendar', 'Performance history', 'GitHub-based fixes', 'Priority support'], true],
                        ['Partner', 'For agencies with larger portfolios.', '249', 'Up to 40 sites', ['Everything in Studio', 'Client roles and permissions', 'White-label reports', 'API access', 'Custom onboarding', 'Dedicated support', 'Early access features'], false],
                    ] as [$name, $description, $price, $capacity, $features, $recommended])
                        <div class="flex flex-col justify-between gap-10 px-0 py-10 @3xl:px-8 @3xl:first:pl-0 @3xl:last:pr-0">
                            <div>
                                <div class="flex items-baseline justify-between gap-4"><h2 class="font-display text-2xl font-semibold tracking-tight">{{ $name }}</h2>@if ($recommended)<p class="font-mono text-sm text-garden">Best for agencies</p>@endif</div>
                                <p class="mt-3 text-pretty text-base text-ink/55 sm:text-sm">{{ $description }}</p>
                                <div class="mt-8 flex items-baseline gap-1"><p class="font-display text-5xl font-semibold tracking-tight tabular-nums">£{{ $price }}</p><p class="text-base text-ink/50 sm:text-sm">/month</p></div>
                                <p class="mt-3 font-mono text-sm text-ink/65">{{ $capacity }}</p>
                                <ul class="mt-8 grid gap-3" role="list">
                                    @foreach ($features as $feature)<li class="text-base text-ink/70 sm:text-sm">— {{ $feature }}</li>@endforeach
                                </ul>
                            </div>
                            <a href="{{ route('marketing.contact', ['plan' => strtolower($name)]) }}" @class(['rounded-md px-3 py-3 text-center text-base font-medium sm:text-sm', 'bg-garden text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden' => $recommended, 'text-ink ring-1 ring-ink/20 hover:bg-white/40' => ! $recommended])>Choose {{ $name }}</a>
                        </div>
                    @endforeach
                </div>
            </div>
            <p class="mt-5 text-pretty text-base text-ink/50 sm:text-sm">Prices exclude VAT. Need more than 40 sites or a tailored handover? We’ll shape a Partner plan around your portfolio.</p>
        </div>
    </section>

    <section class="border-t border-ink/10 bg-lichen/45 py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div><p class="font-mono text-sm uppercase tracking-wide text-garden">Included in every plan</p><h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">No paid add-ons for the basics</h2></div>
            <dl class="grid gap-8 sm:grid-cols-2">
                @foreach ([['Guided onboarding', 'We help connect each site, its forms, Search Console property, and repository.'], ['Unlimited users', 'Bring in the people who own the relationship or approve the work.'], ['Human review', 'Content and fixes stay reviewable before anything reaches a live site.'], ['Clear portability', 'Your websites, repositories, and connected accounts remain yours.']] as [$title, $copy])
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
                @foreach ([['Can I change plans later?', 'Yes. Move up or down as the number of websites you actively manage changes.'], ['Does every client need a Copilot licence?', 'No. Sitewell can coordinate reviewable repository work through the agreed agency workflow; clients do not need their own Copilot subscription.'], ['Are form submissions limited?', 'No. Every plan includes unlimited form submissions, subject to fair-use and spam controls.'], ['What does onboarding include?', 'We connect the first websites, verify forms and notifications, connect Search Console and repositories, and agree your reporting rhythm.']] as [$question, $answer])
                    <details class="group py-5"><summary class="flex list-none items-center justify-between gap-6 text-base font-medium sm:text-sm">{{ $question }}<span class="shrink-0 font-mono text-garden group-open:hidden">+</span><span class="hidden shrink-0 font-mono text-garden group-open:inline">−</span></summary><p class="mt-4 max-w-[70ch] text-pretty text-base text-ink/60 sm:text-sm">{{ $answer }}</p></details>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
