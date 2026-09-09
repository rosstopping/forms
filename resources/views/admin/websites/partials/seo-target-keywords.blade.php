<section class="overflow-hidden rounded-xl border bg-white shadow-sm" aria-labelledby="target-keywords-title">
    <div class="border-b border-slate-200 p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="target-keywords-title" class="text-lg font-semibold text-slate-950">Target keywords</h2>
                    <span class="rounded-full bg-teal-50 px-2 py-1 text-xs font-medium text-teal-800">{{ $targetKeywords->whereNull('archived_at')->count() }} / 20 active</span>
                </div>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">Track terms this website intends to rank for, including terms with no current visibility.@if ($targetKeywords->isNotEmpty()) Positions are exact DataForSEO desktop checks in location {{ config('services.dataforseo.location_code') }} ({{ strtoupper(config('services.dataforseo.language_code')) }}), separate from Search Console measurements.@endif</p>
            </div>
            @if ($canManageWebsite)
                <form method="POST" action="{{ route('admin.seo-target-keywords.store', $website) }}" class="grid w-full gap-3 rounded-lg bg-slate-50 p-3 lg:max-w-xl sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2"><label for="target-term">Search term</label><input id="target-term" name="term" value="{{ old('term') }}" required maxlength="255" placeholder="e.g. emergency plumber barnsley"></div>
                    <div><label for="target-priority">Priority</label><select id="target-priority" name="priority"><option value="normal">Normal</option><option value="high" @selected(old('priority') === 'high')>High</option></select></div>
                    <div><label for="target-note">Business context <span class="text-slate-500">(optional)</span></label><input id="target-note" name="note" value="{{ old('note') }}" maxlength="1000" placeholder="Service, audience, location…"></div>
                    @error('term')<p class="text-sm text-red-700 sm:col-span-2">{{ $message }}</p>@enderror
                    <div class="sm:col-span-2"><button type="submit" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">Add target</button></div>
                </form>
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
                            <details class="w-full rounded-lg border bg-white p-2 lg:w-80">
                                <summary class="cursor-pointer text-sm font-medium text-slate-700">Edit target</summary>
                                <form method="POST" action="{{ route('admin.seo-target-keywords.update', [$website, $target]) }}" class="mt-3 grid gap-3">
                                    @csrf @method('PUT')
                                    <div><label for="target-term-{{ $target->id }}">Search term</label><input id="target-term-{{ $target->id }}" name="term" value="{{ $target->term }}" required maxlength="255"></div>
                                    <div><label for="target-priority-{{ $target->id }}">Priority</label><select id="target-priority-{{ $target->id }}" name="priority"><option value="normal" @selected($target->priority === 'normal')>Normal</option><option value="high" @selected($target->priority === 'high')>High</option></select></div>
                                    <div><label for="target-note-{{ $target->id }}">Business context</label><textarea id="target-note-{{ $target->id }}" name="note" rows="2" maxlength="1000">{{ $target->note }}</textarea></div>
                                    <button class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save changes</button>
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
            </article>
        @empty
            <div class="p-8 text-center"><h3 class="font-semibold text-slate-950">No target keywords yet</h3><p class="mt-1 text-sm text-slate-600">Add the terms the business wants to rank for. Adding a term does not start a paid check.</p></div>
        @endforelse
    </div>
</section>
