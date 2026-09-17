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
        <div class="text-sm"><a href="{{ route('admin.websites.section', [$form->website, 'forms']) }}" class="ui-button ui-button-secondary">Back to forms</a></div>
    </header>
    @if (session('status'))<p class="rounded-xl bg-teal-50 p-4 text-base text-teal-900 sm:text-sm">{{ session('status') }}</p>@endif
    <div class="flex flex-wrap gap-x-6 gap-y-2 border-y border-slate-950/10 py-3 text-sm text-slate-500"><p>Status: <strong class="font-medium text-slate-800">{{ $form->is_active ? 'Active' : 'Disabled' }}</strong></p><p>Form reference: <span class="font-mono text-slate-700">{{ $form->slug }}</span></p><p>Last submission: {{ $form->last_submission_at?->diffForHumans() ?: 'None received' }}</p></div>
    <nav class="overflow-x-auto" aria-label="Form settings sections"><div class="ui-tabs">
        @foreach ($formSections as $key => $label)<a id="form-tab-{{ $key }}" href="{{ route('admin.forms.show', [$form, 'form_section' => $key]) }}" @if ($formSection === $key) aria-current="page" @endif class="ui-tab">{{ $label }}</a>@endforeach
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
            <div id="form-section-notifications" class="ui-panel ui-section" role="region" aria-labelledby="form-tab-notifications" @if ($formSection !== 'notifications') hidden @endif>
        <div>
            <h2 class="font-semibold text-slate-950">Email notifications</h2>
            <p class="mt-1 text-base text-slate-600 sm:text-sm">Send your team an email when this form receives a genuine enquiry.</p>
        </div>

        <label class="ui-label mt-4 flex items-start gap-3 rounded-lg bg-slate-50 p-4">
            <input type="hidden" name="email_enabled_override" value="0"><input type="checkbox" name="email_enabled_override" value="1" class="mt-0.5 size-5 sm:size-4" @checked((bool) old('email_enabled_override', $form->email_enabled_override))>
            <span><span class="font-medium text-slate-900">Send email notifications</span><span class="mt-1 block text-base text-slate-600 sm:text-sm">Spam submissions will remain visible in Sitewell but will not trigger an email.</span></span>
        </label>

        <div class="mt-4 max-w-xl">
            <label class="ui-label" for="email_recipients_override">Recipients</label>
            <textarea id="email_recipients_override" name="email_recipients_override" rows="3" class="ui-input mt-1 w-full">{{ old('email_recipients_override', is_array($form->email_recipients_override) ? implode(PHP_EOL, $form->email_recipients_override) : (string) $form->email_recipients_override) }}</textarea>
            <p class="mt-1 text-base text-slate-500 sm:text-sm">Enter one email address per line.</p>
            @error('email_recipients_override')<p class="mt-1 text-base text-red-600 sm:text-sm">{{ $message }}</p>@enderror
        </div>


                <p class="mt-4 rounded-lg bg-teal-50 p-3 text-base text-teal-900 sm:text-sm">Replying to a team notification emails the customer who submitted the enquiry. To change where customers reply to their acknowledgement, open Customer reply.</p>
                <details class="ui-well mt-5 p-4" @if ($errors->has('email_subject_override') || $errors->has('webhook_url_override') || $errors->has('webhook_secret_override')) open @endif><summary class="cursor-pointer font-medium text-slate-800">Notification subject and webhooks</summary><div class="mt-4 space-y-5">
                <div class="max-w-xl"><label class="ui-label" for="email_subject_override">Notification subject</label><input id="email_subject_override" type="text" name="email_subject_override" value="{{ old('email_subject_override', $form->email_subject_override) }}" class="ui-input mt-1 w-full"></div>

                <section aria-labelledby="webhook-settings-title">
                    <h3 id="webhook-settings-title" class="font-medium text-slate-900">Webhook notifications</h3>
                    <label class="ui-label mt-3 flex items-start gap-3"><input type="hidden" name="webhook_enabled_override" value="0"><input type="checkbox" name="webhook_enabled_override" value="1" class="mt-0.5 size-5 sm:size-4" @checked((bool) old('webhook_enabled_override', $form->webhook_enabled_override))><span class="text-base text-slate-700 sm:text-sm">Send submissions to a webhook.</span></label>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div><label class="ui-label" for="webhook_url_override">Webhook URL</label><input id="webhook_url_override" type="url" name="webhook_url_override" value="{{ old('webhook_url_override', $form->webhook_url_override) }}" class="ui-input mt-1 w-full"></div>
                        <div><label class="ui-label" for="webhook_secret_override">Webhook secret</label><input id="webhook_secret_override" type="text" name="webhook_secret_override" value="{{ old('webhook_secret_override', $form->webhook_secret_override) }}" class="ui-input mt-1 w-full"></div>
                    </div>
                </section>

                </div></details>
            </div>
            <div id="form-section-reply" class="space-y-4" role="region" aria-labelledby="form-tab-reply" @if ($formSection !== 'reply') hidden @endif>
                <div class="ui-well p-4">
                    <h2 class="font-semibold text-slate-900">Current customer reply</h2>
                    <p class="mt-2 text-base text-slate-600 sm:text-sm">{{ $canUseAutoresponders && ($form->autoresponder_enabled_override ?? $form->website->autoresponder_enabled) ? 'Enabled' : 'Off' }} · {{ $hasReplyOverrides ? 'Some fields are customised for this form.' : 'Using the website’s default message and reply-to address.' }}</p>
                    <p class="mt-2 break-words text-base text-slate-600 sm:text-sm">Customer replies go to: <strong class="font-medium text-slate-800">{{ $effectiveReplyTo }}</strong></p>
                    <p class="mt-3 text-base sm:text-sm"><a href="{{ route('admin.websites.section', [$form->website, 'forms', 'forms_section' => 'defaults']) }}" class="font-medium text-teal-700 underline">{{ $canManageForm ? 'Edit website defaults' : 'View website defaults' }}</a> · Applies to forms without their own custom settings.</p>
                </div>
                @if ($canUseAutoresponders)
                    <section class="rounded-lg bg-teal-50/40 p-4" aria-labelledby="form-autoresponder-title">
                        <h3 id="form-autoresponder-title" class="font-medium text-teal-950">Automatic customer reply</h3>
                        <p class="mt-1 text-base text-teal-800 sm:text-sm">Use the website sending setting, switch this form on or off, or customise the message below. Blank fields use the website defaults.</p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div><label class="ui-label" for="autoresponder_mode">Behaviour</label><select id="autoresponder_mode" name="autoresponder_mode" class="ui-input mt-1 w-full"><option value="inherit" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override === null ? 'inherit' : ($form->autoresponder_enabled_override ? 'enabled' : 'disabled')) === 'inherit')>Use website on/off setting</option><option value="enabled" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override ? 'enabled' : null) === 'enabled')>Enabled for this form</option><option value="disabled" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override === false ? 'disabled' : null) === 'disabled')>Disabled for this form</option></select></div>

                        </div>
                        <details class="ui-panel mt-5 p-4 ring-slate-200/70" @if ($hasReplyOverrides || collect(array_keys($errors->getMessages()))->contains(fn ($key) => str_starts_with($key, 'autoresponder_'))) open @endif>
                            <summary class="cursor-pointer font-medium text-slate-900">Customise the reply for this form</summary>
                            <p class="mt-2 text-base text-slate-500 sm:text-sm">Leave individual fields blank to use the website default. Clear a custom field and save to inherit it again.</p>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div><label class="ui-label" for="autoresponder_subject_override">Subject override</label><input id="autoresponder_subject_override" name="autoresponder_subject_override" value="{{ old('autoresponder_subject_override', $form->autoresponder_subject_override) }}" placeholder="Leave blank to use the website default" class="ui-input mt-1 w-full"></div>
                                <div><label for="autoresponder_reply_to_email_override">Reply-to email override</label><input id="autoresponder_reply_to_email_override" name="autoresponder_reply_to_email_override" type="email" maxlength="255" value="{{ old('autoresponder_reply_to_email_override', $form->autoresponder_reply_to_email_override) }}" placeholder="{{ $form->website->autoresponder_reply_to_email ?: 'Use website default' }}" class="ui-input mt-2 w-full"><p class="mt-2 text-base text-slate-500 sm:text-sm">Where this customer’s replies should go.</p>@error('autoresponder_reply_to_email_override')<p class="mt-2 text-rose-700 text-base sm:text-sm">{{ $message }}</p>@enderror</div>
                            </div>
                        @php
                            $autoresponderContentTypeOverride = old('autoresponder_content_type_override', $form->autoresponder_content_type_override);
                        @endphp
                        @php
                            $resolvedAutoresponderContentType = $autoresponderContentTypeOverride ?: ($form->website->autoresponder_content_type ?? 'text');
                        @endphp
                        <div class="mt-4 space-y-4" data-autoresponder-content-editor data-field-name="autoresponder_body_override" data-default-content-type="{{ $form->website->autoresponder_content_type ?? 'text' }}">
                            <div><label class="ui-label" for="autoresponder_content_type_override">Message format</label><select id="autoresponder_content_type_override" name="autoresponder_content_type_override" class="ui-input mt-1 w-full" data-autoresponder-content-type><option value="" @selected(blank($autoresponderContentTypeOverride))>Use website setting</option><option value="text" @selected($autoresponderContentTypeOverride === 'text')>Text</option><option value="html" @selected($autoresponderContentTypeOverride === 'html')>HTML</option></select></div>
                            <div data-autoresponder-content-panel="text" @if ($resolvedAutoresponderContentType === 'html') hidden @endif><label class="ui-label" for="autoresponder_body_override">Message override</label><x-trix-editor id="autoresponder_body_override" name="autoresponder_body_override" :value="old('autoresponder_body_override', $form->autoresponder_body_override)" placeholder="Leave blank to use the website default" /></div>
                            <div data-autoresponder-content-panel="html" @if ($resolvedAutoresponderContentType !== 'html') hidden @endif><label class="ui-label" for="autoresponder_body_override_html">Raw HTML override</label><textarea id="autoresponder_body_override_html" name="autoresponder_body_override" rows="10" class="ui-input mt-1 w-full font-mono" placeholder="Leave blank to use the website default">{{ old('autoresponder_body_override', $form->autoresponder_body_override) }}</textarea></div>
                            @error('autoresponder_body_override')<p class="text-base text-red-600 sm:text-sm">{{ $message }}</p>@enderror
                        </div>
                        <div class="mt-4 max-w-xs"><label class="ui-label" for="autoresponder_delay_minutes_override">Send delay override</label><input id="autoresponder_delay_minutes_override" type="number" min="0" max="10080" name="autoresponder_delay_minutes_override" value="{{ old('autoresponder_delay_minutes_override', $form->autoresponder_delay_minutes_override) }}" placeholder="Use website delay" class="ui-input mt-1 w-full"></div>
                        </details>
                    </section>
                @else
                    <section class="ui-well p-4" aria-labelledby="form-autoresponder-locked-title"><h3 id="form-autoresponder-locked-title" class="font-medium text-slate-900">Automatic customer reply</h3><p class="mt-1 text-base text-slate-600 sm:text-sm">Available with an active Sitewell plan.</p></section>
                @endif
            </div>
            @if ($canManageForm)<div class="flex justify-end"><button type="submit" class="ui-button ui-button-primary">Save settings</button></div>@endif
        </fieldset>
    </form>
    <div id="form-section-setup" role="region" aria-labelledby="form-tab-setup" @if ($formSection !== 'setup') hidden @endif>
    <section class="ui-panel ui-section" aria-labelledby="form-setup-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="form-setup-title" class="font-semibold text-slate-950">Form setup check</h2>
                <p class="mt-1 text-slate-600 text-base sm:text-sm">Check saved configuration without creating a lead or sending email or webhooks. This does not test the live form or prove delivery.</p>
                <p class="mt-2 text-slate-500 text-base sm:text-sm">Last checked: @if ($form->setup_checked_at)<time datetime="{{ $form->setup_checked_at->toIso8601String() }}">{{ $form->setup_checked_at->format('j M Y, H:i') }} {{ config('app.timezone') }}</time>. Run again after changing settings.@else Never.@endif</p>
            </div>
            @if ($form->website->isManageableBy(Auth::user()))
                <form method="POST" action="{{ route('admin.forms.setup-check', $form) }}" class="shrink-0">
                    @csrf
                    <input type="hidden" name="form_section" value="setup">
                    <button type="submit" class="ui-button ui-button-primary">Check form setup</button>
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
                        <div class="min-w-0"><p class="font-medium text-slate-900 text-base sm:text-sm">{{ $check['label'] }}</p><p class="mt-1 text-slate-600 text-base sm:text-sm">{{ $check['message'] }}</p>
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
