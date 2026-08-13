@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-widest text-teal-700">Crawled page</p>
            <h1 class="mt-1 truncate text-2xl font-semibold text-slate-950">{{ parse_url($page->url, PHP_URL_PATH) ?: '/' }}</h1>
            <a href="{{ $page->url }}" target="_blank" rel="noreferrer" class="mt-1 block truncate text-sm text-blue-700 hover:underline">{{ $page->url }}</a>
        </div>
        <a href="{{ route('admin.website-health-reports.show', [$website, $report]) }}" class="self-start rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to report</a>
    </div>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="crawl-evidence-title">
        <div class="border-b border-slate-200 p-4">
            <p class="text-xs font-medium uppercase tracking-widest text-slate-500">Recommendation evidence</p>
            <h2 id="crawl-evidence-title" class="mt-1 text-lg font-semibold text-slate-950">Current crawled values</h2>
            <p class="mt-1 text-sm text-slate-600">These values are crawl evidence, not approved or live changes.</p>
        </div>
        <dl class="grid gap-px bg-slate-200 md:grid-cols-2">
            <div class="bg-white p-4"><dt class="text-sm font-medium text-slate-500">Page title</dt><dd class="mt-1 text-sm text-slate-950">{{ $page->title ?: 'Not present' }}</dd></div>
            <div class="bg-white p-4"><dt class="text-sm font-medium text-slate-500">Meta description</dt><dd class="mt-1 text-sm text-slate-950">{{ $page->meta_description ?: 'Not present' }}</dd></div>
        </dl>
        @if (collect($page->checks)->whereNotIn('status', ['passed'])->isNotEmpty())
            <div class="divide-y divide-slate-100 border-t border-slate-200 px-4">
                @foreach (collect($page->checks)->whereNotIn('status', ['passed']) as $check)
                    <div class="flex items-start justify-between gap-4 py-3">
                        <div><p class="text-sm font-medium text-slate-950">{{ $check['label'] }}</p><p class="mt-1 text-sm text-slate-600">{{ $check['message'] }}</p></div>
                        <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium capitalize text-amber-800">{{ $check['status'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="optimisations-title">
        <div>
            <p class="text-xs font-medium uppercase tracking-widest text-teal-700">Automated deployment</p>
            <h2 id="optimisations-title" class="mt-1 text-lg font-semibold text-slate-950">Sitewell AI fixes</h2>
            <p class="mt-1 text-sm text-slate-600">Sitewell converts the crawl findings into safe Pixel changes. Review the before and after, then approve them together.</p>
        </div>

        @if ($canManageWebsite)
            <div class="mt-4 flex flex-col gap-3 rounded-lg border border-teal-200 bg-teal-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-teal-950">Let Sitewell prepare the fixes</p>
                    <p class="mt-1 text-sm text-teal-800">AI uses the page audit to write supported changes. Nothing is published until you approve it.</p>
                </div>
                <form method="POST" action="{{ route('admin.optimisations.generate', [$website, $report, $page]) }}" class="shrink-0">
                    @csrf
                    <button class="rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Generate fixes with AI</button>
                </form>
            </div>
        @endif

        @php
            $reviewableOptimisations = $page->optimisations->whereIn('status', [
                \App\Enums\OptimisationStatus::Draft,
                \App\Enums\OptimisationStatus::Approved,
            ]);
        @endphp
        @if ($canManageWebsite && $reviewableOptimisations->isNotEmpty())
            <div class="mt-4 flex flex-col gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-blue-900"><span class="font-semibold">{{ $reviewableOptimisations->count() }} {{ Str::plural('fix', $reviewableOptimisations->count()) }} ready.</span> One approval publishes this reviewed set through Pixel.</p>
                <form method="POST" action="{{ route('admin.optimisations.deploy-page', [$website, $report, $page]) }}" class="shrink-0">
                    @csrf
                    <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Approve &amp; deploy all</button>
                </form>
            </div>
        @endif

        @if ($canManageWebsite && $page->optimisations->where('status', \App\Enums\OptimisationStatus::Deployed)->isNotEmpty())
            <div class="mt-4 flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-red-800">Stop every live Pixel optimisation on this page while preserving its individual history.</p>
                <form method="POST" action="{{ route('admin.optimisations.rollback-page', [$website, $report, $page]) }}" class="shrink-0" onsubmit="return confirm('Roll back every live Pixel optimisation on this page?')">
                    @csrf
                    <button class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100">Rollback all on page</button>
                </form>
            </div>
        @endif

        <div class="mt-5 space-y-4">
            @forelse ($page->optimisations as $optimisation)
                @php
                    $currentVersion = $optimisation->versions->sortByDesc('version')->first();
                    $isLive = $optimisation->status === \App\Enums\OptimisationStatus::Deployed;
                @endphp
                <article class="rounded-lg border border-slate-200 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold capitalize text-slate-950">{{ str_replace('_', ' ', $optimisation->type->value) }}</h3>
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-800' => $isLive,
                                    'bg-blue-100 text-blue-800' => $optimisation->status === \App\Enums\OptimisationStatus::Approved,
                                    'bg-amber-100 text-amber-800' => in_array($optimisation->status, [\App\Enums\OptimisationStatus::Draft, \App\Enums\OptimisationStatus::PendingApproval], true),
                                    'bg-slate-100 text-slate-700' => $optimisation->status === \App\Enums\OptimisationStatus::RolledBack,
                                    'bg-red-100 text-red-800' => $optimisation->status === \App\Enums\OptimisationStatus::Failed,
                                ])>{{ $isLive ? 'Live via Pixel' : str_replace('_', ' ', $optimisation->status->value) }}</span>
                            </div>
                            @if ($optimisation->selector)<p class="mt-1 font-mono text-xs text-slate-500">{{ $optimisation->selector }}{{ $optimisation->attribute ? ' · '.$optimisation->attribute : '' }}</p>@endif
                            @if ($optimisation->target_description)<p class="mt-1 text-xs text-slate-500">{{ $optimisation->target_description }}</p>@endif
                        </div>
                        @if ($canManageWebsite)
                            <div class="flex shrink-0 flex-wrap gap-2">
                                @if ($isLive)
                                    <form method="POST" action="{{ route('admin.optimisations.rollback', [$website, $report, $page, $optimisation]) }}">@csrf<button class="rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Rollback</button></form>
                                @else
                                    <form method="POST" action="{{ route('admin.optimisations.deploy', [$website, $report, $page, $optimisation]) }}">@csrf<button class="rounded-md bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800">Approve &amp; deploy</button></form>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($currentVersion)
                        <dl class="mt-4 grid gap-3 rounded-lg bg-slate-50 p-3 md:grid-cols-2">
                            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Original</dt><dd class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ $currentVersion->original_value ?? 'Not recorded' }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Proposed{{ $isLive ? ' / live' : '' }}</dt><dd class="mt-1 whitespace-pre-wrap text-sm font-medium text-slate-950">{{ $currentVersion->new_value }}</dd></div>
                        </dl>
                    @endif

                    @if ($canManageWebsite && ! $isLive)
                        <details class="mt-4 rounded-lg border border-slate-200 p-3">
                            <summary class="cursor-pointer text-sm font-medium text-slate-700">Create revised version</summary>
                            <form method="POST" action="{{ route('admin.optimisation-versions.store', [$website, $report, $page, $optimisation]) }}" class="mt-3 space-y-3">
                                @csrf
                                <textarea name="new_value" rows="4" required maxlength="100000" class="w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm">{{ $currentVersion?->new_value }}</textarea>
                                <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Save new version</button>
                            </form>
                        </details>
                    @endif

                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm font-medium text-slate-700">Version and deployment history</summary>
                        <div class="mt-3 grid gap-4 lg:grid-cols-2">
                            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2 pr-3">Version</th><th class="py-2 pr-3">Value</th><th class="py-2">Created</th></tr></thead><tbody>@foreach ($optimisation->versions->sortByDesc('version') as $version)<tr class="border-b border-slate-100"><td class="py-2 pr-3 tabular-nums">v{{ $version->version }}</td><td class="max-w-72 truncate py-2 pr-3" title="{{ $version->new_value }}">{{ $version->new_value }}</td><td class="py-2 text-slate-500">{{ $version->created_at->toDayDateTimeString() }}</td></tr>@endforeach</tbody></table></div>
                            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2 pr-3">Action</th><th class="py-2 pr-3">Version</th><th class="py-2">When</th></tr></thead><tbody>@forelse ($optimisation->deployments->sortByDesc('performed_at') as $deployment)<tr class="border-b border-slate-100"><td class="py-2 pr-3 capitalize">{{ $deployment->action->value }} · {{ $deployment->status->value }}</td><td class="py-2 pr-3 tabular-nums">v{{ $deployment->version->version }}</td><td class="py-2 text-slate-500">{{ $deployment->performed_at->toDayDateTimeString() }}</td></tr>@empty<tr><td colspan="3" class="py-3 text-slate-500">Not deployed yet.</td></tr>@endforelse</tbody></table></div>
                        </div>
                    </details>
                </article>
            @empty
                <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">No optimisations have been created for this page.</p>
            @endforelse
        </div>
    </section>

    @if ($canManageWebsite)
        <details class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <summary id="create-optimisation-title" class="cursor-pointer text-sm font-medium text-slate-700">Advanced: Create optimisation manually</summary>
            <p class="mt-3 text-sm text-slate-600">Use this fallback for a precise change Sitewell cannot yet infer from its crawl evidence. HTML and JSON-LD are validated before storage.</p>
            <form method="POST" action="{{ route('admin.optimisations.store', [$website, $report, $page]) }}" class="mt-5 grid gap-4 lg:grid-cols-2">
                @csrf
                <div><label for="type" class="text-sm font-medium text-slate-700">Change type</label><select id="type" name="type" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@foreach ($optimisationTypes as $type)<option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ str_replace('_', ' ', ucfirst($type->value)) }}</option>@endforeach</select>@error('type')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="target_description" class="text-sm font-medium text-slate-700">Target description</label><input id="target_description" name="target_description" value="{{ old('target_description') }}" maxlength="255" placeholder="Services introduction paragraph" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                <div><label for="selector" class="text-sm font-medium text-slate-700">CSS selector</label><input id="selector" name="selector" value="{{ old('selector') }}" maxlength="1000" placeholder="#services-intro" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm">@error('selector')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="attribute" class="text-sm font-medium text-slate-700">Attribute</label><select id="attribute" name="attribute" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Not applicable</option>@foreach (\App\Services\OptimisationValueSanitizer::ALLOWED_CHANGE_ATTRIBUTES as $attribute)<option value="{{ $attribute }}" @selected(old('attribute') === $attribute)>{{ $attribute }}</option>@endforeach</select>@error('attribute')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="original_value" class="text-sm font-medium text-slate-700">Original value</label><textarea id="original_value" name="original_value" rows="4" maxlength="100000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm">{{ old('original_value') }}</textarea></div>
                <div><label for="new_value" class="text-sm font-medium text-slate-700">Proposed value</label><textarea id="new_value" name="new_value" rows="4" required maxlength="100000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm">{{ old('new_value') }}</textarea>@error('new_value')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div class="lg:col-span-2"><button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Create draft optimisation</button></div>
            </form>
        </details>
    @endif
</div>
@endsection
