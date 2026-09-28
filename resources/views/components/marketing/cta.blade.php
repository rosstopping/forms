@props(['title' => 'Ready to put your website and SEO in expert hands?', 'text' => null, 'label' => 'Talk to a specialist →', 'route' => 'marketing.contact', 'parameters' => []])

<section class="bg-garden py-16 text-white sm:py-20">
    <div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-[3fr_1fr] lg:items-end lg:px-10">
        <div>
            <p class="font-mono text-sm uppercase tracking-wide text-white/65">Specialist support, without the agency noise</p>
            <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ $title }}</h2>
            @if ($text)<p class="mt-5 max-w-[56ch] text-pretty text-base text-white/80">{{ $text }}</p>@endif
        </div>
        <div class="text-base sm:text-sm lg:text-right"><a href="{{ route($route, $parameters) }}" class="inline-flex rounded-md px-3 py-2.5 font-medium ring-1 ring-white/50 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">{{ $label }}</a></div>
    </div>
</section>
