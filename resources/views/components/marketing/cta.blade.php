@props(['title' => 'See what your website needs.', 'text' => null, 'label' => 'Get your free search audit', 'route' => 'marketing.free-site-audit', 'parameters' => []])

<section class="px-3 py-8 text-ink sm:px-6 sm:py-12">
    <div class="mx-auto grid max-w-7xl gap-10 rounded-3xl bg-lichen px-5 py-10 sm:px-10 sm:py-12 lg:grid-cols-[3fr_2fr] lg:items-center">
        <div class="grid gap-4">
            <h2 class="max-w-[24ch] text-3xl font-medium tracking-tight text-balance sm:text-4xl">{{ $title }}</h2>
            @if ($text)<p class="max-w-[48ch] text-pretty text-lg text-ink/65">{{ $text }}</p>@endif
        </div>
        <p class="text-base font-medium lg:justify-self-end"><a href="{{ route($route, $parameters) }}" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-full px-5 py-3 ring-1 ring-black/20 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden">{{ $label }}</a></p>
    </div>
</section>
