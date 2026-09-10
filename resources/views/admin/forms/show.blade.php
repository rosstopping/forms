@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-2xl font-semibold tracking-tight text-slate-950">{{ $form->name }}</h1><p class="mt-1 text-base text-slate-600 sm:text-sm">Choose where new enquiry notifications should be sent.</p></div>
        <a href="{{ route('admin.websites.section', [$form->website, 'forms']) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-950/15 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to forms</a>
    </header>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-600/20 bg-emerald-50 px-4 py-3 text-base text-emerald-800 sm:text-sm">{{ session('status') }}</div>
    @endif

    <section class="rounded-xl border border-slate-950/10 bg-white p-5 sm:p-6" aria-labelledby="form-overview-title">
        <h2 id="form-overview-title" class="font-semibold text-slate-950">Form overview</h2>
        <dl class="mt-4 grid gap-4 md:grid-cols-3">
            <div><dt class="text-base text-slate-500 sm:text-sm">Website</dt><dd class="mt-1 font-medium text-slate-900">{{ $form->website?->name ?: 'Unknown' }}</dd></div>
            <div><dt class="text-base text-slate-500 sm:text-sm">Status</dt><dd class="mt-1 font-medium text-slate-900">{{ $form->is_active ? 'Active' : 'Disabled' }}</dd></div>
            <div><dt class="text-base text-slate-500 sm:text-sm">Form reference</dt><dd class="mt-1 font-mono text-sm text-slate-700">{{ $form->slug }}</dd></div>
        </dl>
    </section>

    <form method="POST" action="{{ route('admin.forms.update', $form) }}" class="rounded-xl border border-slate-950/10 bg-white p-5 sm:p-6">
        @csrf
        @method('PUT')
        @if (! $canUseAutoresponders)<input type="hidden" name="autoresponder_mode" value="inherit">@endif

        <div>
            <h2 class="font-semibold text-slate-950">Email notifications</h2>
            <p class="mt-1 text-base text-slate-600 sm:text-sm">Send your team an email when this form receives a genuine enquiry.</p>
        </div>

        <label class="mt-4 flex items-start gap-3 rounded-lg bg-slate-50 p-4">
            <input type="checkbox" name="email_enabled_override" value="1" class="mt-0.5 size-5 rounded border-slate-300 text-teal-600 focus:ring-teal-600 sm:size-4" @checked((bool) $form->email_enabled_override)>
            <span><span class="font-medium text-slate-900">Send email notifications</span><span class="mt-1 block text-base text-slate-600 sm:text-sm">Spam submissions will remain visible in Sitewell but will not trigger an email.</span></span>
        </label>

        <div class="mt-4 max-w-xl">
            <label class="text-base font-medium text-slate-700 sm:text-sm" for="email_recipients_override">Recipients</label>
            <textarea id="email_recipients_override" name="email_recipients_override" rows="3" class="mt-1 w-full rounded-lg border border-slate-950/15 px-3 py-2 text-base focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm">{{ old('email_recipients_override', is_array($form->email_recipients_override) ? implode(PHP_EOL, $form->email_recipients_override) : (string) $form->email_recipients_override) }}</textarea>
            <p class="mt-1 text-base text-slate-500 sm:text-sm">Enter one email address per line.</p>
            @error('email_recipients_override')<p class="mt-1 text-base text-red-600 sm:text-sm">{{ $message }}</p>@enderror
        </div>

        <details class="group mt-6 rounded-lg border border-slate-950/10">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-4 py-3 font-medium text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 [&::-webkit-details-marker]:hidden">
                Additional settings
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0 group-open:rotate-180 sm:size-4" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </summary>
            <div class="space-y-6 border-t border-slate-950/10 p-4">
                <div class="max-w-xl"><label class="text-base font-medium text-slate-700 sm:text-sm" for="email_subject_override">Notification subject</label><input id="email_subject_override" type="text" name="email_subject_override" value="{{ old('email_subject_override', $form->email_subject_override) }}" class="mt-1 w-full rounded-lg border border-slate-950/15 px-3 py-2 text-base focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm"></div>

                <section aria-labelledby="webhook-settings-title">
                    <h3 id="webhook-settings-title" class="font-medium text-slate-900">Webhook notifications</h3>
                    <label class="mt-3 flex items-start gap-3"><input type="checkbox" name="webhook_enabled_override" value="1" class="mt-0.5 size-5 rounded border-slate-300 text-teal-600 focus:ring-teal-600 sm:size-4" @checked((bool) $form->webhook_enabled_override)><span class="text-base text-slate-700 sm:text-sm">Send submissions to a webhook.</span></label>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="webhook_url_override">Webhook URL</label><input id="webhook_url_override" type="url" name="webhook_url_override" value="{{ old('webhook_url_override', $form->webhook_url_override) }}" class="mt-1 w-full rounded-lg border border-slate-950/15 px-3 py-2 text-base focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm"></div>
                        <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="webhook_secret_override">Webhook secret</label><input id="webhook_secret_override" type="text" name="webhook_secret_override" value="{{ old('webhook_secret_override', $form->webhook_secret_override) }}" class="mt-1 w-full rounded-lg border border-slate-950/15 px-3 py-2 text-base focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm"></div>
                    </div>
                </section>

                @if ($canUseAutoresponders)
                    <section class="rounded-lg bg-blue-50 p-4" aria-labelledby="form-autoresponder-title">
                        <h3 id="form-autoresponder-title" class="font-medium text-blue-950">Automatic customer reply</h3>
                        <p class="mt-1 text-base text-blue-800 sm:text-sm">Override the website reply for this form only.</p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_mode">Behaviour</label><select id="autoresponder_mode" name="autoresponder_mode" class="mt-1 w-full rounded-lg border border-slate-950/15 px-3 py-2 text-base sm:text-sm"><option value="inherit" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override === null ? 'inherit' : ($form->autoresponder_enabled_override ? 'enabled' : 'disabled')) === 'inherit')>Use website setting</option><option value="enabled" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override ? 'enabled' : null) === 'enabled')>Enabled for this form</option><option value="disabled" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override === false ? 'disabled' : null) === 'disabled')>Disabled for this form</option></select></div>
                            <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_subject_override">Subject override</label><input id="autoresponder_subject_override" name="autoresponder_subject_override" value="{{ old('autoresponder_subject_override', $form->autoresponder_subject_override) }}" placeholder="Leave blank to use the website default" class="mt-1 w-full rounded-lg border border-slate-950/15 px-3 py-2 text-base sm:text-sm"></div>
                        </div>
                        @php($autoresponderContentTypeOverride = old('autoresponder_content_type_override', $form->autoresponder_content_type_override))
                        @php($resolvedAutoresponderContentType = $autoresponderContentTypeOverride ?: ($form->website->autoresponder_content_type ?? 'text'))
                        <div class="mt-4 space-y-4" data-autoresponder-content-editor data-field-name="autoresponder_body_override" data-default-content-type="{{ $form->website->autoresponder_content_type ?? 'text' }}">
                            <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_content_type_override">Message format</label><select id="autoresponder_content_type_override" name="autoresponder_content_type_override" class="mt-1 w-full" data-autoresponder-content-type><option value="" @selected(blank($autoresponderContentTypeOverride))>Use website setting</option><option value="text" @selected($autoresponderContentTypeOverride === 'text')>Text</option><option value="html" @selected($autoresponderContentTypeOverride === 'html')>HTML</option></select></div>
                            <div data-autoresponder-content-panel="text" @if ($resolvedAutoresponderContentType === 'html') hidden @endif><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_body_override">Message override</label><x-trix-editor id="autoresponder_body_override" name="autoresponder_body_override" :value="old('autoresponder_body_override', $form->autoresponder_body_override)" placeholder="Leave blank to use the website default" /></div>
                            <div data-autoresponder-content-panel="html" @if ($resolvedAutoresponderContentType !== 'html') hidden @endif><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_body_override_html">Raw HTML override</label><textarea id="autoresponder_body_override_html" name="autoresponder_body_override" rows="10" class="mt-1 w-full rounded-lg border border-slate-950/15 p-3 font-mono text-base sm:text-sm" placeholder="Leave blank to use the website default">{{ old('autoresponder_body_override', $form->autoresponder_body_override) }}</textarea></div>
                            @error('autoresponder_body_override')<p class="text-base text-red-600 sm:text-sm">{{ $message }}</p>@enderror
                        </div>
                        <div class="mt-4 max-w-xs"><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_delay_minutes_override">Send delay override</label><input id="autoresponder_delay_minutes_override" type="number" min="0" max="10080" name="autoresponder_delay_minutes_override" value="{{ old('autoresponder_delay_minutes_override', $form->autoresponder_delay_minutes_override) }}" placeholder="Use website delay" class="mt-1 w-full rounded-lg border border-slate-950/15 px-3 py-2 text-base sm:text-sm"></div>
                    </section>
                @else
                    <section class="rounded-lg bg-slate-50 p-4" aria-labelledby="form-autoresponder-locked-title"><h3 id="form-autoresponder-locked-title" class="font-medium text-slate-900">Automatic customer reply</h3><p class="mt-1 text-base text-slate-600 sm:text-sm">Available with an active Sitewell plan.</p></section>
                @endif
            </div>
        </details>

        <div class="mt-6 flex justify-end"><button type="submit" class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Save settings</button></div>
    </form>
</div>
@endsection
