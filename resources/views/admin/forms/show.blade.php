@extends('layouts.app')

@section('content')
@php
    $canManageForm = $form->website->isManageableBy(Auth::user());
    $formSections = ['notifications' => 'Team notifications', 'reply' => 'Customer reply', 'setup' => 'Setup check'];
    $formSection = request('form_section', 'notifications');
    $formSection = is_string($formSection) && isset($formSections[$formSection]) ? $formSection : 'notifications';
    if (collect(array_keys($errors->getMessages()))->contains(fn ($key) => str_starts_with($key, 'autoresponder_'))) {
        $formSection = 'reply';
    } elseif ($errors->any()) {
        $formSection = 'notifications';
    }
    $hasReplyOverrides = collect(['autoresponder_subject_override', 'autoresponder_body_override', 'autoresponder_content_type_override', 'autoresponder_delay_minutes_override', 'autoresponder_reply_to_email_override'])->contains(fn ($field) => $form->{$field} !== null && $form->{$field} !== '');
    $effectiveReplyTo = $form->autoresponder_reply_to_email_override ?: $form->website->autoresponder_reply_to_email ?: config('forms.autoresponder_from_address');
@endphp
<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0"><p class="text-base text-slate-500 sm:text-sm">{{ $form->website->name }} / Forms</p><h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ $form->name }}</h1><p class="mt-2 text-base text-slate-500 sm:text-sm">Settings on this page apply only to this form.</p></div>
        <div class="text-sm"><a href="{{ route('admin.websites.section', [$form->website, 'forms']) }}" class="inline-flex rounded-lg bg-slate-100 px-3 py-2 font-medium text-slate-700 hover:bg-slate-200">Back to forms</a></div>
    </header>
    @if (session('status'))<p class="rounded-xl bg-teal-50 p-4 text-base text-teal-900 sm:text-sm">{{ session('status') }}</p>@endif
    <div class="flex flex-wrap gap-x-6 gap-y-2 border-y border-slate-950/10 py-3 text-sm text-slate-500"><p>Status: <strong class="font-medium text-slate-800">{{ $form->is_active ? 'Active' : 'Disabled' }}</strong></p><p>Form reference: <span class="font-mono text-slate-700">{{ $form->slug }}</span></p><p>Last submission: {{ $form->last_submission_at?->diffForHumans() ?: 'None received' }}</p></div>
    <nav class="overflow-x-auto" aria-label="Form settings sections"><div class="flex min-w-max gap-1 text-sm">
        @foreach ($formSections as $key => $label)<a id="form-tab-{{ $key }}" href="{{ route('admin.forms.show', [$form, 'form_section' => $key]) }}" @if ($formSection === $key) aria-current="page" @endif @class(['rounded-lg px-3 py-2 font-medium', 'bg-teal-50 text-teal-900' => $formSection === $key, 'text-slate-500 hover:bg-slate-50' => $formSection !== $key])>{{ $label }}</a>@endforeach
    </div></nav>
    <form id="form-settings" method="POST" action="{{ route('admin.forms.update', $form) }}" @if ($formSection === 'setup') hidden @endif>
        @csrf @method('PUT')
        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-medium">Check these settings before saving.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <input type="hidden" name="form_section" value="{{ $formSection }}">
        @if (! $canUseAutoresponders)<input type="hidden" name="autoresponder_mode" value="inherit">@endif
        <fieldset @disabled(! $canManageForm) class="space-y-5">
            <div id="form-section-notifications" class="rounded-2xl bg-white p-5 ring-1 ring-slate-200/70 sm:p-6" role="region" aria-labelledby="form-tab-notifications" @if ($formSection !== 'notifications') hidden @endif>
        <div>
            <h2 class="font-semibold text-slate-950">Email notifications</h2>
            <p class="mt-1 text-base text-slate-600 sm:text-sm">Send your team an email when this form receives a genuine enquiry.</p>
        </div>

        <label class="mt-4 flex items-start gap-3 rounded-lg bg-slate-50 p-4">
            <input type="hidden" name="email_enabled_override" value="0"><input type="checkbox" name="email_enabled_override" value="1" class="mt-0.5 size-5 rounded border-slate-300 text-teal-600 focus:ring-teal-600 sm:size-4" @checked((bool) old('email_enabled_override', $form->email_enabled_override))>
            <span><span class="font-medium text-slate-900">Send email notifications</span><span class="mt-1 block text-base text-slate-600 sm:text-sm">Spam submissions will remain visible in Sitewell but will not trigger an email.</span></span>
        </label>

        <div class="mt-4 max-w-xl">
            <label class="text-base font-medium text-slate-700 sm:text-sm" for="email_recipients_override">Recipients</label>
            <textarea id="email_recipients_override" name="email_recipients_override" rows="3" class="mt-1 w-full rounded-xl border-0 ring-slate-200 px-3 py-2 text-base focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm">{{ old('email_recipients_override', is_array($form->email_recipients_override) ? implode(PHP_EOL, $form->email_recipients_override) : (string) $form->email_recipients_override) }}</textarea>
            <p class="mt-1 text-base text-slate-500 sm:text-sm">Enter one email address per line.</p>
            @error('email_recipients_override')<p class="mt-1 text-base text-red-600 sm:text-sm">{{ $message }}</p>@enderror
        </div>


                <p class="mt-4 rounded-lg bg-teal-50 p-3 text-base text-teal-900 sm:text-sm">Replying to a team notification emails the customer who submitted the enquiry. To change where customers reply to their acknowledgement, open Customer reply.</p>
                <details class="mt-5 rounded-xl bg-slate-50 p-4" @if ($errors->has('email_subject_override') || $errors->has('webhook_url_override') || $errors->has('webhook_secret_override')) open @endif><summary class="cursor-pointer font-medium text-slate-800">Notification subject and webhooks</summary><div class="mt-4 space-y-5">
                <div class="max-w-xl"><label class="text-base font-medium text-slate-700 sm:text-sm" for="email_subject_override">Notification subject</label><input id="email_subject_override" type="text" name="email_subject_override" value="{{ old('email_subject_override', $form->email_subject_override) }}" class="mt-1 w-full rounded-xl border-0 ring-slate-200 px-3 py-2 text-base focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm"></div>

                <section aria-labelledby="webhook-settings-title">
                    <h3 id="webhook-settings-title" class="font-medium text-slate-900">Webhook notifications</h3>
                    <label class="mt-3 flex items-start gap-3"><input type="hidden" name="webhook_enabled_override" value="0"><input type="checkbox" name="webhook_enabled_override" value="1" class="mt-0.5 size-5 rounded border-slate-300 text-teal-600 focus:ring-teal-600 sm:size-4" @checked((bool) old('webhook_enabled_override', $form->webhook_enabled_override))><span class="text-base text-slate-700 sm:text-sm">Send submissions to a webhook.</span></label>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="webhook_url_override">Webhook URL</label><input id="webhook_url_override" type="url" name="webhook_url_override" value="{{ old('webhook_url_override', $form->webhook_url_override) }}" class="mt-1 w-full rounded-xl border-0 ring-slate-200 px-3 py-2 text-base focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm"></div>
                        <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="webhook_secret_override">Webhook secret</label><input id="webhook_secret_override" type="text" name="webhook_secret_override" value="{{ old('webhook_secret_override', $form->webhook_secret_override) }}" class="mt-1 w-full rounded-xl border-0 ring-slate-200 px-3 py-2 text-base focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm"></div>
                    </div>
                </section>

                </div></details>
            </div>
            <div id="form-section-reply" class="space-y-4" role="region" aria-labelledby="form-tab-reply" @if ($formSection !== 'reply') hidden @endif>
                <div class="rounded-xl bg-slate-50 p-4">
                    <h2 class="font-semibold text-slate-900">Current customer reply</h2>
                    <p class="mt-2 text-base text-slate-600 sm:text-sm">{{ $canUseAutoresponders && ($form->autoresponder_enabled_override ?? $form->website->autoresponder_enabled) ? 'Enabled' : 'Off' }} · {{ $hasReplyOverrides ? 'Some fields are customised for this form.' : 'Using the website’s default message and reply-to address.' }}</p>
                    <p class="mt-2 break-words text-base text-slate-600 sm:text-sm">Customer replies go to: <strong class="font-medium text-slate-800">{{ $effectiveReplyTo }}</strong></p>
                    <p class="mt-3 text-sm"><a href="{{ route('admin.websites.section', [$form->website, 'forms', 'forms_section' => 'defaults']) }}" class="font-medium text-teal-700 underline">{{ $canManageForm ? 'Edit website defaults' : 'View website defaults' }}</a> · Applies to forms without their own custom settings.</p>
                </div>
                @if ($canUseAutoresponders)
                    <section class="rounded-lg bg-teal-50/40 p-4" aria-labelledby="form-autoresponder-title">
                        <h3 id="form-autoresponder-title" class="font-medium text-teal-950">Automatic customer reply</h3>
                        <p class="mt-1 text-base text-teal-800 sm:text-sm">Use the website sending setting, switch this form on or off, or customise the message below. Blank fields use the website defaults.</p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_mode">Behaviour</label><select id="autoresponder_mode" name="autoresponder_mode" class="mt-1 w-full rounded-xl border-0 ring-slate-200 px-3 py-2 text-base sm:text-sm"><option value="inherit" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override === null ? 'inherit' : ($form->autoresponder_enabled_override ? 'enabled' : 'disabled')) === 'inherit')>Use website on/off setting</option><option value="enabled" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override ? 'enabled' : null) === 'enabled')>Enabled for this form</option><option value="disabled" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override === false ? 'disabled' : null) === 'disabled')>Disabled for this form</option></select></div>

                        </div>
                        <details class="mt-5 rounded-xl bg-white p-4 ring-1 ring-slate-200/70" @if ($hasReplyOverrides || collect(array_keys($errors->getMessages()))->contains(fn ($key) => str_starts_with($key, 'autoresponder_'))) open @endif>
                            <summary class="cursor-pointer font-medium text-slate-900">Customise the reply for this form</summary>
                            <p class="mt-2 text-base text-slate-500 sm:text-sm">Leave individual fields blank to use the website default. Clear a custom field and save to inherit it again.</p>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_subject_override">Subject override</label><input id="autoresponder_subject_override" name="autoresponder_subject_override" value="{{ old('autoresponder_subject_override', $form->autoresponder_subject_override) }}" placeholder="Leave blank to use the website default" class="mt-1 w-full rounded-xl border-0 ring-slate-200 px-3 py-2 text-base sm:text-sm"></div>
                                <div><label for="autoresponder_reply_to_email_override">Reply-to email override</label><input id="autoresponder_reply_to_email_override" name="autoresponder_reply_to_email_override" type="email" maxlength="255" value="{{ old('autoresponder_reply_to_email_override', $form->autoresponder_reply_to_email_override) }}" placeholder="{{ $form->website->autoresponder_reply_to_email ?: 'Use website default' }}" class="mt-2 w-full border-0 ring-slate-200"><p class="mt-2 text-base text-slate-500 sm:text-sm">Where this customer’s replies should go.</p>@error('autoresponder_reply_to_email_override')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                            </div>
                        @php
                            $autoresponderContentTypeOverride = old('autoresponder_content_type_override', $form->autoresponder_content_type_override);
                        @endphp
                        @php
                            $resolvedAutoresponderContentType = $autoresponderContentTypeOverride ?: ($form->website->autoresponder_content_type ?? 'text');
                        @endphp
                        <div class="mt-4 space-y-4" data-autoresponder-content-editor data-field-name="autoresponder_body_override" data-default-content-type="{{ $form->website->autoresponder_content_type ?? 'text' }}">
                            <div><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_content_type_override">Message format</label><select id="autoresponder_content_type_override" name="autoresponder_content_type_override" class="mt-1 w-full" data-autoresponder-content-type><option value="" @selected(blank($autoresponderContentTypeOverride))>Use website setting</option><option value="text" @selected($autoresponderContentTypeOverride === 'text')>Text</option><option value="html" @selected($autoresponderContentTypeOverride === 'html')>HTML</option></select></div>
                            <div data-autoresponder-content-panel="text" @if ($resolvedAutoresponderContentType === 'html') hidden @endif><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_body_override">Message override</label><x-trix-editor id="autoresponder_body_override" name="autoresponder_body_override" :value="old('autoresponder_body_override', $form->autoresponder_body_override)" placeholder="Leave blank to use the website default" /></div>
                            <div data-autoresponder-content-panel="html" @if ($resolvedAutoresponderContentType !== 'html') hidden @endif><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_body_override_html">Raw HTML override</label><textarea id="autoresponder_body_override_html" name="autoresponder_body_override" rows="10" class="mt-1 w-full rounded-lg border border-slate-950/15 p-3 font-mono text-base sm:text-sm" placeholder="Leave blank to use the website default">{{ old('autoresponder_body_override', $form->autoresponder_body_override) }}</textarea></div>
                            @error('autoresponder_body_override')<p class="text-base text-red-600 sm:text-sm">{{ $message }}</p>@enderror
                        </div>
                        <div class="mt-4 max-w-xs"><label class="text-base font-medium text-slate-700 sm:text-sm" for="autoresponder_delay_minutes_override">Send delay override</label><input id="autoresponder_delay_minutes_override" type="number" min="0" max="10080" name="autoresponder_delay_minutes_override" value="{{ old('autoresponder_delay_minutes_override', $form->autoresponder_delay_minutes_override) }}" placeholder="Use website delay" class="mt-1 w-full rounded-xl border-0 ring-slate-200 px-3 py-2 text-base sm:text-sm"></div>
                        </details>
                    </section>
                @else
                    <section class="rounded-lg bg-slate-50 p-4" aria-labelledby="form-autoresponder-locked-title"><h3 id="form-autoresponder-locked-title" class="font-medium text-slate-900">Automatic customer reply</h3><p class="mt-1 text-base text-slate-600 sm:text-sm">Available with an active Sitewell plan.</p></section>
                @endif
            </div>
            @if ($canManageForm)<div class="flex justify-end"><button type="submit" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800 focus-visible:outline-teal-600">Save settings</button></div>@endif
        </fieldset>
    </form>
    <div id="form-section-setup" role="region" aria-labelledby="form-tab-setup" @if ($formSection !== 'setup') hidden @endif>
    <section class="rounded-xl border border-slate-950/10 bg-white p-5 sm:p-6" aria-labelledby="form-setup-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="form-setup-title" class="font-semibold text-slate-950">Form setup check</h2>
                <p class="mt-1 text-sm text-slate-600">Check saved configuration without creating a lead or sending email or webhooks. This does not test the live form or prove delivery.</p>
                <p class="mt-2 text-xs text-slate-500">Last checked: @if ($form->setup_checked_at)<time datetime="{{ $form->setup_checked_at->toIso8601String() }}">{{ $form->setup_checked_at->format('j M Y, H:i') }} {{ config('app.timezone') }}</time>. Run again after changing settings.@else Never.@endif</p>
            </div>
            @if ($form->website->isManageableBy(Auth::user()))
                <form method="POST" action="{{ route('admin.forms.setup-check', $form) }}" class="shrink-0">
                    @csrf
                    <input type="hidden" name="form_section" value="setup">
                    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Check form setup</button>
                </form>
            @endif
        </div>
        @if ($form->setup_check_results)
            @php
                $needsAttention = collect($form->setup_check_results)->contains('status', 'needs_attention');
            @endphp
            <p @class(['mt-4 font-semibold', 'text-amber-800' => $needsAttention, 'text-teal-800' => ! $needsAttention])>{{ $needsAttention ? 'Needs attention' : 'Configuration checks passed' }}</p>
            <div class="mt-3 divide-y divide-slate-100">
                @foreach ($form->setup_check_results as $check)
                    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0"><p class="text-sm font-medium text-slate-900">{{ $check['label'] }}</p><p class="mt-1 text-sm text-slate-600">{{ $check['message'] }}</p>
                            @if ($check['status'] === 'needs_attention')
                                @php
                                    $checkUrl = match ($check['action']) {
                                        'settings', 'search' => \App\Support\WebsiteNavigation::routeFor($form->website, $check['action']),
                                        'support' => route('marketing.contact'),
                                        default => route('admin.forms.show', [$form, 'form_section' => 'notifications']),
                                    };
                                @endphp
                                <a href="{{ $checkUrl }}" class="mt-2 inline-flex text-sm font-semibold text-teal-700 hover:text-teal-900">{{ $check['action'] === 'support' ? 'Contact support' : 'Review settings' }} →</a>
                            @endif
                        </div>
                        <span @class(['shrink-0 text-xs font-semibold', 'text-amber-800' => $check['status'] === 'needs_attention', 'text-teal-800' => $check['status'] === 'passed'])>{{ $check['status'] === 'passed' ? 'Passed' : 'Needs attention' }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

        <p class="mt-4 text-base text-slate-600 sm:text-sm">Adding this form to your website? <a href="{{ route('admin.websites.section', [$form->website, 'forms', 'forms_section' => 'installation']) }}" class="font-medium text-teal-700 underline">View installation instructions</a>. Use <strong class="font-medium">{{ $form->name }}</strong> as the form name.</p>
    </div>
</div>
@endsection
