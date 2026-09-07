@extends('layouts.marketing')

@section('title', 'Frequently asked questions')
@section('meta_description', 'Straight answers about Sitewell website management, SEO, content, ownership, setup, reporting, and ongoing support.')

@section('content')
    <section class="py-16 sm:py-24"><div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10"><p class="font-mono text-sm uppercase tracking-wide text-garden">Straight answers</p><h1 class="mt-5 max-w-[18ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">What businesses ask before handing us their website</h1><p class="mt-6 max-w-[58ch] text-pretty text-lg leading-8 text-ink/65">The practical details of working with Sitewell and putting your website and SEO under specialist management.</p></div></section>

    <section class="border-t border-ink/10 pb-20 sm:pb-28">
        <div class="mx-auto max-w-5xl px-5 sm:px-8 lg:px-10">
            <div class="divide-y divide-ink/10 border-b border-ink/10">
                @foreach ([
                    ['Do I need a new website?', 'No. You can bring your existing website to Sitewell. If it is no longer the right foundation, every plan can include a new website instead.'],
                    ['Who actually manages the work?', 'Our website and SEO specialists assess priorities, complete routine improvements, and coordinate the work included in your plan.'],
                    ['Can I take the website with me?', 'Yes. Your website is portable. We will confirm the practical handover details before you get started.'],
                    ['Will I still control my domain and business accounts?', 'Yes. Your domain and connected business accounts remain yours.'],
                    ['What will you need from me?', 'We need enough context to understand your services, locations, customers, priorities, and brand. After setup, our aim is to keep routine specialist work off your desk.'],
                    ['How will I know what is being done?', 'You receive clear reporting on website health, enquiries, search opportunities, completed work, and the priorities our team is managing next.'],
                    ['Do you guarantee Google rankings?', 'No responsible SEO specialist can guarantee a position. We provide evidence-led strategy, professional implementation, and ongoing management.'],
                    ['How quickly will SEO work?', 'That depends on the starting website, market, competition, and work required. We explain realistic priorities and monitor progress without promising instant results.'],
                    ['Can you manage our Google Business Profile?', 'Yes, on the Complete plan. Our team looks after profile health, agreed updates, posts, and customer review responses.'],
                    ['Is there an upfront fee or long contract?', 'No. Plans start from £149 per month with no upfront website-build fee, no long-term contract, and no minimum commitment.'],
                    ['What happens if something significant needs changing?', 'We handle routine work and involve you when a change needs business knowledge, a positioning decision, or explicit approval.'],
                    ['Can you work with our current website provider?', 'Yes. We can recommend the lightest technical setup and coordinate with an existing provider where appropriate.'],
                ] as [$question, $answer])
                    <details class="group py-6"><summary class="flex list-none items-center justify-between gap-6 text-base font-medium">{{ $question }}<span class="shrink-0 font-mono text-garden group-open:hidden">+</span><span class="hidden shrink-0 font-mono text-garden group-open:inline">−</span></summary><p class="mt-4 max-w-[72ch] text-pretty text-base leading-7 text-ink/60 sm:text-sm sm:leading-6">{{ $answer }}</p></details>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
