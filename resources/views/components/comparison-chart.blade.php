@props(['title', 'description', 'points', 'firstKey', 'firstLabel', 'secondKey', 'secondLabel'])

@php
    $points = collect($points)->values();
    $firstValues = $points->map(fn (mixed $point): float => (float) data_get($point, $firstKey));
    $secondValues = $points->map(fn (mixed $point): float => (float) data_get($point, $secondKey));
    $firstMaximum = max(1, (float) ($firstValues->max() ?? 0));
    $secondMaximum = max(1, (float) ($secondValues->max() ?? 0));
    $count = max(1, $points->count() - 1);

    $formatPeriod = function (mixed $point): string {
        if (data_get($point, 'month') !== null) {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m', data_get($point, 'month'))->format('M Y');
        }

        return \Illuminate\Support\Carbon::parse(data_get($point, 'snapshot_date'))->format('j M Y');
    };

    $chartPoints = $points->map(function (mixed $point, int $index) use ($count, $firstKey, $firstLabel, $firstMaximum, $formatPeriod, $secondKey, $secondLabel, $secondMaximum): array {
        $firstValue = (float) data_get($point, $firstKey);
        $secondValue = (float) data_get($point, $secondKey);

        return [
            'x' => round(58 + (($index / $count) * 564), 2),
            'first_y' => round(18 + ((1 - ($firstValue / $firstMaximum)) * 178), 2),
            'second_y' => round(18 + ((1 - ($secondValue / $secondMaximum)) * 178), 2),
            'period' => $formatPeriod($point),
            'display_value' => $firstLabel.': '.number_format($firstValue).' · '.$secondLabel.': '.number_format($secondValue),
        ];
    });

    $firstCoordinates = $chartPoints->map(fn (array $point): string => $point['x'].','.$point['first_y'])->implode(' ');
    $secondCoordinates = $chartPoints->map(fn (array $point): string => $point['x'].','.$point['second_y'])->implode(' ');
    $yTicks = collect(range(0, 4))->map(fn (int $index): array => [
        'y' => 18 + ($index * 44.5),
        'first' => number_format($firstMaximum * ((4 - $index) / 4)),
        'second' => number_format($secondMaximum * ((4 - $index) / 4)),
    ]);
    $labelEvery = max(1, (int) ceil(max(1, $points->count() - 1) / 4));
    $xLabels = $chartPoints->filter(fn (array $point, int $index): bool => $index === 0 || $index === $points->count() - 1 || $index % $labelEvery === 0);
@endphp

<section class="@container relative rounded-lg border border-slate-950/10 bg-white p-4" data-progress-chart data-comparison-chart>
    <div class="flex min-w-0 flex-col gap-3 @sm:flex-row @sm:items-start @sm:justify-between">
        <div class="min-w-0">
            <h4 class="font-medium text-slate-900">{{ $title }}</h4>
            <p class="mt-1 text-pretty text-base text-slate-500 sm:text-sm">{{ $description }}</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-600" aria-label="Chart key">
            <span class="inline-flex items-center gap-2"><span class="h-0.5 w-5 bg-teal-700" aria-hidden="true"></span>{{ $firstLabel }}</span>
            <span class="inline-flex items-center gap-2"><span class="h-0.5 w-5 bg-violet-600" aria-hidden="true"></span>{{ $secondLabel }}</span>
        </div>
    </div>

    @if ($points->count() > 1)
        <div class="mt-4" data-chart-plot>
            <svg class="h-auto min-h-52 w-full" viewBox="0 0 680 230" role="img" aria-label="{{ $title }} over time">
                @foreach ($yTicks as $tick)
                    <line x1="58" x2="622" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" class="stroke-slate-950/10" vector-effect="non-scaling-stroke" />
                    <text x="48" y="{{ $tick['y'] + 4 }}" text-anchor="end" class="fill-teal-700 text-[0.6875rem] tabular-nums">{{ $tick['first'] }}</text>
                    <text x="632" y="{{ $tick['y'] + 4 }}" class="fill-violet-600 text-[0.6875rem] tabular-nums">{{ $tick['second'] }}</text>
                @endforeach

                <line data-chart-guide x1="0" x2="0" y1="18" y2="196" class="hidden stroke-slate-600/30" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
                <polyline points="{{ $firstCoordinates }}" fill="none" class="stroke-teal-700" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" vector-effect="non-scaling-stroke" />
                <polyline points="{{ $secondCoordinates }}" fill="none" class="stroke-violet-600" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" vector-effect="non-scaling-stroke" />

                @foreach ($chartPoints as $point)
                    <g
                        tabindex="0"
                        role="button"
                        aria-label="{{ $point['period'] }}: {{ $point['display_value'] }}"
                        data-chart-point
                        data-chart-x="{{ $point['x'] }}"
                        data-period="{{ $point['period'] }}"
                        data-display-value="{{ $point['display_value'] }}"
                        class="group cursor-pointer outline-none"
                    >
                        <rect x="{{ $point['x'] - 10 }}" y="18" width="20" height="178" class="fill-transparent" />
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['first_y'] }}" r="4" class="fill-white stroke-teal-700 group-focus:fill-teal-700" stroke-width="3" vector-effect="non-scaling-stroke" />
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['second_y'] }}" r="4" class="fill-white stroke-violet-600 group-focus:fill-violet-600" stroke-width="3" vector-effect="non-scaling-stroke" />
                    </g>
                @endforeach

                @foreach ($xLabels as $point)
                    <text x="{{ $point['x'] }}" y="220" text-anchor="middle" class="fill-slate-500 text-[0.6875rem]">{{ $point['period'] }}</text>
                @endforeach
            </svg>
        </div>
        <div class="pointer-events-none absolute top-(--chart-tooltip-top) left-(--chart-tooltip-left) z-10 hidden -translate-x-1/2 -translate-y-full rounded-md bg-slate-950 px-3 py-2 text-white shadow-lg" data-chart-tooltip role="status">
            <p class="text-sm font-medium tabular-nums" data-chart-tooltip-value></p>
            <p class="text-xs text-slate-300" data-chart-tooltip-period></p>
        </div>
        <p class="text-base text-slate-500 sm:text-sm">Hover over or focus any period to compare its exact values.</p>
    @else
        <p class="mt-6 rounded-md bg-slate-50 p-4 text-base text-slate-500 sm:text-sm">More than one observation is needed to draw a trend.</p>
    @endif
</section>
