@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <header>
            <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Google Ads</h1>
            <p class="mt-2 text-slate-600">{{ $connection?->customer_id ? ($connection->customer_name ?: 'Ads account').' · '.$connection->customer_id : 'Connect an Ads account for '.$website->name.'.' }}</p>
        </header>

        <nav class="ui-tabs" aria-label="Google Ads sections">
            @foreach (['campaigns' => 'Campaigns', 'create' => 'Create campaign', 'settings' => 'Settings'] as $section => $label)
                <a href="{{ route('admin.google-ads.index', ['website' => $website, 'tab' => $section]) }}" class="ui-tab" @if ($tab === $section) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if (session('status'))
            <p role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ session('error') }}</p>
        @endif
        @if (! $oauthConfigured)
            <p role="alert" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Google Ads connection is not configured in this environment. {{ Auth::user()?->isAdmin() ? 'Set GOOGLE_ADS_CLIENT_ID and GOOGLE_ADS_CLIENT_SECRET, or the shared GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET, then refresh this page.' : 'Ask Sitewell to check the Google connection settings.' }}</p>
        @endif

        @if ($connectionError)
            <p role="alert" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $connectionError }}</p>
        @endif

        @if ($tab === 'settings')
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

                    @if ($unavailableAccountCount > 0)
                        <p class="mt-5 text-sm text-amber-800">{{ $unavailableAccountCount }} {{ \Illuminate\Support\Str::plural('account', $unavailableAccountCount) }} could not be loaded. Reconnect Google if the account you need is missing.</p>
                    @endif

                    @if ($availableAccounts)
                        @php($selectedAccount = old('account', ($connection->login_customer_id ? $connection->login_customer_id.':' : '').$connection->customer_id))
                        <form method="POST" action="{{ route('admin.google-ads.account', $website) }}" class="mt-5 space-y-4">
                            @csrf
                            <div>
                                <label for="account" class="ui-label">Ads account</label>
                                <select id="account" name="account" class="ui-input mt-1 w-full" required>
                                    <option value="">Choose an account</option>
                                    @foreach ($availableAccounts as $account)
                                        @php($accountValue = ($account['login_customer_id'] ? $account['login_customer_id'].':' : '').$account['id'])
                                        <option value="{{ $accountValue }}" @selected($selectedAccount === $accountValue)>{{ $account['name'] }} · {{ $account['id'] }}{{ $account['currency'] ? ' · '.$account['currency'] : '' }}{{ $account['manager_name'] ? ' · via '.$account['manager_name'] : '' }}</option>
                                    @endforeach
                                </select>
                                @error('account') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="ui-button ui-button-primary">Use this account</button>
                        </form>
                    @elseif (! $connectionError)
                        <p class="mt-5 text-sm text-slate-600">No client Ads accounts found for this Google login. Check its access in Google Ads, then reconnect.</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.google-ads.destroy', $website) }}" class="mt-6 border-t border-slate-200 pt-5">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">Disconnect Google Ads</button>
                </form>
            @endif
        </section>
        @endif

        @if ($connection?->customer_id)
            @if ($tab === 'settings')
            <section class="ui-panel p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-slate-950">Conversion tracking</h2>
                <p class="mt-1 text-sm text-slate-600">Use one real enquiry to check that Google Ads receives the right lead action before you enable a campaign.</p>
                <ol class="mt-5 grid gap-3 sm:grid-cols-3">
                    <li class="ui-well p-4"><span class="text-xs font-semibold text-teal-700">1 · Create the lead action</span><p class="mt-2 text-sm text-slate-700">In this Ads account, create a Website conversion for a submitted lead. Choose manual setup with a Google Ads tag, not an event from an unrelated GA4 property. GA4 is optional.</p></li>
                    <li class="ui-well p-4"><span class="text-xs font-semibold text-teal-700">2 · Run a real test</span><p class="mt-2 text-sm text-slate-700">For a website-tag action, choose Troubleshoot in Google Ads to launch Tag Assistant. Complete the form once and check the conversion fires only after it succeeds.</p></li>
                    <li class="ui-well p-4"><span class="text-xs font-semibold text-teal-700">3 · Confirm in Ads</span><p class="mt-2 text-sm text-slate-700">Check the action in Google Ads. Tag Assistant can confirm the tag fired; Ads may take around 30 minutes to update its status.</p></li>
                </ol>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="https://tagassistant.google.com/" target="_blank" rel="noopener noreferrer" class="ui-button ui-button-secondary ui-button-small">Tag Assistant ↗</a>
                    <a href="https://ads.google.com/aw/conversions" target="_blank" rel="noopener noreferrer" class="ui-button ui-button-secondary ui-button-small">Open conversion goals ↗</a>
                    @if ($website->primaryDomain())
                        <a href="https://{{ $website->primaryDomain()->domain }}" target="_blank" rel="noopener noreferrer" class="ui-button ui-button-secondary ui-button-small">Open website ↗</a>
                    @endif
                </div>
                <p class="mt-3 text-xs text-slate-500">Check account {{ $connection->customer_id }} in Google Ads. Sitewell can prepare the code change, but cannot confirm a live conversion until you test it.</p>
                @if ($conversionError)
                    <p class="mt-5 text-sm text-amber-900">{{ $conversionError }}</p>
                @elseif (count($conversionActions) === 0)
                    <p class="mt-5 text-sm font-medium text-amber-900">No enabled conversion actions found. In Google Ads, create a Website → Submit lead form conversion using manual setup, then refresh this page. An empty GA4 event picker does not prevent this.</p>
                @else
                    <h3 class="mt-6 text-sm font-semibold text-slate-950">Enabled actions in this account</h3>
                    <ul class="mt-2 divide-y divide-slate-200">@foreach ($conversionActions as $action)<li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"><span class="font-medium text-slate-900">{{ $action['name'] }}</span><span class="text-xs text-slate-600">{{ str_replace('_', ' ', ucfirst(strtolower($action['category']))) }} · {{ match ($action['type']) { 'WEBPAGE', 'WEBPAGE_CODELESS' => 'Website tag', 'GOOGLE_ANALYTICS_4_CUSTOM', 'GOOGLE_ANALYTICS_4_GENERATE_LEAD' => 'Imported from GA4', 'UPLOAD_CLICKS' => 'Click upload', default => str_replace('_', ' ', ucfirst(strtolower($action['type'] ?: 'Unknown source'))) } }}{{ $action['primary'] ? ' · Primary' : ' · Secondary' }}</span></li>@endforeach</ul>
                    <p class="mt-3 text-xs text-slate-500">For GA4 imports, check the event in GA4 DebugView and its import in Ads. Click-upload actions need an upload test; Tag Assistant checks website tags.</p>
                @endif
                <div class="mt-6 border-t border-slate-200 pt-6">
                    <h3 class="text-base font-semibold text-slate-950">Prepare the tracking change</h3>
                    <p class="mt-1 text-sm text-slate-600">Choose a website-tag lead action. Sitewell will ask Copilot to implement it in the connected repository and open a pull request for review. Nothing goes live from this button.</p>
                    @if (! $canPrepareTracking)
                        <p class="mt-3 text-sm text-amber-800">Connect an authorized GitHub repository and verify this website’s domain to prepare a tracking pull request.</p>
                        <div class="mt-3 flex flex-wrap gap-3"><a href="{{ route('admin.website-repositories.create', $website) }}" class="ui-button ui-button-secondary ui-button-small">Connect repository</a>@if ($website->repository && ! auth()->user()->githubAuthorization)<a href="{{ route('admin.github.connect', $website) }}" class="ui-button ui-button-secondary ui-button-small">Authorize GitHub</a>@endif</div>
                    @elseif (collect($conversionActions)->where('type', 'WEBPAGE')->whereIn('category', \App\Services\GoogleAdsClient::LEAD_CONVERSION_CATEGORIES)->isEmpty())
                        <p class="mt-3 text-sm text-amber-800">In Google Ads, create a Website → Submit lead form conversion using manual setup with a Google Ads tag. Leave the GA4 event picker if it shows another website’s property. Then refresh this page.</p>
                    @else
                        <form method="POST" action="{{ route('admin.google-ads.tracking.store', $website) }}" class="mt-4 space-y-4">
                            @csrf
                            <div><label for="tracking_action" class="ui-label">Lead conversion</label><select id="tracking_action" name="conversion_action_id" class="ui-input mt-1 w-full" required><option value="">Choose an action</option>@foreach ($conversionActions as $action)@if ($action['type'] === 'WEBPAGE' && in_array($action['category'], \App\Services\GoogleAdsClient::LEAD_CONVERSION_CATEGORIES, true) && ctype_digit($action['id']))<option value="{{ $action['id'] }}" @selected(old('conversion_action_id') === $action['id'])>{{ $action['name'] }} · {{ $action['primary'] ? 'Primary' : 'Secondary' }}</option>@endif @endforeach</select>@error('conversion_action_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                            <div><label for="tracking_success" class="ui-label">What counts as a successful lead?</label><textarea id="tracking_success" name="lead_success_description" rows="2" maxlength="500" class="ui-input mt-1 w-full" placeholder="E.g. the contact form is accepted and the thank-you message appears" required>{{ old('lead_success_description') }}</textarea><p class="mt-1 text-xs text-slate-500">Describe the completed action, not a button click or page view.</p>@error('lead_success_description')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                            <button type="submit" class="ui-button ui-button-primary">Prepare tracking PR</button>
                        </form>
                    @endif
                    @if ($trackingRequests->isNotEmpty())
                        <div class="mt-6 border-t border-slate-200 pt-4"><h4 class="text-sm font-semibold text-slate-950">Tracking requests</h4><ul class="mt-2 divide-y divide-slate-200">@foreach ($trackingRequests as $trackingRequest)<li class="flex flex-wrap items-start justify-between gap-3 py-3 text-sm"><div><span class="font-medium text-slate-950">{{ $trackingRequest->conversion_action_name }}</span><p class="mt-1 text-xs text-slate-600">{{ $trackingRequest->created_at->format('j M Y') }} · {{ str_replace('_', ' ', ucfirst($trackingRequest->status)) }}</p>@if ($trackingRequest->error)<p class="mt-1 text-xs text-amber-800">{{ $trackingRequest->error }}</p>@endif</div>@if ($trackingRequest->pull_request_url)<a href="{{ $trackingRequest->pull_request_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-teal-700 underline">Review pull request ↗</a>@elseif ($trackingRequest->copilot_task_url)<a href="{{ $trackingRequest->copilot_task_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-teal-700 underline">View Copilot task ↗</a>@endif</li>@endforeach</ul></div>
                    @endif
                </div>
            </section>
            @endif
            @if ($tab === 'create')
            <section class="ui-panel p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Create a paused Search campaign</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-600">Review the searches, location, budget and ad before creating it in {{ $connection->customer_name ?: 'Google Ads' }}. The campaign stays paused until you check tracking and turn it on in Google Ads.</p>
                        @if ($connection->campaign_form_draft_saved_at)
                            <p class="mt-2 text-xs font-medium text-teal-700">Draft saved {{ $connection->campaign_form_draft_saved_at->diffForHumans() }}</p>
                        @endif
                    </div>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900">No spend until enabled</span>
                </div>
                <form method="POST" action="{{ route('admin.google-ads.campaigns.store', $website) }}" class="mt-6 space-y-5">
                    @csrf
                    <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="ad_name" class="ui-label">Campaign name</label><input id="ad_name" name="name" class="ui-input mt-1 w-full" maxlength="120" value="{{ old('name', $formDraft['name'] ?? $website->name.' | Local Search') }}" required>@error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                        <div><label for="ad_budget" class="ui-label">Average daily budget ({{ $connection->currency_code }})</label><input id="ad_budget" name="daily_budget" class="ui-input mt-1 w-full" type="number" min="1" max="1000" step="0.01" value="{{ old('daily_budget', $formDraft['daily_budget'] ?? '20') }}" required><p class="mt-1 text-xs text-slate-500">Google can spend up to twice this on a day, within its monthly limit.</p>@error('daily_budget') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                        <div><label for="ad_max_cpc" class="ui-label">Max CPC bid ({{ $connection->currency_code }})</label><input id="ad_max_cpc" name="max_cpc" class="ui-input mt-1 w-full" type="number" min="0.02" max="1000" step="0.01" placeholder="e.g. 3.00" value="{{ old('max_cpc', $formDraft['max_cpc'] ?? '') }}" required><p class="mt-1 text-xs text-slate-500">The most you are willing to bid for one click. Set this separately from the daily budget.</p>@error('max_cpc') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                        <div><label for="ad_city" class="ui-label">Target city</label><input id="ad_city" name="city_name" class="ui-input mt-1 w-full" value="{{ old('city_name', $formDraft['city_name'] ?? 'Doncaster') }}" required>@error('city_name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                        <div><label for="ad_radius" class="ui-label">Radius (miles)</label><input id="ad_radius" name="radius_miles" class="ui-input mt-1 w-full" type="number" min="1" max="50" value="{{ old('radius_miles', $formDraft['radius_miles'] ?? '20') }}" required>@error('radius_miles') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    </div>
                    <div><label for="ad_url" class="ui-label">Landing page on your verified domain</label><input id="ad_url" name="final_url" type="url" class="ui-input mt-1 w-full" value="{{ old('final_url', $formDraft['final_url'] ?? ($website->primaryDomain() ? 'https://'.$website->primaryDomain()->domain.'/' : '')) }}" required>@error('final_url') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    <div><label for="ad_brief" class="ui-label">What should this ad promote? <span class="font-normal text-slate-500">Optional</span></label><textarea id="ad_brief" name="campaign_brief" rows="2" maxlength="1000" class="ui-input mt-1 w-full" placeholder="A short description of the service or product on this page">{{ old('campaign_brief', $formDraft['campaign_brief'] ?? '') }}</textarea><p class="mt-1 text-xs text-slate-500">Helps the suggestions stay accurate if we have not scanned this page yet.</p>@error('campaign_brief') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    <div class="flex flex-wrap items-center gap-3"><button type="submit" formaction="{{ route('admin.google-ads.suggestions', $website) }}" formnovalidate class="ui-button ui-button-secondary">Suggest keywords &amp; ad copy</button><span class="text-xs text-slate-500">Fills the fields below. Review and edit before creating.</span></div>
                    <div><label for="ad_keywords" class="ui-label">Searches to advertise on</label><textarea id="ad_keywords" name="keywords_text" rows="4" class="ui-input mt-1 w-full" placeholder="One buying-intent search per line" required>{{ old('keywords_text', $formDraft['keywords_text'] ?? '') }}</textarea><p class="mt-1 text-xs text-slate-500">1–10 exact-match searches. Use the opportunities above as research, then choose terms a buyer would use.</p>@error('keywords_text') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    <div><span class="ui-label">Headlines</span><div class="mt-2 grid gap-3 sm:grid-cols-3">@for ($i = 0; $i < 3; $i++) <div><input name="headlines[]" class="ui-input w-full" maxlength="30" placeholder="Headline {{ $i + 1 }}" value="{{ old('headlines.'.$i, $formDraft['headlines'][$i] ?? '') }}" required></div> @endfor</div>@error('headlines') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror @error('headlines.*') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    <div><span class="ui-label">Descriptions</span><div class="mt-2 grid gap-3 sm:grid-cols-2">@for ($i = 0; $i < 2; $i++) <div><input name="descriptions[]" class="ui-input w-full" maxlength="90" placeholder="Description {{ $i + 1 }}" value="{{ old('descriptions.'.$i, $formDraft['descriptions'][$i] ?? '') }}" required></div> @endfor</div>@error('descriptions') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror @error('descriptions.*') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror</div>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="ui-button ui-button-primary">Create paused campaign</button>
                        <button type="submit" formaction="{{ route('admin.google-ads.campaign-draft.save', $website) }}" formnovalidate class="ui-button ui-button-secondary">Save draft</button>
                    </div>
                </form>
            </section>
            @endif

            @if ($tab === 'campaigns')
                <section class="ui-panel p-5 sm:p-6">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight text-slate-950">Campaigns</h2>
                        <p class="mt-1 text-base text-slate-600 sm:text-sm">{{ $connection->customer_name ?: 'Ads account '.$connection->customer_id }}</p>
                    </div>
                    @if ($campaignError)
                        <p role="alert" class="mt-5 text-sm text-amber-800">{{ $campaignError }}</p>
                    @elseif ($campaigns === [])
                        <p class="mt-5 text-sm text-slate-600">No campaigns in this account yet.</p>
                    @else
                        <div class="mt-6 border-y border-slate-900/10 py-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2"><h3 class="text-sm font-semibold text-slate-950">Last 30 days</h3><p class="text-sm text-slate-500">Across {{ number_format($campaignCounts['all']) }} {{ \Illuminate\Support\Str::plural('campaign', $campaignCounts['all']) }}</p></div>
                            <div class="@container mt-4">
                                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 @lg:grid-cols-3 @4xl:grid-cols-6">
                                    <div><dt class="truncate text-base font-medium text-slate-600 sm:text-sm">Impressions</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $campaignTotals === null ? '—' : number_format($campaignTotals['impressions']) }}</dd></div>
                                    <div><dt class="truncate text-base font-medium text-slate-600 sm:text-sm">Clicks</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $campaignTotals === null ? '—' : number_format($campaignTotals['clicks']) }}</dd></div>
                                    <div><dt class="truncate text-base font-medium text-slate-600 sm:text-sm" title="Click-through rate">CTR</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $campaignTotals === null || $campaignTotals['impressions'] === 0 ? '—' : number_format($campaignTotals['clicks'] / $campaignTotals['impressions'] * 100, 2).'%' }}</dd></div>
                                    <div><dt class="truncate text-base font-medium text-slate-600 sm:text-sm">Spend</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $campaignTotals === null ? '—' : $connection->currency_code.' '.number_format($campaignTotals['cost_micros'] / 1000000, 2) }}</dd></div>
                                    <div><dt class="truncate text-base font-medium text-slate-600 sm:text-sm" title="Average cost per click">Avg. CPC</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $campaignTotals === null || $campaignTotals['clicks'] === 0 ? '—' : $connection->currency_code.' '.number_format($campaignTotals['cost_micros'] / 1000000 / $campaignTotals['clicks'], 2) }}</dd></div>
                                    <div><dt class="truncate text-base font-medium text-slate-600 sm:text-sm">Conversions</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $campaignTotals === null ? '—' : number_format($campaignTotals['conversions'], 1) }}</dd></div>
                                </dl>
                            </div>
                            @if ($campaignTotals === null)
                                <p class="mt-3 text-base text-amber-800 sm:text-sm">Performance is temporarily unavailable. Refresh to try again.</p>
                            @endif
                        </div>
                        <nav class="mt-6 flex max-w-full gap-6 overflow-x-auto border-b border-slate-900/10" aria-label="Filter campaigns by status">
                            @foreach (['all' => 'All', 'enabled' => 'Enabled', 'paused' => 'Paused'] as $filter => $label)
                                <a href="{{ route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns', 'status' => $filter]) }}" class="flex shrink-0 items-center gap-2 border-b-2 pb-3 text-base font-medium sm:text-sm {{ $statusFilter === $filter ? 'border-teal-700 text-teal-800' : 'border-transparent text-slate-500 hover:text-slate-900' }}" @if ($statusFilter === $filter) aria-current="page" @endif>{{ $label }} <span class="tabular-nums">{{ $campaignCounts[$filter] }}</span></a>
                            @endforeach
                        </nav>
                        @if ($visibleCampaigns === [])
                            <p class="mt-5 text-sm text-slate-600">No {{ $statusFilter }} campaigns in this account.</p>
                        @else
                        <div class="divide-y divide-slate-900/10">
                            @foreach ($visibleCampaigns as $campaign)
                                <article class="py-6 last:pb-0">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-semibold text-slate-950"><a href="{{ route('admin.google-ads.live-campaigns.show', [$website, $campaign['id']]) }}" class="hover:text-teal-700 focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700">{{ $campaign['name'] }}</a></h3>
                                            <p class="mt-1 text-base text-slate-600 sm:text-sm">{{ ucfirst(strtolower($campaign['type'])) }} · {{ $campaign['daily_budget_micros'] > 0 ? $connection->currency_code.' '.number_format($campaign['daily_budget_micros'] / 1000000, 2).' daily budget' : 'Budget unavailable' }} <span class="text-slate-400">· ID {{ $campaign['id'] }}</span></p>
                                        </div>
                                        <span class="shrink-0 rounded-full px-3 py-1 text-sm font-medium {{ $campaign['status'] === 'ENABLED' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">{{ ucfirst(strtolower($campaign['status'])) }}</span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @endif
                    @endif
                </section>

            @if ($drafts->isNotEmpty())
                <section class="ui-panel p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-4"><h2 class="text-lg font-semibold text-slate-950">Campaign requests</h2><a href="{{ route('admin.google-ads.index', ['website' => $website, 'tab' => 'campaigns']) }}" class="text-sm font-medium text-teal-700 hover:text-teal-900">Refresh status</a></div>
                    <div class="mt-4 divide-y divide-slate-200">
                        @foreach ($drafts as $draft)
                            <div class="flex flex-wrap items-start justify-between gap-2 py-3 text-sm">
                                <div>
                                    <strong class="text-slate-900">{{ $draft->name }}</strong><span class="ml-2 text-slate-500">{{ $draft->created_at->format('j M Y') }}</span>
                                    <p class="mt-1 text-xs text-slate-500">Ads account {{ $draft->customer_id }}@if ($draft->campaign_resource_name) · Campaign ID {{ \Illuminate\Support\Str::afterLast($draft->campaign_resource_name, '/') }}@endif</p>
                                    @if ($draft->status === 'pending' && $draft->created_at->lt(now()->subMinutes(3)))
                                        <p class="mt-1 max-w-xl text-sm text-amber-800">Still queued. Ask Sitewell to check the campaign worker before submitting again.</p>
                                    @endif
                                    @if ($draft->error)<p class="mt-1 max-w-xl text-sm text-slate-600">{{ $draft->error }}</p>@endif
                                    @if ($draft->status === 'uncertain')
                                        <form method="POST" action="{{ route('admin.google-ads.campaigns.check', [$website, $draft]) }}" class="mt-2">
                                            @csrf
                                            <button type="submit" class="text-sm font-medium text-teal-700 underline hover:text-teal-900">Check this Ads account</button>
                                        </form>
                                        @if (session('campaign_check.draft_id') === $draft->id)
                                            @if (count(session('campaign_check.matches', [])) > 0)
                                                <p class="mt-2 text-sm font-medium text-emerald-800">Matching campaign{{ count(session('campaign_check.matches')) === 1 ? '' : 's' }} found in account {{ $draft->customer_id }}:</p>
                                                <ul class="mt-1 text-sm text-emerald-800">@foreach (session('campaign_check.matches') as $match)<li>ID {{ $match['id'] }} · {{ ucfirst(strtolower($match['status'])) }}</li>@endforeach</ul>
                                            @else
                                                <p class="mt-2 max-w-xl text-sm text-amber-800">No campaign with this name was found in account {{ $draft->customer_id }}. Check the same account in Google Ads before creating another.</p>
                                                @if ($draft->updated_at->lte(now()->subMinutes(5)))
                                                    <form method="POST" action="{{ route('admin.google-ads.campaigns.clear', [$website, $draft]) }}" class="mt-2">
                                                        @csrf
                                                        <button type="submit" class="text-sm font-medium text-teal-700 underline hover:text-teal-900">Clear this request after checking Ads</button>
                                                    </form>
                                                @else
                                                    <p class="mt-1 text-xs text-slate-600">You can clear this request after five minutes if it still does not appear.</p>
                                                @endif
                                            @endif
                                        @endif
                                    @endif
                                </div>
                                <span class="font-medium {{ $draft->status === 'created' ? 'text-emerald-700' : ($draft->status === 'pending' ? 'text-sky-700' : 'text-amber-800') }}">{{ match ($draft->status) { 'created' => 'Created · paused', 'pending' => 'Creating…', 'uncertain' => 'Check Ads before retrying', default => 'Not created' } }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
            @endif
        @endif

        @if ($tab === 'create' && $connection?->customer_id)
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
        @endif
    </div>
@endsection
