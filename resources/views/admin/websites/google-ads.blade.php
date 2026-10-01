@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <header>
            <a href="{{ \App\Support\WebsiteNavigation::routeFor($website, 'search') }}" class="text-sm font-medium text-teal-700 hover:text-teal-900">← Search performance</a>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Google Ads</h1>
            <p class="mt-2 text-slate-600">Connect the Ads account for {{ $website->name }}.</p>
        </header>

        @if ($connectionError)
            <p role="alert" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $connectionError }}</p>
        @endif

        <section class="ui-panel p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Google account</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $connection ? 'Authorized with Google. Select the Ads account you want this website to use.' : 'Connect with Google to choose an Ads account.' }}</p>
                </div>
                <a href="{{ route('admin.google-ads.connect', $website) }}" class="ui-button ui-button-secondary">{{ $connection ? 'Reconnect Google' : 'Connect Google' }}</a>
            </div>

            @if ($connection)
                <div class="mt-6 border-t border-slate-200 pt-6">
                    @if ($connection->customer_id)
                        <dl class="grid gap-4 sm:grid-cols-3">
                            <div><dt class="text-xs uppercase tracking-wide text-slate-500">Account</dt><dd class="mt-1 font-medium text-slate-950">{{ $connection->customer_name ?: $connection->customer_id }}</dd></div>
                            <div><dt class="text-xs uppercase tracking-wide text-slate-500">Customer ID</dt><dd class="mt-1 font-medium tabular-nums text-slate-950">{{ $connection->customer_id }}</dd></div>
                            <div><dt class="text-xs uppercase tracking-wide text-slate-500">Currency</dt><dd class="mt-1 font-medium text-slate-950">{{ $connection->currency_code ?: 'Unknown' }}</dd></div>
                        </dl>
                    @endif

                    @if ($customerIds)
                        <p class="mt-5 text-sm text-slate-600">Accounts directly available to this Google login: {{ implode(', ', $customerIds) }}.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.google-ads.account', $website) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label for="customer_id" class="ui-label">Ads customer ID</label>
                            <input id="customer_id" name="customer_id" class="ui-input mt-1 w-full" inputmode="numeric" autocomplete="off" placeholder="123-456-7890" value="{{ old('customer_id', $connection->customer_id) }}" required>
                            @error('customer_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="login_customer_id" class="ui-label">Manager account ID <span class="font-normal text-slate-500">(if applicable)</span></label>
                            <input id="login_customer_id" name="login_customer_id" class="ui-input mt-1 w-full" inputmode="numeric" autocomplete="off" placeholder="123-456-7890" value="{{ old('login_customer_id', $connection->login_customer_id) }}">
                            @error('login_customer_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="ui-button ui-button-primary">Verify account</button>
                        </div>
                    </form>
                </div>
                <form method="POST" action="{{ route('admin.google-ads.destroy', $website) }}" class="mt-6 border-t border-slate-200 pt-5">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">Disconnect Google Ads</button>
                </form>
            @endif
        </section>

        @if ($connection?->customer_id)
            <section class="ui-panel p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-slate-950">Conversion tracking</h2>
                <p class="mt-1 text-sm text-slate-600">Check which lead actions exist in this Ads account. An action here does not prove its tag is installed or firing on the website.</p>
                @if ($conversionError)
                    <p class="mt-4 text-sm text-amber-900">{{ $conversionError }}</p>
                @elseif (count($conversionActions) === 0)
                    <p class="mt-4 text-sm font-medium text-amber-900">No enabled conversion actions found. Set up and test a lead conversion before enabling a campaign.</p>
                @else
                    <ul class="mt-4 divide-y divide-slate-200">@foreach ($conversionActions as $action)<li class="flex items-center justify-between gap-4 py-2 text-sm"><span class="font-medium text-slate-900">{{ $action['name'] }}</span><span class="text-slate-500">{{ str_replace('_', ' ', ucfirst(strtolower($action['category']))) }}{{ $action['primary'] ? ' · Primary' : '' }}</span></li>@endforeach</ul>
                    <p class="mt-3 text-xs text-slate-500">Confirm a real test conversion is recorded in Google Ads before switching on spend.</p>
                @endif
            </section>
            <section class="ui-panel p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Create a paused Search campaign</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-600">Review the searches, location, budget and ad before creating it in {{ $connection->customer_name ?: 'Google Ads' }}. The campaign stays paused until you check tracking and turn it on in Google Ads.</p>
                    </div>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900">No spend until enabled</span>
                </div>
                <form method="POST" action="{{ route('admin.google-ads.campaigns.store', $website) }}" class="mt-6 space-y-5">
                    @csrf
                    <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="ad_name" class="ui-label">Campaign name</label><input id="ad_name" name="name" class="ui-input mt-1 w-full" maxlength="120" value="{{ old('name', $website->name.' | Local Search') }}" required>@error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                        <div><label for="ad_budget" class="ui-label">Average daily budget ({{ $connection->currency_code }})</label><input id="ad_budget" name="daily_budget" class="ui-input mt-1 w-full" type="number" min="1" max="1000" step="0.01" value="{{ old('daily_budget', '20') }}" required><p class="mt-1 text-xs text-slate-500">Google can spend up to twice this on a day, within its monthly limit.</p>@error('daily_budget') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                        <div><label for="ad_city" class="ui-label">Target city</label><input id="ad_city" name="city_name" class="ui-input mt-1 w-full" value="{{ old('city_name', 'Doncaster') }}" required>@error('city_name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                        <div><label for="ad_radius" class="ui-label">Radius (miles)</label><input id="ad_radius" name="radius_miles" class="ui-input mt-1 w-full" type="number" min="1" max="50" value="{{ old('radius_miles', '20') }}" required>@error('radius_miles') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    </div>
                    <div><label for="ad_url" class="ui-label">Landing page on your verified domain</label><input id="ad_url" name="final_url" type="url" class="ui-input mt-1 w-full" value="{{ old('final_url', $website->primaryDomain() ? 'https://'.$website->primaryDomain()->domain.'/' : '') }}" required>@error('final_url') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    <div><label for="ad_keywords" class="ui-label">Searches to advertise on</label><textarea id="ad_keywords" name="keywords_text" rows="4" class="ui-input mt-1 w-full" placeholder="One buying-intent search per line" required>{{ old('keywords_text') }}</textarea><p class="mt-1 text-xs text-slate-500">1–10 exact-match searches. Use the opportunities above as research, then choose terms a buyer would use.</p>@error('keywords_text') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    <div><span class="ui-label">Headlines</span><div class="mt-2 grid gap-3 sm:grid-cols-3">@for ($i = 0; $i < 3; $i++) <div><input name="headlines[]" class="ui-input w-full" maxlength="30" placeholder="Headline {{ $i + 1 }}" value="{{ old('headlines.'.$i) }}" required></div> @endfor</div>@error('headlines') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror @error('headlines.*') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    <div><span class="ui-label">Descriptions</span><div class="mt-2 grid gap-3 sm:grid-cols-2">@for ($i = 0; $i < 2; $i++) <div><input name="descriptions[]" class="ui-input w-full" maxlength="90" placeholder="Description {{ $i + 1 }}" value="{{ old('descriptions.'.$i) }}" required></div> @endfor</div>@error('descriptions') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror @error('descriptions.*') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    <button type="submit" class="ui-button ui-button-primary">Create paused campaign</button>
                </form>
            </section>

            @if ($drafts->isNotEmpty())
                <section class="ui-panel p-5 sm:p-6"><h2 class="text-lg font-semibold text-slate-950">Campaign requests</h2><div class="mt-4 divide-y divide-slate-200">@foreach ($drafts as $draft)<div class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"><div><strong class="text-slate-900">{{ $draft->name }}</strong><span class="ml-2 text-slate-500">{{ $draft->created_at->format('j M Y') }}</span></div><span class="font-medium {{ $draft->status === 'created' ? 'text-emerald-700' : 'text-amber-800' }}">{{ $draft->status === 'created' ? 'Created · paused' : ($draft->status === 'uncertain' ? 'Check account before retrying' : 'Not created') }}</span></div>@endforeach</div></section>
            @endif
        @endif

        <section class="ui-panel p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-slate-950">Search opportunities</h2>
            <p class="mt-1 text-sm text-slate-600">Searches where this website already appears but gets few clicks. Review their buying intent before using them in ads.</p>
            @if ($searchGaps->isEmpty())
                <p class="mt-5 text-sm text-slate-600">No recent Search Console queries meet the current opportunity threshold. Connect Search Console or check back when there is more search data.</p>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-slate-500"><tr><th class="py-2 pr-4 font-medium">Search</th><th class="px-4 py-2 text-right font-medium">Impressions</th><th class="px-4 py-2 text-right font-medium">Clicks</th><th class="py-2 pl-4 text-right font-medium">Avg. position</th></tr></thead>
                        <tbody>
                            @foreach ($searchGaps as $gap)
                                <tr class="border-b border-slate-100 last:border-0"><td class="py-3 pr-4 font-medium text-slate-900">{{ $gap->query }}</td><td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ number_format($gap->impressions) }}</td><td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ number_format($gap->clicks) }}</td><td class="py-3 pl-4 text-right tabular-nums text-slate-600">{{ number_format($gap->position, 1) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
