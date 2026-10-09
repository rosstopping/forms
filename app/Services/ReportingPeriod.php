<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReportingPeriod
{
    public function __construct(
        public readonly string $preset,
        public readonly string $comparison,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly CarbonImmutable $previousStart,
        public readonly CarbonImmutable $previousEnd,
    ) {}

    public static function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::now('America/Los_Angeles')->startOfDay()->subDays(3);
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input, ?CarbonImmutable $availableEnd = null): self
    {
        $cutoff = self::cutoff();
        $validator = Validator::make($input, [
            'period' => ['nullable', Rule::in(['7d', '28d', '3m', '6m', '12m', 'custom'])],
            'comparison' => ['nullable', Rule::in(['previous', 'year'])],
            'start' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'end' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start', 'before_or_equal:'.$cutoff->toDateString()],
        ]);
        $validator->after(function ($validator) use ($input): void {
            if (($input['period'] ?? null) === 'custom' && ! $validator->errors()->has('start') && ! $validator->errors()->has('end')) {
                if (CarbonImmutable::parse($input['start'])->diffInDays(CarbonImmutable::parse($input['end'])) > 365) {
                    $validator->errors()->add('end', 'Choose a range of at most 366 days.');
                }
            }
        });
        $values = $validator->validate();
        $preset = $values['period'] ?? '28d';
        $comparison = $values['comparison'] ?? 'previous';
        $end = $availableEnd && $availableEnd->lessThan($cutoff) && $availableEnd->greaterThanOrEqualTo($cutoff->subDays(7)) ? $availableEnd : $cutoff;
        if ($preset === 'custom') {
            $start = CarbonImmutable::parse($values['start'], 'America/Los_Angeles')->startOfDay();
            $end = CarbonImmutable::parse($values['end'], 'America/Los_Angeles')->startOfDay();
        } else {
            $start = match ($preset) {
                '7d' => $end->subDays(6),
                '28d' => $end->subDays(27),
                default => $end->addDay()->subMonthsNoOverflow((int) $preset),
            };
        }
        $days = (int) $start->diffInDays($end) + 1;
        $previousEnd = $comparison === 'year' ? $end->subYearNoOverflow() : $start->subDay();

        return new self($preset, $comparison, $start, $end, $previousEnd->subDays($days - 1), $previousEnd);
    }
}
