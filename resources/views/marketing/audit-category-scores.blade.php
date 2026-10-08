@php
    $scoreGroups = [
        'On-page SEO' => ['Search essentials', 'Structured data'],
        'Crawl access' => ['Discoverability'],
        'Usability' => ['Accessibility'],
        'Security' => ['Security'],
        'Response time' => ['Availability & speed'],
    ];
@endphp
    <div class="@container grid gap-3 px-4 py-5 sm:px-6 sm:py-6">
        <dl class="flex flex-wrap items-start gap-x-4 gap-y-4 sm:gap-x-8">
            @foreach ($scoreGroups as $label => $categories)
                @php
                    $checks = $findings->filter(fn (mixed $finding): bool => is_array($finding) && in_array($finding['category'] ?? null, $categories, true) && in_array($finding['severity'] ?? null, ['passed', 'warning', 'failed'], true));
                    if ($label === 'Response time') {
                        $checks = $checks->where('key', 'response_time');
                    }
                    $score = $checks->isNotEmpty() ? (int) round($checks->where('severity', 'passed')->count() / $checks->count() * 100) : null;
                    $grade = $score === null ? '—' : match (true) {
                        $score >= 95 => 'A+', $score >= 85 => 'A', $score >= 70 => 'B', $score >= 50 => 'C', $score >= 25 => 'D', default => 'F',
                    };
                @endphp
                <div data-audit-category-score data-category="{{ $label }}" data-score="{{ $score }}" class="grid w-max min-w-14 justify-items-center gap-2 sm:min-w-16">
                    <dt class="order-last whitespace-nowrap text-center text-xs leading-4 font-medium text-ink/65" title="{{ $label }}">{{ $label }}</dt>
                    <dd class="relative grid size-14 place-items-center text-base tabular-nums sm:size-16">
                        <svg viewBox="0 0 100 100" fill="none" aria-hidden="true" @class(['absolute inset-0 size-full -rotate-90', 'text-emerald-600' => $score !== null && $score >= 85, 'text-amber-500' => $score !== null && $score >= 50 && $score < 85, 'text-rose-500' => $score !== null && $score < 50, 'text-ink/15' => $score === null])>
                            <circle cx="50" cy="50" r="43" stroke="currentColor" stroke-width="7" class="opacity-15" />
                            @if ($score !== null && $score > 0)
                                <circle cx="50" cy="50" r="43" stroke="currentColor" stroke-width="7" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $score }} 100" />
                            @endif
                        </svg>
                        <span class="font-medium text-ink">{{ $grade }}</span>
                        <span class="sr-only">{{ $score !== null ? $score.'% of '.$checks->count().' checks passed' : 'Not checked' }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
