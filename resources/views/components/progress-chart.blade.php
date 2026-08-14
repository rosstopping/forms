@props(['title', 'description', 'points', 'valueKey', 'format' => 'number', 'lowerIsBetter' => false])

@php
    $points = collect($points)->values();
    $values = $points->map(fn (mixed $point): float => (float) data_get($point, $valueKey));
    $minimum = $values->min() ?? 0;
    $maximum = $values->max() ?? 0;
    $valuePadding = max(($maximum - $minimum) * 0.12, $maximum === $minimum ? max(abs($maximum) * 0.1, 1) : 0);
    $axisMinimum = max(0, $minimum - $valuePadding);
    $axisMaximum = $maximum + $valuePadding;
    $range = max(1, $axisMaximum - $axisMinimum);
    $count = max(1, $values->count() - 1);

    $formatValue = function (float $value) use ($format): string {
        return match ($format) {
            'decimal' => number_format($value, 1),
            'percentage' => number_format($value, 1).'%',
            'traffic' => '~'.number_format($value),
            default => number_format($value),
        };
    };

    $formatPeriod = function (mixed $point): string {
        if (data_get($point, 'month') !== null) {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m', data_get($point, 'month'))->format('M Y');
        }

        return \Illuminate\Support\Carbon::parse(data_get($point, 'snapshot_date'))->format('j M Y');
    };

    $chartPoints = $points->map(function (mixed $point, int $index) use ($axisMinimum, $range, $count, $valueKey, $formatValue, $formatPeriod): array {
        $value = (float) data_get($point, $valueKey);

        return [
            'x' => round(58 + (($index / $count) * 598), 2),
            'y' => round(18 + ((1 - (($value - $axisMinimum) / $range)) * 178), 2),
            'value' => $value,
            'display_value' => $formatValue($value),
            'period' => $formatPeriod($point),
        ];
    });

    $coordinates = $chartPoints->map(fn (array $point): string => $point['x'].','.$point['y'])->implode(' ');
    $yTicks = collect(range(0, 4))->map(function (int $index) use ($axisMinimum, $range, $formatValue): array {
        $value = $axisMinimum + ($range * ((4 - $index) / 4));

        return ['y' => 18 + ($index * 44.5), 'label' => $formatValue($value)];
    });
    $labelEvery = max(1, (int) ceil(max(1, $points->count() - 1) / 4));
    $xLabels = $chartPoints->filter(fn (array $point, int $index): bool => $index === 0 || $index === $points->count() - 1 || $index % $labelEvery === 0);
    $latest = $values->last();
    $previous = $values->count() > 1 ? $values->get($values->count() - 2) : null;
    $change = $previous !== null ? $latest - $previous : null;
    $improved = $change !== null && ($lowerIsBetter ? $change < 0 : $change > 0);
@endphp

<section class="@container relative rounded-lg border border-slate-950/10 bg-white p-4" data-progress-chart>
    <div class="flex min-w-0 flex-col gap-3 @sm:flex-row @sm:items-start @sm:justify-between">
        <div class="min-w-0">
            <h4 class="truncate font-medium text-slate-900">{{ $title }}</h4>
            <p class="mt-1 text-pretty text-base text-slate-500 sm:text-sm">{{ $description }}</p>
        </div>
        <div class="shrink-0 @sm:text-right">
            <p class="text-2xl font-semibold tabular-nums text-slate-950 sm:text-xl">{{ $formatValue($latest ?? 0) }}</p>
            @if ($change !== null)
                <p class="mt-1 text-sm tabular-nums {{ $change === 0 ? 'text-slate-500' : ($improved ? 'text-emerald-700' : 'text-rose-700') }}">
                    {{ $change > 0 ? '+' : '' }}{{ $formatValue($change) }} vs previous
                </p>
            @endif
        </div>
    </div>

    @if ($values->count() > 1)
        <div class="mt-4 pb-2">
            <div class="relative" data-chart-plot>
                <svg class="h-64 w-full" viewBox="0 0 680 230" role="img" aria-label="{{ $title }} over time">
                    @foreach ($yTicks as $tick)
                        <line x1="58" x2="656" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" class="stroke-slate-950/10" vector-effect="non-scaling-stroke" />
                        <text x="48" y="{{ $tick['y'] + 4 }}" text-anchor="end" class="fill-slate-500 text-[0.6875rem] tabular-nums">{{ $tick['label'] }}</text>
                    @endforeach

                    <line data-chart-guide x1="0" x2="0" y1="18" y2="196" class="hidden stroke-teal-600/30" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
                    <polyline points="{{ $coordinates }}" fill="none" class="stroke-teal-700" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" vector-effect="non-scaling-stroke" />

                    @foreach ($chartPoints as $index => $point)
                        <g
                            tabindex="0"
                            role="button"
                            aria-label="{{ $point['period'] }}: {{ $point['display_value'] }}"
                            data-chart-point
                            data-chart-x="{{ $point['x'] }}"
                            data-period="{{ $point['period'] }}"
                            data-display-value="{{ $point['display_value'] }}"
                            class="group cursor-pointer outline-none"
                            transform="translate({{ $point['x'] }} {{ $point['y'] }})"
                        >
                            <circle r="12" class="fill-transparent" />
                            <circle r="4" class="fill-white stroke-teal-700 group-focus:fill-teal-700" stroke-width="3" vector-effect="non-scaling-stroke" />
                        </g>
                    @endforeach

                    @foreach ($xLabels as $point)
                        <text x="{{ $point['x'] }}" y="220" text-anchor="middle" class="fill-slate-500 text-[0.6875rem]">{{ $point['period'] }}</text>
                    @endforeach
                </svg>

            </div>
        </div>
        <div class="pointer-events-none absolute top-(--chart-tooltip-top) left-(--chart-tooltip-left) z-10 hidden -translate-x-1/2 -translate-y-full rounded-md bg-slate-950 px-3 py-2 text-white shadow-lg" data-chart-tooltip role="status">
            <p class="text-sm font-medium tabular-nums" data-chart-tooltip-value></p>
            <p class="text-xs text-slate-300" data-chart-tooltip-period></p>
        </div>
        <p class="text-base text-slate-500 sm:text-sm">Hover over or focus any point to see its exact value.</p>
    @else
        <p class="mt-6 rounded-md bg-slate-50 p-4 text-base text-slate-500 sm:text-sm">More than one observation is needed to draw a trend.</p>
    @endif
</section>
