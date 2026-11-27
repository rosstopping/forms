@props(['title' => 'Show us your website. We’ll show you where we’d start.', 'text' => 'Start with a free website check. Then decide whether you’d like us to take care of the improvements.', 'label' => 'See what we’d fix', 'route' => 'marketing.free-site-audit', 'parameters' => []])

@if (\App\Support\PublicWebsite::origin() && request()->routeIs('marketing.article', 'marketing.landing', 'marketing.industry', 'marketing.feature'))
    <section class="bg-lichen px-5 py-10 sm:px-8">
        <div class="mx-auto grid max-w-7xl gap-4">
            <h2 class="text-3xl font-medium">Website and SEO plans</h2>
            <p>Website design and support cost £149 per month. A website with ongoing SEO costs £349 per month.</p>
            <p><a href="/pay-monthly-websites/" class="font-medium text-garden underline underline-offset-4">Compare the plans</a> or <a href="/case-studies/casa-amore-seo/" class="font-medium text-garden underline underline-offset-4">see how Casa Amore’s organic Google clicks rose from 178 to 280</a>.</p>
        </div>
    </section>
@else
<section class="px-3 py-8 text-ink sm:px-6 sm:py-12">
    <div class="mx-auto grid max-w-7xl gap-10 rounded-3xl bg-lichen px-5 py-10 sm:px-10 sm:py-12 lg:grid-cols-[3fr_2fr] lg:items-center">
        <div class="grid gap-4">
            <h2 class="max-w-[24ch] text-3xl font-medium tracking-tight text-balance sm:text-4xl">{{ $title }}</h2>
            @if ($text)<p class="max-w-[48ch] text-pretty text-lg text-ink/65">{{ $text }}</p>@endif
        </div>
        <p class="text-base font-medium lg:justify-self-end"><a href="{{ route($route, $parameters) }}" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-full px-5 py-3 ring-1 ring-black/20 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden">{{ $label }}</a></p>
    </div>
</section>
@endif
