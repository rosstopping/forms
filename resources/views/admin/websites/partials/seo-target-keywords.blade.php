@php
    $comparisonCompetitors = $trackedCompetitors->where('excluded', false);
@endphp
<section class="overflow-hidden rounded-xl border bg-white shadow-sm" aria-labelledby="target-keywords-title">
    <div class="border-b border-slate-200 p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="target-keywords-title" class="text-lg font-semibold text-slate-950">Target keywords</h2>
                    <span class="rounded-full bg-teal-50 px-2 py-1 text-xs font-medium text-teal-800">{{ $targetKeywords->whereNull('archived_at')->count() }} / 20 active</span>
                </div>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">Track terms this website intends to rank for, including terms with no current visibility.@if ($targetKeywords->isNotEmpty()) Positions are exact DataForSEO desktop checks in location {{ config('services.dataforseo.location_code') }} ({{ strtoupper(config('services.dataforseo.language_code')) }}), separate from Search Console measurements.@endif</p>
                <a href="{{ route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'competitors']) }}" class="mt-2 inline-block text-sm font-medium text-teal-700 hover:underline">{{ $comparisonCompetitors->isEmpty() ? 'Add competitors to compare rankings' : 'Manage comparison competitors' }}</a>
                @if ($canManageWebsite)
                    <form method="POST" action="{{ route('admin.seo-target-keywords.check-all', $website) }}" class="mt-3">
                        @csrf
                        <button type="submit" @disabled($targetKeywords->whereNull('archived_at')->isEmpty()) class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Check all rankings</button>
                    </form>
                @endif
            </div>
            @if ($canManageWebsite)
                <div class="w-full min-w-0 lg:max-w-xl">
                    <form method="POST" action="{{ route('admin.seo-target-keywords.store', $website) }}" class="grid min-w-0 gap-4 rounded-lg bg-slate-50 p-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                        @csrf
                        <div class="min-w-0 sm:col-span-2"><label for="target-term" class="block">Search term</label><input id="target-term" name="term" value="{{ old('term') }}" required maxlength="255" placeholder="e.g. emergency plumber barnsley" class="w-full min-w-0"></div>
                        <div class="min-w-0"><label for="target-priority" class="block">Priority</label><select id="target-priority" name="priority" class="w-full"><option value="normal">Normal</option><option value="high" @selected(old('priority') === 'high')>High</option></select></div>
                        <div class="min-w-0"><label for="target-note" class="block">Business context <span class="text-slate-500">(optional)</span></label><input id="target-note" name="note" value="{{ old('note') }}" maxlength="1000" placeholder="Service, audience, location…" class="w-full min-w-0"></div>
                        @error('term')<p class="text-sm text-red-700 sm:col-span-2">{{ $message }}</p>@enderror
                        <div class="sm:col-span-2"><button type="submit" class="w-full rounded-lg bg-teal-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 sm:w-auto">Add target</button></div>
                    </form>

                    <details class="mt-3 rounded-lg border border-slate-200 bg-white p-3" @if ($errors->has('bulk_terms')) open @endif>
                        <summary class="cursor-pointer select-none text-sm font-semibold text-slate-700">Bulk add keywords</summary>
                        <form method="POST" action="{{ route('admin.seo-target-keywords.bulk-store', $website) }}" class="mt-3 grid min-w-0 gap-3 border-t border-slate-100 pt-3">
                            @csrf
                            <div class="min-w-0">
                                <label for="target-bulk-terms" class="block">Keywords</label>
                                <textarea id="target-bulk-terms" name="bulk_terms" rows="8" maxlength="6000" required class="mt-1 min-h-40 w-full min-w-0" placeholder="emergency plumber barnsley&#10;boiler installation barnsley&#10;boiler repair barnsley">{{ old('bulk_terms') }}</textarea>
                                <p class="mt-1 text-xs text-slate-500">Enter one keyword per line, up to 20. Every keyword will use normal priority.</p>
                            </div>
                            @error('bulk_terms')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                            <button type="submit" class="w-full rounded-lg bg-teal-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 sm:w-auto sm:justify-self-start">Add keywords</button>
                        </form>
                    </details>
                </div>
            @endif
        </div>
    </div>

    <div class="divide-y divide-slate-200">
        @forelse ($targetKeywords as $target)
            @php
                $latest = $target->rankings->first();
                $successful = $target->rankings->first(fn ($ranking) => in_array($ranking->status, ['ranked', 'not_found'], true));
                $previous = $successful ? $target->rankings->first(fn ($ranking) => $ranking->id !== $successful->id && in_array($ranking->status, ['ranked', 'not_found'], true)) : null;
                $currentPosition = $successful?->status === 'ranked' ? $successful->position : null;
                $previousPosition = $previous?->status === 'ranked' ? $previous->position : null;
            @endphp
            <article class="p-4 {{ $target->archived_at ? 'bg-slate-50' : '' }}">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-950">{{ $target->term }}</h3>
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $target->priority === 'high' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700' }}">{{ ucfirst($target->priority) }}</span>
                            @if ($target->archived_at)<span class="rounded-full bg-slate-200 px-2 py-1 text-xs font-medium text-slate-700">Archived</span>@endif
                        </div>
                        @if ($target->note)<p class="mt-1 text-sm text-slate-600">{{ $target->note }}</p>@endif
                        <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <div><dt class="text-slate-500">Latest position</dt><dd class="font-semibold text-slate-900">{{ $successful ? ($currentPosition ? '#'.$currentPosition : 'Not in top 100') : 'Awaiting first check' }}</dd></div>
                            <div><dt class="text-slate-500">Movement</dt><dd class="font-medium text-slate-700">
                                @if (!$previous) —
                                @elseif ($currentPosition && !$previousPosition) Newly ranked
                                @elseif (!$currentPosition && $previousPosition) Dropped beyond top 100
                                @elseif ($currentPosition === $previousPosition) Unchanged
                                @elseif ($currentPosition < $previousPosition) Improved {{ $previousPosition - $currentPosition }}
                                @else Declined {{ $currentPosition - $previousPosition }}
                                @endif
                            </dd></div>
                            <div><dt class="text-slate-500">Checked</dt><dd class="text-slate-700">{{ $latest?->observed_at?->format('j M Y, H:i') ?? '—' }}</dd></div>
                        </dl>
                        @if ($latest?->status === 'failed')<p class="mt-2 text-sm text-red-700">Latest check failed. The previous successful result remains shown.</p>@endif
                        @if ($successful?->ranking_url)<a href="{{ $successful->ranking_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 block truncate text-sm text-teal-700 underline" title="{{ $successful->ranking_url }}">{{ $successful->ranking_url }}</a>@endif
                    </div>
                    @if ($canManageWebsite)
                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <details class="w-full rounded-lg border border-slate-200 bg-white p-3 open:shadow-sm lg:w-96">
                                <summary class="cursor-pointer select-none text-sm font-medium text-slate-700">Edit target</summary>
                                <form method="POST" action="{{ route('admin.seo-target-keywords.update', [$website, $target]) }}" class="mt-4 grid min-w-0 gap-4 border-t border-slate-100 pt-4">
                                    @csrf @method('PUT')
                                    <div class="min-w-0"><label for="target-term-{{ $target->id }}" class="block">Search term</label><input id="target-term-{{ $target->id }}" name="term" value="{{ $target->term }}" required maxlength="255" class="w-full min-w-0"></div>
                                    <div class="max-w-40"><label for="target-priority-{{ $target->id }}" class="block">Priority</label><select id="target-priority-{{ $target->id }}" name="priority" class="w-full"><option value="normal" @selected($target->priority === 'normal')>Normal</option><option value="high" @selected($target->priority === 'high')>High</option></select></div>
                                    <div class="min-w-0"><label for="target-note-{{ $target->id }}" class="block">Business context</label><textarea id="target-note-{{ $target->id }}" name="note" rows="3" maxlength="1000" class="min-h-24 w-full min-w-0">{{ $target->note }}</textarea></div>
                                    <button class="w-full rounded-lg bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 sm:w-auto sm:justify-self-start">Save changes</button>
                                </form>
                            </details>
                            @if (!$target->archived_at)
                                <form method="POST" action="{{ route('admin.seo-target-keywords.check', [$website, $target]) }}">@csrf<button class="rounded-lg border px-3 py-2 text-sm font-medium hover:bg-slate-50">Check now</button></form>
                                <form method="POST" action="{{ route('admin.seo-target-keywords.archive', [$website, $target]) }}">@csrf @method('DELETE')<button class="rounded-lg border px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Archive</button></form>
                            @else
                                <form method="POST" action="{{ route('admin.seo-target-keywords.restore', [$website, $target]) }}">@csrf<button class="rounded-lg border px-3 py-2 text-sm font-medium hover:bg-white">Restore</button></form>
                            @endif
                        </div>
                    @endif
                </div>
                @if ($comparisonCompetitors->isNotEmpty())
                    <div class="mt-5 border-t border-slate-200 pt-4">
                        <h4 class="text-sm font-semibold text-slate-950">Competitor comparison</h4>
                        @if ($successful?->organic_results !== null)
                            <p class="mt-1 text-xs text-slate-500">Same search results · {{ $successful->observed_at->format('j M Y, H:i') }} · {{ strtoupper($successful->language_code) }} · Location {{ $successful->location_code }} · {{ ucfirst($successful->device) }}{{ $successful->cached ? ' · Cached result' : '' }}. Lower positions rank higher.</p>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <caption class="sr-only">Ranking comparison for {{ $target->term }}</caption>
                                    <thead class="border-b border-slate-200 text-xs text-slate-500"><tr><th scope="col" class="px-3 py-2">Website</th><th scope="col" class="px-3 py-2">Position</th><th scope="col" class="px-3 py-2">Compared with you</th><th scope="col" class="px-3 py-2">Ranking page</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr class="bg-teal-50">
                                            <th scope="row" class="px-3 py-3 font-semibold text-teal-950">Your website</th>
                                            <td class="whitespace-nowrap px-3 py-3 font-semibold tabular-nums">{{ $currentPosition ? '#'.$currentPosition : 'Not in top 100' }}</td>
                                            <td class="px-3 py-3 text-slate-500">—</td>
                                            <td class="px-3 py-3">@if ($successful->ranking_url && in_array(parse_url($successful->ranking_url, PHP_URL_SCHEME), ['http', 'https'], true))<a href="{{ $successful->ranking_url }}" target="_blank" rel="noopener noreferrer" class="block max-w-xs truncate text-teal-700 underline" title="{{ $successful->ranking_url }}">{{ $successful->ranking_url }}</a>@else — @endif</td>
                                        </tr>
                                        @foreach ($comparisonCompetitors as $competitor)
                                            @php
                                                $competitorResult = collect($successful->organic_results)->firstWhere('domain', \App\Models\WebsiteDomain::canonicalDomain(strtolower($competitor->domain)));
                                                $competitorPosition = $competitorResult['position'] ?? null;
                                                $competitorUrl = $competitorResult['url'] ?? null;
                                            @endphp
                                            <tr>
                                                <th scope="row" class="break-all px-3 py-3 font-medium text-slate-900">{{ $competitor->domain }}</th>
                                                <td class="whitespace-nowrap px-3 py-3 font-semibold tabular-nums">{{ $competitorPosition ? '#'.$competitorPosition : 'Not in top 100' }}</td>
                                                <td class="whitespace-nowrap px-3 py-3">
                                                    @if ($competitorPosition && $currentPosition)
                                                        @if ($competitorPosition < $currentPosition)<span class="text-amber-800">{{ $currentPosition - $competitorPosition }} {{ Str::plural('place', $currentPosition - $competitorPosition) }} ahead of you</span>
                                                        @elseif ($competitorPosition > $currentPosition)<span class="text-teal-800">{{ $competitorPosition - $currentPosition }} {{ Str::plural('place', $competitorPosition - $currentPosition) }} behind you</span>
                                                        @else Same position
                                                        @endif
                                                    @elseif ($competitorPosition)<span class="text-amber-800">Ranks above you</span>
                                                    @elseif ($currentPosition)<span class="text-teal-800">You rank higher</span>
                                                    @else <span class="text-slate-500">Neither in top 100</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3">@if ($competitorUrl && in_array(parse_url($competitorUrl, PHP_URL_SCHEME), ['http', 'https'], true))<a href="{{ $competitorUrl }}" target="_blank" rel="noopener noreferrer" class="block max-w-xs truncate text-teal-700 underline" title="{{ $competitorUrl }}">{{ $competitorUrl }}</a>@else — @endif</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="mt-2 text-sm text-slate-600">Run a ranking check to compare your position with tracked competitors. Older checks do not contain competitor results.</p>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="p-8 text-center"><h3 class="font-semibold text-slate-950">No target keywords yet</h3><p class="mt-1 text-sm text-slate-600">Add the terms the business wants to rank for. Adding a term does not start a paid check.</p></div>
        @endforelse
    </div>
</section>
