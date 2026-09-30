@props(['primary' => false])
<div {{ $attributes->class(['flex flex-wrap items-center gap-x-6 gap-y-3 font-medium']) }}>
    <a href="{{ route('marketing.free-site-audit') }}" @class([
        'inline-flex min-h-12 items-center justify-center gap-6 rounded-md px-4 py-3 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden',
        'bg-garden text-white hover:bg-moss' => $primary,
        'bg-lichen text-ink hover:bg-lichen/70' => ! $primary,
    ])>Check my website <span aria-hidden="true">→</span></a>
    <a href="{{ route('marketing.ppc.book') }}" class="inline-flex min-h-12 items-center underline decoration-current/30 underline-offset-4 hover:decoration-current">Book a call</a>
</div>
