@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-base text-slate-500 sm:text-sm">{{ $website->name }}</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-balance">Plan of action</h1>
            <p class="mt-2 max-w-[56ch] text-base text-pretty text-slate-600 sm:text-sm">A complete six-month plan, written from the website context and search evidence already saved in Sitewell.</p>
        </div>
        <form method="POST" action="{{ route('admin.website-growth-plans.store', $website) }}">
            @csrf
            <button type="submit" @disabled(!$canPreparePlan || $plan?->isGenerating()) class="ui-button ui-button-primary">{{ $plan?->isGenerating() ? 'Generating plan…' : ($plan?->isReady() ? 'Generate a new plan' : 'Generate plan') }}</button>
        </form>
    </header>
    @if(session('status'))<p role="status" class="ui-well p-4 text-emerald-800">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="ui-well p-4 text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @unless($canPreparePlan)
        <p class="ui-well p-4 text-base text-slate-700 sm:text-sm">Activate this website’s client service or add a signed-up customer before generating a plan. <a href="{{ route('admin.websites.section', [$website, 'settings']) }}" class="text-teal-700 underline">Open website settings</a>.</p>
    @endunless
    @if($history->count() > 1)
        <details class="border-b border-slate-950/10 pb-4">
            <summary class="cursor-pointer font-medium">Previous plans</summary>
            <ul role="list" class="mt-3 space-y-2 text-base sm:text-sm">@foreach($history as $version)<li><a href="{{ route('admin.website-growth-plans.edit', [$website, $version]) }}" class="text-teal-700 underline">{{ $version->created_at->format('j M Y, H:i') }} · {{ ucfirst($version->status) }}</a></li>@endforeach</ul>
        </details>
    @endif
    @if($plan?->isGenerating())
        <section class="ui-panel ui-section space-y-3" aria-live="polite">
            <h2 class="text-xl font-semibold text-balance">Your plan is being prepared</h2>
            <p class="max-w-[56ch] text-base text-pretty text-slate-600">We’re bringing together the saved business details, keywords, audit findings and search performance. The report will appear here when it is ready.</p>
            <p class="text-base text-slate-500 sm:text-sm">You can leave this page and come back. Generation continues in the background.</p>
        </section>
    @elseif($plan?->status === \App\Models\WebsiteGrowthPlan::STATUS_FAILED)
        <div role="alert" class="ui-panel ui-section space-y-2"><h2 class="text-xl font-semibold">The plan could not be generated</h2><p class="text-base text-slate-600">{{ $plan->generation_error }} The previous completed reports remain available above.</p></div>
    @elseif($plan?->isReady())
        <section class="ui-panel ui-section space-y-4" aria-label="Share plan">
            <div class="flex flex-wrap gap-3">
                <a href="{{ $plan->shareUrl() }}" target="_blank" rel="noopener noreferrer" class="ui-button ui-button-secondary">Open shareable report</a>
            </div>
            <p class="text-base text-slate-500 sm:text-sm">The protected client link lasts 30 days. Anyone with that link can read this report.</p>
            @if($plan->email_queued_at)
                <p class="text-base text-slate-600 sm:text-sm">Email queued to {{ $plan->email_recipient }} on {{ $plan->email_queued_at->format('j M Y, H:i') }}.</p>
            @elseif($recipients !== [])
                <form method="POST" action="{{ route('admin.website-growth-plans.send', [$website, $plan]) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-0"><label for="recipient" class="ui-label">Send to client</label><select id="recipient" name="recipient" class="ui-input mt-1 w-full">@foreach($recipients as $recipient)<option value="{{ $recipient }}">{{ $recipient }}</option>@endforeach</select></div>
                    <button type="submit" class="ui-button ui-button-secondary">Email plan</button>
                </form>
            @else
                <p class="text-base text-slate-600 sm:text-sm">Add a customer in <a href="{{ route('admin.websites.section', [$website, 'settings']) }}" class="text-teal-700 underline">website settings</a> if you want to email the report.</p>
            @endif
        </section>
        <p class="text-base text-slate-600 sm:text-sm">Your plan is ready. Open the shareable report to view the standalone client document.</p>
    @else
        <section class="ui-panel ui-section space-y-3">
            <h2 class="text-xl font-semibold text-balance">Your website’s next six months</h2>
            <p class="max-w-[56ch] text-base text-pretty text-slate-600">Generate the plan to see where the website stands, what we want it to rank for, and the work that will help it get there. No extra details are needed.</p>
        </section>
    @endif
</div>
@endsection
