@props(['title', 'description', 'points', 'valueKey', 'format' => 'number', 'lowerIsBetter' => false])
@php
    $values = collect($points)->pluck($valueKey)->map(fn ($value) => (float) $value);
    $minimum = $values->min() ?? 0;
    $maximum = $values->max() ?? 0;
    $range = max(1, $maximum - $minimum);
    $count = max(1, $values->count() - 1);
    $coordinates = $values->values()->map(function ($value, $index) use ($minimum, $range, $count, $lowerIsBetter) {
        $ratio = ($value - $minimum) / $range;
        $vertical = $lowerIsBetter ? $ratio : 1 - $ratio;
        return round(($index / $count) * 600, 2).','.round(12 + ($vertical * 126), 2);
    })->implode(' ');
    $latest = $values->last();
    $display = match ($format) { 'decimal' => number_format($latest ?? 0, 1), 'traffic' => '~'.number_format($latest ?? 0), default => number_format($latest ?? 0) };
@endphp
<section class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="flex items-start justify-between gap-4"><div><h4 class="font-medium text-slate-900">{{ $title }}</h4><p class="mt-1 text-xs text-slate-500">{{ $description }}</p></div><p class="text-lg font-semibold tabular-nums text-slate-950">{{ $display }}</p></div>
    @if ($values->count() > 1)
        <svg class="mt-4 h-40 w-full" viewBox="0 0 600 150" role="img" aria-label="{{ $title }} over time" preserveAspectRatio="none"><path d="M0 138 H600" stroke="#e2e8f0"/><polyline points="{{ $coordinates }}" fill="none" stroke="#0f766e" stroke-width="4" vector-effect="non-scaling-stroke"/></svg>
        <div class="flex justify-between text-xs text-slate-500"><span>{{ \Illuminate\Support\Carbon::parse(collect($points)->first()['month'] ?? collect($points)->first()['snapshot_date'])->format('M Y') }}</span><span>{{ \Illuminate\Support\Carbon::parse(collect($points)->last()['month'] ?? collect($points)->last()['snapshot_date'])->format('M Y') }}</span></div>
    @else
        <p class="mt-6 rounded-md bg-slate-50 p-4 text-sm text-slate-500">More than one observation is needed to draw a trend.</p>
    @endif
</section>
