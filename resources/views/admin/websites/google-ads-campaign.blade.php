@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns']) }}" class="text-sm font-medium text-teal-700 hover:text-teal-900">← Campaigns</a>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $campaign['name'] }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $connection->customer_name ?: 'Google Ads' }} · Campaign {{ $campaign['id'] }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $campaign['status'] === 'ENABLED' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">{{ ucfirst(strtolower($campaign['status'])) }}</span>
                <a href="https://ads.google.com/aw/overview?campaignId={{ $campaign['id'] }}" target="_blank" rel="noopener noreferrer" class="ui-button ui-button-secondary">Open in Google Ads ↗</a>
            </div>
        </header>

        @if (session('status')) <p role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</p> @endif
        @if (session('error')) <p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ session('error') }}</p> @endif
        @if ($errors->any()) <p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">Check the highlighted fields and try again.</p> @endif

        <section class="ui-panel p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><h2 class="text-lg font-semibold text-slate-950">Last 30 days</h2><p class="mt-1 text-sm text-slate-600">Performance for this campaign</p></div>
                @if ($campaign['status'] === 'ENABLED')
                    <form method="POST" action="{{ route('admin.google-ads.live-campaigns.status', [$website, $campaign['id']]) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="PAUSED">
                        <button type="submit" class="ui-button ui-button-secondary ui-button-small">Pause campaign</button>
                    </form>
                @endif
            </div>
            <dl class="mt-5 grid grid-cols-2 gap-5 border-y border-slate-900/10 py-5 sm:grid-cols-3 lg:grid-cols-6">
                <div><dt class="text-sm text-slate-600">Impressions</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ $performance === null ? '—' : number_format($performance['impressions']) }}</dd></div>
                <div><dt class="text-sm text-slate-600">Clicks</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ $performance === null ? '—' : number_format($performance['clicks']) }}</dd></div>
                <div><dt class="text-sm text-slate-600" title="Click-through rate">CTR</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ $performance === null || $performance['impressions'] === 0 ? '—' : number_format($performance['clicks'] / $performance['impressions'] * 100, 2).'%' }}</dd></div>
                <div><dt class="text-sm text-slate-600">Spend</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ $performance === null ? '—' : $connection->currency_code.' '.number_format($performance['cost_micros'] / 1000000, 2) }}</dd></div>
                <div><dt class="text-sm text-slate-600" title="Average cost per click">Avg. CPC</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ $performance === null || $performance['clicks'] === 0 ? '—' : $connection->currency_code.' '.number_format($performance['cost_micros'] / 1000000 / $performance['clicks'], 2) }}</dd></div>
                <div><dt class="text-sm text-slate-600">Conversions</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ $performance === null ? '—' : number_format($performance['conversions'], 1) }}</dd></div>
            </dl>
            @if ($campaign['status'] === 'PAUSED')
                <form method="POST" action="{{ route('admin.google-ads.live-campaigns.status', [$website, $campaign['id']]) }}" class="mt-5 flex flex-wrap items-center gap-4">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="ENABLED">
                    <label class="flex max-w-lg items-start gap-2 text-sm text-slate-600"><input type="checkbox" name="tracking_confirmed" value="1" class="mt-0.5 size-4 shrink-0 accent-teal-700" required><span>Tracking, ad and budget checked. Enabling can start spend.</span></label>
                    <button type="submit" class="ui-button ui-button-primary ui-button-small">Enable campaign</button>
                </form>
            @endif
            <details class="mt-5 border-t border-slate-900/10 pt-4">
                <summary class="w-fit cursor-pointer text-sm font-medium text-slate-600 hover:text-slate-900">Remove campaign</summary>
                <form method="POST" action="{{ route('admin.google-ads.live-campaigns.destroy', [$website, $campaign['id']]) }}" class="mt-4 max-w-sm space-y-3">
                    @csrf @method('DELETE')
                    <label for="remove_campaign_confirmation" class="ui-label">Type “{{ $campaign['name'] }}” to remove this campaign permanently.</label>
                    <input id="remove_campaign_confirmation" name="confirmation" class="ui-input w-full" autocomplete="off" required>
                    <button type="submit" class="ui-button ui-button-danger ui-button-small">Remove campaign</button>
                </form>
            </details>
        </section>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,1fr)]">
            <div class="space-y-6">
                <section class="ui-panel p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-slate-950">Campaign settings</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ ucfirst(strtolower($campaign['type'])) }} campaign · {{ ucfirst(strtolower($campaign['status'])) }}</p>
                    <form method="POST" action="{{ route('admin.google-ads.live-campaigns.name', [$website, $campaign['id']]) }}" class="mt-5">
                        @csrf @method('PATCH')
                        <input type="hidden" name="original_name" value="{{ $campaign['name'] }}">
                        <label for="campaign_name" class="ui-label">Campaign name</label>
                        <div class="mt-2 flex flex-wrap gap-3"><input id="campaign_name" name="name" class="ui-input min-w-0 flex-1" maxlength="120" value="{{ old('name', $campaign['name']) }}" required><button class="ui-button ui-button-secondary" type="submit">Save name</button></div>
                        @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </form>
                    <div class="mt-6 border-t border-slate-200 pt-5">
                        <h3 class="font-medium text-slate-950">Targeting</h3>
                        @if ($proximities !== [])
                            <ul class="mt-2 space-y-1 text-sm text-slate-600">@foreach ($proximities as $proximity)<li>{{ $proximity['city'] ?: 'Area' }} · {{ number_format($proximity['radius'], 0) }} {{ strtolower($proximity['units']) }}</li>@endforeach</ul>
                        @else
                            <p class="mt-2 text-sm text-slate-600">Targeting is managed in Google Ads.</p>
                        @endif
                        <p class="mt-3 text-xs text-slate-500">Use Google Ads to change location, network or bidding settings.</p>
                    </div>
                </section>

                <section class="ui-panel p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-slate-950">Daily budget</h2>
                    @if (! $campaign['budget_shared'] && $campaign['budget_period'] === 'DAILY' && $campaign['budget_resource_name'] !== '')
                        <form method="POST" action="{{ route('admin.google-ads.live-campaigns.budget', [$website, $campaign['id']]) }}" class="mt-4">
                            @csrf @method('PATCH')
                            <input type="hidden" name="original_budget_micros" value="{{ $campaign['daily_budget_micros'] }}">
                            <label for="daily_budget" class="ui-label">Average daily budget ({{ $connection->currency_code }})</label>
                            <div class="mt-2 flex flex-wrap gap-3"><input id="daily_budget" name="daily_budget" type="number" min="1" max="1000" step="0.01" class="ui-input w-40" value="{{ old('daily_budget', number_format($campaign['daily_budget_micros'] / 1000000, 2, '.', '')) }}" required><button class="ui-button ui-button-secondary" type="submit">Save budget</button></div>
                            @error('daily_budget') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            <p class="mt-2 text-xs text-slate-500">Changes to an enabled campaign can affect spend immediately.</p>
                        </form>
                    @else
                        <p class="mt-3 text-sm text-slate-600">{{ $campaign['daily_budget_micros'] > 0 ? $connection->currency_code.' '.number_format($campaign['daily_budget_micros'] / 1000000, 2) : 'No daily budget shown' }}. Edit this budget in Google Ads.</p>
                    @endif
                </section>

                <section class="ui-panel p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-slate-950">Keywords</h2>
                    @if ($keywords !== [])
                        <ul class="mt-4 flex flex-wrap gap-2">@foreach ($keywords as $keyword)<li class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">{{ $keyword['text'] }} <span class="text-xs text-slate-500">{{ strtolower($keyword['match_type']) }}</span></li>@endforeach</ul>
                    @else
                        <p class="mt-3 text-sm text-slate-600">No active keywords found.</p>
                    @endif
                    <p class="mt-3 text-xs text-slate-500">Edit keywords in Google Ads.</p>
                </section>
            </div>

            <div class="space-y-6">
                @forelse ($ads as $ad)
                    <section class="ui-panel p-5 sm:p-6">
                        <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-semibold text-slate-950">Search ad</h2><span class="text-xs text-slate-500">{{ ucfirst(strtolower($ad['status'])) }}</span></div>
                        <p class="mt-1 text-xs text-slate-500">Ad {{ $ad['id'] }} · Example appearance</p>
                        <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs text-slate-500"><span class="font-semibold text-slate-800">Sponsored</span> · {{ parse_url($ad['final_url'], PHP_URL_HOST) ?: $ad['final_url'] }}</p>
                            <p class="mt-2 text-lg font-medium leading-snug text-[#1a0dab]">{{ collect($ad['headlines'])->take(3)->pluck('text')->implode(' | ') }}</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ collect($ad['descriptions'])->take(2)->pluck('text')->implode(' ') }}</p>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Google may show a different combination of your headlines and descriptions.</p>
                        <form method="POST" action="{{ route('admin.google-ads.live-campaigns.ads.update', [$website, $campaign['id'], $ad['id']]) }}" class="mt-6 space-y-4">
                            @csrf @method('PATCH')
                            <input type="hidden" name="ad_id" value="{{ $ad['id'] }}">
                            <input type="hidden" name="copy_signature" value="{{ hash('sha256', json_encode([$ad['headlines'], $ad['descriptions']])) }}">
                            <div><p class="ui-label">Headlines</p><div class="mt-2 space-y-2">@foreach ($ad['headlines'] as $index => $headline)<div><label class="sr-only" for="headline_{{ $ad['id'] }}_{{ $index }}">Headline {{ $index + 1 }}</label><input id="headline_{{ $ad['id'] }}_{{ $index }}" name="headlines[]" class="ui-input w-full" maxlength="30" value="{{ old('ad_id') === $ad['id'] ? old('headlines.'.$index, $headline['text']) : $headline['text'] }}" required></div>@endforeach</div>@error('headlines') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror @error('headlines.*') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                            <div><p class="ui-label">Descriptions</p><div class="mt-2 space-y-2">@foreach ($ad['descriptions'] as $index => $description)<div><label class="sr-only" for="description_{{ $ad['id'] }}_{{ $index }}">Description {{ $index + 1 }}</label><textarea id="description_{{ $ad['id'] }}_{{ $index }}" name="descriptions[]" rows="2" maxlength="90" class="ui-input w-full" required>{{ old('ad_id') === $ad['id'] ? old('descriptions.'.$index, $description['text']) : $description['text'] }}</textarea></div>@endforeach</div>@error('descriptions') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror @error('descriptions.*') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                            <p class="text-xs text-slate-500">Landing page: <a href="{{ $ad['final_url'] }}" target="_blank" rel="noopener noreferrer" class="break-all text-teal-700 underline">{{ $ad['final_url'] }}</a></p>
                            <button type="submit" class="ui-button ui-button-primary">Save ad copy</button>
                        </form>
                    </section>
                @empty
                    <section class="ui-panel p-5 sm:p-6"><h2 class="text-lg font-semibold text-slate-950">Search ads</h2><p class="mt-2 text-sm text-slate-600">No responsive search ads found in this campaign. Open Google Ads to review other ad types.</p></section>
                @endforelse
            </div>
        </div>
    </div>
@endsection
