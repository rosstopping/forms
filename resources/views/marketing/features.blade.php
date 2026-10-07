@extends('layouts.marketing')

@section('title', 'Features')
@section('meta_description', 'Specialist website, SEO, content, lead, and Google Business Profile management for growing businesses.')

@section('content')
    <section class="marketing-hero">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="text-base font-medium sm:text-sm text-garden">Our services</p>
            <h1 class="mt-5 max-w-[20ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-6xl">Website care. SEO. Content.</h1>
            <p class="mt-6 max-w-[52ch] text-pretty text-lg text-ink/65 sm:text-base">Choose the support you need, from website maintenance to SEO, content and local search.</p>
            <a href="{{ route('marketing.free-site-audit') }}" class="mt-8 inline-flex rounded-full bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">See what we’d fix</a>
        </div>
    </section>

    @foreach ([
        ['01', 'Never lose an enquiry', 'Forms and CRM', 'forms-and-lead-management', 'We manage the forms, spam protection, customer acknowledgements, and lead handling that keep every genuine enquiry visible.', ['Website and form setup managed for you', 'Professional notifications and customer replies', 'Lead status, notes, and follow-up reminders', 'Spam review and delivery oversight']],
        ['02', 'Keep your website healthy', 'Website health & SEO', 'website-health-monitoring', 'Our specialists monitor the website, prioritise issues by business impact, and manage the practical fixes needed to keep it performing well.', ['Regular checks across important pages', 'Accessibility, metadata, links, performance, security, and technical SEO', 'Weekly reports written in plain English', 'Specialist fixes and ongoing oversight']],
        ['03', 'Improve the pages that matter', 'Managed SEO & content', 'seo-and-search-growth', 'Our SEO specialists interpret real search performance, find commercially useful opportunities, and strengthen the pages most likely to improve your visibility.', ['Keywords close to stronger positions', 'Clicks, impressions, ranking pages, and commercial relevance', 'Page-level SEO and content improvements', 'New landing pages and content written by specialists']],
        ['04', 'Show up well locally', 'Google Business Profile', 'google-business-profile', 'On the Complete plan, our team manages your Google Business Profile alongside your website and search strategy.', ['Profile health management', 'Google posts prepared for your business', 'Thoughtful customer review replies', 'One specialist view of website and local visibility']],
    ] as [$number, $headline, $label, $slug, $copy, $features])
        <section class="border-t border-ink/10 py-12 sm:py-16">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
                <div><p class="mt-5 text-base font-medium sm:text-sm text-ink/50">{{ $label }}</p><h2 class="mt-4 max-w-[24ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-5xl">{{ $headline }}</h2></div>
                <div>
                    <p class="max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">{{ $copy }}</p>
                    <ul role="list" class="mt-8 grid gap-4 sm:grid-cols-2">
                        @foreach ($features as $feature)
                            <li class="border-t border-ink/10 pt-4 text-base">{{ $feature }}</li>
                        @endforeach
                    </ul>
                    <a href="{{ route('marketing.feature', $slug) }}" class="mt-8 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">See how {{ strtolower($label) }} works →</a>
                </div>
            </div>
        </section>
    @endforeach

    <section class="border-t border-ink/10 bg-lichen py-12 sm:py-16">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="text-base font-medium sm:text-sm text-garden">Start with what you need</p>
            <h2 class="mt-4 max-w-[24ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-5xl">Find the support you need.</h2>
            <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/60 sm:text-base">Explore Sitewell by the outcome you are looking for, from dependable management to stronger search visibility and more useful enquiries.</p>
            <nav class="mt-12" aria-label="Website and SEO services">
                <ul class="grid gap-8 md:grid-cols-2 lg:grid-cols-3" role="list">
                    @foreach (config('marketing.landing_pages') as $slug => $landing)
                        @php([$title, $description] = [$landing['eyebrow'], $landing['meta_description']])
                        <li class="border-t border-ink/15 pt-5">
                            <a href="{{ route('marketing.landing', $slug) }}" class="group">
                                <h3 class="font-sans text-2xl font-medium tracking-tight text-balance">{{ $title }}</h3>
                                <p class="mt-3 text-pretty text-base text-ink/60 sm:text-sm">{{ $description }}</p>
                                <p class="mt-5 text-base font-medium text-garden underline decoration-garden/25 underline-offset-4 group-hover:decoration-garden sm:text-sm">Explore this service →</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <ul role="list" class="mt-8 flex flex-wrap gap-x-8 gap-y-4">
                    @foreach (config('ppc.pages') as $page => $landing)
                        <li><a href="{{ route('marketing.ppc.'.$page) }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">{{ $landing['heading'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </section>

    <section class="border-t border-ink/10 bg-lichen/45 py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-6 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:items-center lg:px-10">
            <div><p class="text-base font-medium sm:text-sm text-garden">Included with every plan</p><h2 class="mt-4 font-sans text-4xl font-medium tracking-tight text-balance">A free website, if you need one</h2></div>
            <div><p class="max-w-[56ch] text-pretty text-lg text-ink/65 sm:text-base">Starting without a website—or ready to replace one that no longer works for your business? A professionally set-up website is included with Essential, Growth, and Complete. Already have a site you want to keep? Bring it with you instead.</p><a href="{{ route('marketing.feature', 'website-design-and-management') }}" class="mt-6 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">Explore website design and management →</a></div>
        </div>
    </section>

    <section class="border-t border-ink/10 py-16 sm:py-20">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <h2 class="font-sans text-4xl font-medium tracking-tight text-balance">SEO for your business.</h2>
            <p class="mt-5 max-w-[56ch] text-pretty text-base text-ink/65">Explore the search questions, service pages and enquiry journeys that matter in your industry.</p>
            <nav aria-label="Industry SEO guides" class="mt-10">
                <ul role="list" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (config('marketing.industries') as $slug => $industry)
                        <li><a class="text-garden underline decoration-garden/30 underline-offset-4 hover:text-moss" href="{{ route('marketing.industry', $slug) }}">{{ $industry['seo_title'] ?? $industry['eyebrow'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <p class="mt-10 text-base">Investigating a problem first? <a class="text-garden underline underline-offset-4" href="{{ route('marketing.journal') }}">Browse the practical SEO guides</a>.</p>
        </div>
    </section>
    <x-marketing.cta />
@endsection
