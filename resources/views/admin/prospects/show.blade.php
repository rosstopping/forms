@extends('layouts.app')

@section('content')
<div class="prospect-workspace min-w-0 space-y-6">
    <header class="space-y-4">
        <p class="text-base sm:text-sm"><a href="{{ route('admin.prospects.index') }}" class="font-medium text-slate-500 hover:text-teal-800">← Outreach</a></p>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-2">
                <h1 class="break-words text-3xl font-semibold tracking-tight text-balance text-slate-950">{{ $prospect->business_name }}</h1>
                <div class="flex flex-wrap items-center gap-3 text-base text-slate-500 sm:text-sm">
                    <p>{{ $prospect->contact_name ?: 'No contact name' }}</p>
                    @if ($prospect->email)<p class="break-all"><a href="mailto:{{ $prospect->email }}" class="hover:text-teal-800">{{ $prospect->email }}</a></p>@endif
                    @if ($prospect->website_url)<p class="break-all"><a href="{{ $prospect->website_url }}" target="_blank" rel="noopener noreferrer" class="text-teal-700 hover:underline">{{ parse_url($prospect->website_url, PHP_URL_HOST) ?: $prospect->website_url }} ↗</a></p>@endif
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2 text-base sm:text-sm">
                <p class="rounded-md bg-white px-2.5 py-1 font-medium text-slate-700 ring-1 ring-slate-950/10">{{ str($prospect->status)->replace('_', ' ')->title() }}</p>
                @if ($prospect->status !== 'converted')
                <p @class(['rounded-md px-2.5 py-1 font-medium', 'bg-rose-50 text-rose-800' => $prospect->lead_temperature === 'hot', 'bg-amber-50 text-amber-800' => $prospect->lead_temperature === 'warm', 'bg-slate-100 text-slate-700' => $prospect->lead_temperature === 'cold'])>{{ ucfirst($prospect->lead_temperature) }} lead</p>
                @endif
            </div>
        </div>
        <div class="@container border-y border-slate-950/10 py-4">
            <dl class="grid grid-cols-2 gap-4 @2xl:grid-cols-4">
                <div class="min-w-0"><dt class="truncate text-base font-medium text-slate-800 sm:text-sm">Lifecycle</dt><dd class="mt-1 text-base text-slate-500 sm:text-sm">{{ str($prospect->outreachState->lifecycle_state->value)->headline() }}</dd></div>
                <div class="min-w-0"><dt class="truncate text-base font-medium text-slate-800 sm:text-sm">Automation</dt><dd class="mt-1 text-base text-slate-500 sm:text-sm">{{ ucfirst($prospect->outreachState->automation_status->value) }}</dd></div>
                <div class="min-w-0"><dt class="truncate text-base font-medium text-slate-800 sm:text-sm">Engagement score</dt><dd class="mt-1 text-base text-slate-500 tabular-nums sm:text-sm">{{ $prospect->outreachState->engagement_score }} points</dd></div>
                <div class="min-w-0"><dt class="truncate text-base font-medium text-slate-800 sm:text-sm">Last email</dt><dd class="mt-1 text-base text-slate-500 tabular-nums sm:text-sm">{{ $prospect->outreachState->last_outreach_at?->setTimezone('Europe/London')->format('j M, H:i') ?? 'Not sent' }}{{ $prospect->outreachState->last_outreach_at ? ' UK' : '' }}</dd></div>
            </dl>
        </div>
    </header>

    @if (session('status'))<div role="status" class="rounded-lg bg-emerald-50 p-4 text-base text-emerald-800 sm:text-sm">{{ session('status') }}</div>@endif
    @if ($prospect->unsubscribed_at)
        <p role="status" class="ui-well p-4 text-base text-slate-700 sm:text-sm"><strong>Unsubscribed</strong> on {{ $prospect->unsubscribed_at->setTimezone('Europe/London')->format('j M Y, H:i') }} UK time. All outreach is stopped; pending emails and follow-ups were cancelled.</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="rounded-lg bg-red-50 p-4 text-base text-red-800 sm:text-sm"><p class="font-medium">Please check the following.</p><ul role="list" class="mt-2 space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if ($prospect->analysis_status === 'failed')<p role="alert" class="rounded-lg bg-red-50 p-4 text-base text-red-800 sm:text-sm"><strong>Research failed:</strong> {{ $prospect->analysis_error }}</p>@endif

    <nav class="ui-tabs" aria-label="Prospect sections">
        @foreach (['emails' => 'Emails & schedule', 'details' => 'Prospect & research', 'activity' => 'Activity', 'controls' => 'Controls'] as $key => $label)
            <a href="{{ route('admin.prospects.show', [$prospect, 'section' => $key]) }}" class="ui-tab" @if ($prospectSection === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($prospectSection === 'emails')
        @if ($isFreeSiteAudit)
            <section class="ui-panel ui-section">
                <h2 class="text-lg font-semibold text-balance">Free audit results email</h2>
                @if ($prospect->activities->contains('type', 'free_audit_email_sent'))
                    <p class="mt-2 text-base text-slate-600 sm:text-sm">The audit results were emailed automatically to {{ $prospect->email }}.</p>
                @elseif ($prospect->activities->contains('type', 'free_audit_email_failed'))
                    <p class="mt-2 text-base text-red-700 sm:text-sm">The audit completed, but the results email could not be delivered. Check the queue failure and mail configuration.</p>
                @else
                    <p class="mt-2 text-base text-slate-600 sm:text-sm">The audit is complete and its results email is queued for automatic delivery. No approval or outreach action is required.</p>
                @endif
            </section>
        @else
            <section class="rounded-xl bg-teal-50 p-5 sm:p-6" aria-labelledby="next-email-heading">
                <p class="text-base font-medium text-teal-700 sm:text-sm">Next email</p>
                <h2 id="next-email-heading" class="mt-1 text-xl font-semibold text-balance text-teal-950">{{ $outreachPlan['next'] }}</h2>
                <p class="mt-2 max-w-[85ch] text-base/6 text-pretty text-teal-800 sm:text-sm/6">{{ $outreachPlan['reason'] }}</p>
                @if ($prospect->outreachState->manual_follow_up_required_at)
                    <div class="mt-4 border-t border-teal-950/10 pt-4">
                        <h3 class="font-semibold text-balance text-teal-950">Follow up directly</h3>
                        <ul role="list" class="mt-2 space-y-1 text-base text-teal-800 sm:text-sm">@foreach ($prospect->outreachState->manual_follow_up_reason ?? [] as $reason)<li>{{ $reason['label'] }}</li>@endforeach</ul>
                    </div>
                @endif
            </section>
            <div class="grid items-start gap-6 xl:grid-cols-[3fr_2fr]">
                <div class="min-w-0 space-y-5">
                    @if ($needsPersonalisedVideo) @include('admin.prospects.partials.video-email') @endif
                    @include('admin.prospects.partials.initial-email')
                    <p class="text-base/6 text-pretty text-slate-500 sm:text-sm/6">Test emails go only to you and do not count towards lead engagement. Times on this page are UK time.</p>
                </div>
                <div class="min-w-0">@include('admin.prospects.partials.sequence')</div>
            </div>
        @endif
    @elseif ($prospectSection === 'details')
        <div class="grid items-start gap-6 xl:grid-cols-2">@include('admin.prospects.partials.details')</div>
    @elseif ($prospectSection === 'activity')
        <div class="space-y-6">@include('admin.prospects.partials.activity')</div>
    @else
        @include('admin.prospects.partials.controls')
        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-950/10 pt-5">
            <div><h2 class="font-semibold text-balance">Delete prospect</h2><p class="mt-1 text-base text-slate-500 sm:text-sm">Moves this prospect to Deleted and cancels pending outreach.</p></div>
            <form method="POST" action="{{ route('admin.prospects.destroy', $prospect) }}" data-confirm-action-form>@csrf @method('DELETE')<button type="button" data-confirm-action data-confirm-title="Delete this prospect?" data-confirm-message="This prospect will move to Deleted and pending outreach will be cancelled. You can still find the record using the Deleted filter." data-confirm-label="Delete prospect" data-confirm-danger class="ui-button ui-button-danger">Delete</button></form>
        </div>
    @endif

    <dialog data-confirm-action-dialog class="m-auto w-[min(30rem,calc(100%-2rem))] rounded-xl border border-slate-950/10 bg-white p-0 backdrop:bg-slate-950/50">
        <div class="p-6"><h2 data-confirm-action-title class="text-lg font-semibold text-balance">Confirm action</h2><p data-confirm-action-message class="mt-2 text-base/6 text-slate-600 sm:text-sm/6"></p><div class="mt-6 flex justify-end gap-3"><button type="button" data-confirm-action-cancel class="ui-button ui-button-secondary">Cancel</button><button type="button" data-confirm-action-submit class="ui-button ui-button-primary">Confirm</button></div></div>
    </dialog>
</div>
@endsection
