    @php
        $canManage = $website->isManageableBy(Auth::user());
        $generation = $seoImpact->generation ?? $seoImpact->contentRequest?->generation;
        $baseline = data_get($seoImpact->baseline, 'target.totals');
        $current = data_get($seoImpact->observations, 'target.totals');
    @endphp
    <div class="space-y-6">
        <header>
            <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact']) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">← All SEO impact</a>
            <p class="mt-4 text-xs font-medium uppercase tracking-wide text-slate-500">{{ str($seoImpact->status)->replace('_', ' ') }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ $seoImpact->title }}</h1>
            <p class="mt-2 text-sm text-slate-600">Observed changes help decide what to do next. They do not prove that an edit caused a result.</p>
        </header>
        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if ($seoImpact->measurement_error)
            <p role="status" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $seoImpact->measurement_error }}</p>
        @endif
        <section class="rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="impact-brief">
            <h2 id="impact-brief" class="font-semibold text-slate-950">1. Measurable brief</h2>
            <p class="mt-2 text-sm text-slate-600">Define the pages, search intent, and expected effect before publishing. The measurement scope is frozen when delivery is confirmed.</p>
            @if ($canManage && $seoImpact->status === 'planned')
                <form method="POST" action="{{ route('admin.seo-impacts.update', [$website, $seoImpact]) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf @method('PUT')
                    <div class="md:col-span-2"><label for="title" class="block text-sm font-medium">Objective</label><input id="title" name="title" required maxlength="200" value="{{ old('title', $seoImpact->title) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                    <div class="md:col-span-2"><label for="hypothesis" class="block text-sm font-medium">What will change, and why should it help?</label><textarea id="hypothesis" name="hypothesis" required maxlength="2000" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('hypothesis', $seoImpact->hypothesis) }}</textarea></div>
                    <div><label for="target_urls" class="block text-sm font-medium">Canonical target URLs · one per line, up to five</label><textarea id="target_urls" name="target_urls" required rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ is_array(old('target_urls')) ? implode("\n", old('target_urls')) : old('target_urls', implode("\n", $seoImpact->target_urls)) }}</textarea></div>
                    <div><label for="target_queries" class="block text-sm font-medium">Exact search terms · one per line, up to ten</label><textarea id="target_queries" name="target_queries" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ is_array(old('target_queries')) ? implode("\n", old('target_queries')) : old('target_queries', implode("\n", $seoImpact->target_queries)) }}</textarea><p class="mt-1 text-xs text-slate-500">Leave empty for all searches to these pages. Choose non-branded terms to measure discovery separately.</p></div>
                    <div><label for="primary_metric" class="block text-sm font-medium">Primary success measure</label><select id="primary_metric" name="primary_metric" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="clicks" @selected(old('primary_metric', $seoImpact->primary_metric) === 'clicks')>Relevant search clicks</option><option value="ctr" @selected(old('primary_metric', $seoImpact->primary_metric) === 'ctr')>Search click-through rate</option></select></div>
                    <div><label for="control_url" class="block text-sm font-medium">Unchanged comparison page (optional)</label><input type="url" id="control_url" name="control_url" value="{{ old('control_url', $seoImpact->control_url) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><p class="mt-1 text-xs text-slate-500">Choose a similar page with similar demand. Comparison uses all of its queries.</p></div>
                    <div><label for="country" class="block text-sm font-medium">Country (optional, three-letter code)</label><input id="country" name="country" maxlength="3" placeholder="gbr" value="{{ old('country', $seoImpact->country) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                    <div><label for="device" class="block text-sm font-medium">Device</label><select id="device" name="device" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">All devices</option>@foreach (['DESKTOP', 'MOBILE', 'TABLET'] as $device)<option value="{{ $device }}" @selected(old('device', $seoImpact->device) === $device)>{{ ucfirst(strtolower($device)) }}</option>@endforeach</select></div>
                    @foreach (['business_value' => 'Business value', 'confidence' => 'Evidence confidence', 'effort' => 'Implementation effort'] as $field => $label)
                        <div><label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@for ($score = 1; $score <= 5; $score++)<option value="{{ $score }}" @selected((int) old($field, $seoImpact->$field) === $score)>{{ $score }}{{ $score === 1 ? ' · Low' : ($score === 5 ? ' · High' : '') }}</option>@endfor</select></div>
                    @endforeach
                    <div class="md:col-span-2"><button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save brief</button></div>
                </form>
            @else
                <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $seoImpact->hypothesis }}</p>
                <dl class="mt-4 space-y-3 text-sm"><div><dt class="font-medium">Target pages</dt><dd class="break-words text-slate-600">{{ implode(', ', $seoImpact->target_urls) ?: 'Not yet specified' }}</dd></div><div><dt class="font-medium">Search terms</dt><dd class="text-slate-600">{{ implode(', ', $seoImpact->target_queries) ?: 'All queries to the target pages' }}</dd></div><div><dt class="font-medium">Success measure</dt><dd class="text-slate-600">{{ strtoupper($seoImpact->primary_metric) }} · {{ $seoImpact->country ?: 'All countries' }} · {{ $seoImpact->device ?: 'All devices' }}</dd></div></dl>
            @endif
            @if ($seoImpact->evidence)
                <details class="mt-4 border-t border-slate-100 pt-4"><summary class="cursor-pointer text-sm font-medium text-slate-700">Original recommendation evidence</summary><pre class="mt-3 overflow-x-auto whitespace-pre-wrap break-words text-xs leading-5 text-slate-600">{{ json_encode($seoImpact->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
            @endif
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="impact-delivery">
            <h2 id="impact-delivery" class="font-semibold text-slate-950">2. Delivery</h2>
            @if ($generation)
                <p class="mt-3 text-sm text-slate-600">Content task: {{ str($generation->status)->replace('_', ' ') }}@if ($generation->merged_at) · Merged {{ $generation->merged_at->format('j M Y') }}@endif</p>
                @if ($canManage && $generation->pull_request_url)<a href="{{ $generation->pull_request_url }}" class="mt-2 inline-block text-sm font-medium underline">Review pull request #{{ $generation->pull_request_number }}</a>@endif
            @endif
            @if ($seoImpact->contentRequest?->optimisations->isNotEmpty())
                <p class="mt-3 text-sm text-slate-600">Linked Pixel changes:</p>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">@foreach ($seoImpact->contentRequest->optimisations as $optimisation)<li>{{ $optimisation->url }} · {{ $optimisation->status->value }}@if ($optimisation->deployed_at) · Deployed {{ $optimisation->deployed_at->format('j M Y') }}@endif</li>@endforeach</ul>
            @endif
            @if ($seoImpact->live_at)
                <p class="mt-3 text-sm font-medium text-slate-900">Manager-confirmed live on {{ $seoImpact->live_at->setTimezone('America/Los_Angeles')->format('j M Y') }}</p>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $seoImpact->actual_changes }}</p>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">Evidence: {{ $seoImpact->deployment_evidence }}</p>
            @elseif ($canManage && $seoImpact->status === 'planned')
                <p class="mt-2 text-sm text-slate-600">Check the live pages and record the actual changes. A merged pull request or prepared Pixel change alone does not confirm delivery. Save any brief changes above first.</p>
                <form method="POST" action="{{ route('admin.seo-impacts.live', [$website, $seoImpact]) }}" class="mt-4 space-y-4">
                    @csrf
                    <div><label for="live_date" class="block text-sm font-medium">Live date (Search Console’s Pacific time)</label><input type="date" id="live_date" name="live_date" required max="{{ now('America/Los_Angeles')->toDateString() }}" value="{{ old('live_date') }}" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                    <div><label for="actual_changes" class="block text-sm font-medium">Actual changes on the target pages</label><textarea id="actual_changes" name="actual_changes" required minlength="10" maxlength="3000" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('actual_changes') }}</textarea></div>
                    <div><label for="deployment_evidence" class="block text-sm font-medium">How did you confirm delivery?</label><textarea id="deployment_evidence" name="deployment_evidence" required minlength="10" maxlength="2000" rows="2" placeholder="Release reference and what you checked on the live pages" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('deployment_evidence') }}</textarea></div>
                    <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="confirmed_live" value="1" required class="mt-1">I checked that these changes are live on the target pages.</label>
                    <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Confirm live and start measuring</button>
                </form>
            @endif
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="impact-results">
            <h2 id="impact-results" class="font-semibold text-slate-950">3. Search results</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">First-party Search Console data, with a reporting delay. The baseline covers the 28 days before delivery; reviews use days 1–28 and 29–56 afterwards. The deployment day is excluded. Query filters omit anonymised searches, and missing data is not treated as zero. Seasonal demand, competitors, and Google updates can also affect results.</p>
            @if ($seoImpact->baseline)
                <p class="mt-3 text-xs text-slate-500">{{ $seoImpact->live_at ? 'Baseline' : 'Provisional baseline · refreshed against the live date later' }}: {{ $seoImpact->baseline['start'] }} – {{ $seoImpact->baseline['end'] }}@if ($seoImpact->observations) · Current window: {{ $seoImpact->observations['start'] }} – {{ $seoImpact->observations['end'] }}@endif</p>
                <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-slate-200"><th class="py-2">Measure</th><th>Before</th><th>Latest window</th></tr></thead><tbody>
                    @foreach (['clicks' => 'Clicks', 'impressions' => 'Impressions', 'ctr' => 'CTR (%)', 'position' => 'Average position', 'reported_days' => 'Days with reported activity'] as $key => $label)
                        <tr class="border-b border-slate-100"><th class="py-3 font-medium">{{ $label }}</th><td class="tabular-nums">{{ isset($baseline[$key]) ? number_format($baseline[$key] * ($key === 'ctr' ? 100 : 1), in_array($key, ['ctr', 'position']) ? 2 : 0) : 'Unavailable' }}</td><td class="tabular-nums">{{ isset($current[$key]) ? number_format($current[$key] * ($key === 'ctr' ? 100 : 1), in_array($key, ['ctr', 'position']) ? 2 : 0) : 'Unavailable' }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @else
                <p class="mt-4 text-sm text-slate-500">Awaiting a saved page scope, connected Search Console property, and final data. Measurement runs daily.</p>
            @endif
            @foreach ($seoImpact->reviews as $review)
                <article class="mt-4 rounded-md bg-slate-50 p-4"><h3 class="text-sm font-semibold">Day {{ $review->checkpoint }} · {{ str($review->outcome)->replace('_', ' ')->ucfirst() }}</h3><p class="mt-1 text-xs text-slate-500">{{ $review->period_start->toDateString() }} – {{ $review->period_end->toDateString() }}</p><p class="mt-2 text-sm text-slate-600">{{ data_get($review->assessment, 'reason') }}</p>@if (data_get($review->assessment, 'percent') !== null)<p class="mt-2 text-sm font-medium">{{ data_get($review->assessment, 'percent') > 0 ? '+' : '' }}{{ data_get($review->assessment, 'percent') }}% {{ strtoupper($seoImpact->primary_metric) }}</p>@endif</article>
            @endforeach
            @if ($seoImpact->status === 'measuring')<p class="mt-4 text-sm text-slate-600">Next checkpoint: day {{ $seoImpact->review_after_days }}. Target pages and search terms remain protected from overlapping content work.</p>@endif
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="impact-decision">
            <h2 id="impact-decision" class="font-semibold text-slate-950">4. Decide what happens next</h2>
            @if ($seoImpact->decision)<p class="mt-3 text-sm font-medium">{{ ucfirst($seoImpact->decision) }}@if ($seoImpact->reviewed_at) · {{ $seoImpact->reviewed_at->format('j M Y') }}@endif</p><p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $seoImpact->decision_notes }}</p>@endif
            @if ($canManage && ! in_array($seoImpact->status, ['completed', 'cancelled']))
                <p class="mt-2 text-sm text-slate-600">Explain the evidence and any external factors. Follow-up choices create a content request for human review. Closing the review releases the measurement protection; it does not change the live website.</p>
                <form method="POST" action="{{ route('admin.seo-impacts.review', [$website, $seoImpact]) }}" class="mt-4 space-y-4">
                    @csrf
                    <div><label for="decision" class="block text-sm font-medium">Next step</label><select id="decision" name="decision" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @if ($seoImpact->live_at)<option value="keep">Keep the change and close review</option><option value="investigate">Queue an investigation</option><option value="iterate">Queue a focused revision</option><option value="rollback">Queue a rollback proposal</option>@endif
                        @if ($seoImpact->status === 'review_required' && $seoImpact->review_after_days < 168)<option value="extend">Measure for another 28 days</option>@endif
                        <option value="cancel">Stop tracking this change</option>
                    </select></div>
                    <div><label for="decision_notes" class="block text-sm font-medium">What did we learn, and what should happen next?</label><textarea id="decision_notes" name="decision_notes" required minlength="10" maxlength="2000" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('decision_notes') }}</textarea></div>
                    <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Save decision</button>
                </form>
            @endif
        </section>
    </div>
