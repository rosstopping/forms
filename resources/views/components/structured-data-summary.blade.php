@props(['report'])

@php
    $schemaPages = $report->pages->map(function ($page) {
        $checks = collect($page->checks)->filter(fn ($check) => Str::startsWith($check['key'], 'structured_data_'));
        $syntax = $checks->firstWhere('key', 'structured_data_syntax');

        return [
            'page' => $page,
            'types' => collect(data_get($syntax, 'details.types', [])),
            'issues' => $checks->whereIn('status', ['warning', 'failed'])->values(),
            'blocks' => (int) data_get($syntax, 'details.blocks', 0),
        ];
    })->filter(fn ($page) => $page['blocks'] > 0 || $page['types']->isNotEmpty() || $page['issues']->isNotEmpty());
@endphp

@if ($schemaPages->isNotEmpty())
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="structured-data-summary-title">
        <div class="border-b border-slate-200 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Search appearance</p>
            <h2 id="structured-data-summary-title" class="mt-1 font-semibold text-slate-950">Structured data and schema recommendations</h2>
            <p class="mt-1 text-sm text-slate-600">Checks cover embedded JSON-LD syntax, common Google-supported properties, and opportunities supported by visible page evidence. Valid markup does not guarantee a rich result.</p>
        </div>
        <div class="divide-y divide-slate-200">
            @foreach ($schemaPages as $schemaPage)
                <article class="p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-950" title="{{ $schemaPage['page']->url }}">{{ parse_url($schemaPage['page']->url, PHP_URL_PATH) ?: '/' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $schemaPage['blocks'] }} JSON-LD {{ Str::plural('block', $schemaPage['blocks']) }}</p>
                        </div>
                        @if ($schemaPage['types']->isNotEmpty())
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($schemaPage['types'] as $type)
                                    <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800">{{ $type }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if ($schemaPage['issues']->isNotEmpty())
                        <div class="mt-3 grid gap-2 lg:grid-cols-2">
                            @foreach ($schemaPage['issues'] as $issue)
                                <div class="rounded-md bg-slate-50 p-3 text-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="font-medium text-slate-900">{{ $issue['label'] }}</p>
                                        <span @class([
                                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-amber-100 text-amber-800' => $issue['status'] === 'warning',
                                            'bg-red-100 text-red-800' => $issue['status'] === 'failed',
                                        ])>{{ $issue['status'] === 'failed' ? 'Invalid' : 'Recommendation' }}</span>
                                    </div>
                                    <p class="mt-1 leading-6 text-slate-600">{{ $issue['message'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
