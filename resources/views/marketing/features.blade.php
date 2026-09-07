@extends('layouts.marketing')

@section('title', 'Features')
@section('meta_description', 'Specialist website, SEO, content, lead, and Google Business Profile management for growing businesses.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <p class="font-mono text-sm uppercase tracking-wide text-garden">The complete care loop</p>
            <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Specialist website and SEO management, all in one place</h1>
            <p class="mt-6 max-w-[52ch] text-pretty text-lg text-ink/65 sm:text-base">Our specialists look after your website, enquiries, search performance, content, and local visibility as one ongoing service.</p>
            <a href="{{ route('marketing.free-site-audit') }}" class="mt-8 inline-flex rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Get started</a>
        </div>
    </section>

    @foreach ([
        ['01', 'Never lose an enquiry', 'Forms and CRM', 'forms-and-lead-management', 'We manage the forms, spam protection, customer acknowledgements, and lead handling that keep every genuine enquiry visible.', ['Website and form setup managed for you', 'Professional notifications and customer replies', 'Lead status, notes, and follow-up reminders', 'Spam review and delivery oversight']],
        ['02', 'Keep your website healthy', 'Website health & SEO', 'website-health-monitoring', 'Our specialists monitor the website, prioritise issues by business impact, and manage the practical fixes needed to keep it performing well.', ['Regular checks across important pages', 'Accessibility, metadata, links, performance, security, and technical SEO', 'Weekly reports written in plain English', 'Specialist fixes and ongoing oversight']],
        ['03', 'Improve the pages that matter', 'Managed SEO & content', 'seo-and-search-growth', 'Our SEO specialists interpret real search performance, find commercially useful opportunities, and strengthen the pages most likely to improve your visibility.', ['Keywords close to stronger positions', 'Clicks, impressions, ranking pages, and commercial relevance', 'Page-level SEO and content improvements', 'New landing pages and content written by specialists']],
        ['04', 'Show up well locally', 'Google Business Profile', 'google-business-profile', 'On the Complete plan, our team manages your Google Business Profile alongside your website and search strategy.', ['Profile health management', 'Google posts prepared for your business', 'Thoughtful customer review replies', 'One specialist view of website and local visibility']],
    ] as [$number, $headline, $label, $slug, $copy, $features])
        <section class="border-t border-ink/10 py-20 sm:py-28">
            <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
                <div><p class="font-mono text-sm text-garden">{{ $number }}</p><p class="mt-5 font-mono text-sm uppercase tracking-wide text-ink/50">{{ $label }}</p><h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $headline }}</h2></div>
                <div>
                    <p class="max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">{{ $copy }}</p>
                    <dl class="mt-10 grid gap-8 sm:grid-cols-2">
                        @foreach ($features as $feature)
                            <div class="border-t border-ink/15 pt-4"><dt class="text-base font-medium sm:text-sm">{{ $feature }}</dt><dd class="mt-2 text-base text-ink/50 sm:text-sm">Managed as part of your ongoing Sitewell service.</dd></div>
                        @endforeach
                    </dl>
                    <a href="{{ route('marketing.feature', $slug) }}" class="mt-8 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">See how {{ strtolower($label) }} works →</a>
                </div>
            </div>
        </section>
    @endforeach

    <section class="border-t border-ink/10 bg-lichen/45 py-16 sm:py-20">
        <div class="mx-auto grid max-w-7xl gap-6 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:items-center lg:px-10">
            <div><p class="font-mono text-sm uppercase tracking-wide text-garden">Included with every plan</p><h2 class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance">A free website, if you need one</h2></div>
            <div><p class="max-w-[56ch] text-pretty text-lg text-ink/65 sm:text-base">Starting without a website—or ready to replace one that no longer works for your business? A professionally set-up website is included with Essential, Growth, and Complete. Already have a site you want to keep? Bring it with you instead.</p><a href="{{ route('marketing.feature', 'website-design-and-management') }}" class="mt-6 inline-flex text-base font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden sm:text-sm">Explore website design and management →</a></div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
