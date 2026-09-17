@props([
    'tier' => 'Growth',
    'title',
    'description',
])

<section {{ $attributes->class(['rounded-xl border border-violet-200 bg-violet-50 p-5']) }} aria-label="Membership upgrade required">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-800">{{ $tier }} feature</span>
                <span class="text-xs font-medium uppercase tracking-widest text-violet-700">Preview</span>
            </div>
            <h2 class="mt-2 text-lg font-semibold text-slate-950">{{ $title }}</h2>
            <p class="mt-1 max-w-3xl text-slate-700 text-base sm:text-sm">{{ $description }}</p>
        </div>
        <a href="{{ route('admin.billing.index') }}" class="ui-button ui-button-primary shrink-0 text-center">View packages</a>
    </div>
</section>
