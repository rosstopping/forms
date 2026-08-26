@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $form->name }}</h1>
            <p class="text-sm text-slate-600">Form details and notification settings.</p>
        </div>
        <a href="{{ route('admin.websites.show', [$form->website, 'tab' => 'forms']) }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to forms</a>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-lg border bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Overview</h2>
        <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
            <div class="flex justify-between"><dt class="text-slate-500">Slug</dt><dd class="font-medium">{{ $form->slug }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Website</dt><dd class="font-medium">{{ $form->website?->name ?: 'Unknown' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-medium">{{ $form->is_active ? 'Active' : 'Disabled' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Auto discovered</dt><dd class="font-medium">{{ $form->auto_discovered ? 'Yes' : 'No' }}</dd></div>
        </dl>
    </div>

    <form method="POST" action="{{ route('admin.forms.update', $form) }}" class="rounded-lg border bg-white p-4 shadow-sm">
        @csrf
        @method('PUT')

        <h2 class="font-semibold">Notification settings</h2>
        <p class="mt-1 text-sm text-slate-600">Leave these off unless you want this form to send notifications.</p>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="flex items-center gap-2 rounded-md border border-slate-200 p-3 text-sm">
                <input type="checkbox" name="email_enabled_override" value="1" @checked((bool) $form->email_enabled_override)>
                <span>Send email notifications for this form</span>
            </label>

            <label class="flex items-center gap-2 rounded-md border border-slate-200 p-3 text-sm">
                <input type="checkbox" name="webhook_enabled_override" value="1" @checked((bool) $form->webhook_enabled_override)>
                <span>Send webhook notifications for this form</span>
            </label>
        </div>

        <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
            <h3 class="font-semibold text-blue-950">Automatic customer reply</h3>
            <p class="mt-1 text-sm text-blue-800">Acknowledge genuine enquiries automatically. Spam submissions are never emailed.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-slate-700" for="autoresponder_mode">Behaviour for this form</label>
                    <select id="autoresponder_mode" name="autoresponder_mode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="inherit" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override === null ? 'inherit' : ($form->autoresponder_enabled_override ? 'enabled' : 'disabled')) === 'inherit')>Use website setting</option>
                        <option value="enabled" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override ? 'enabled' : null) === 'enabled')>Enabled for this form</option>
                        <option value="disabled" @selected(old('autoresponder_mode', $form->autoresponder_enabled_override === false ? 'disabled' : null) === 'disabled')>Disabled for this form</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="autoresponder_subject_override">Subject override</label>
                    <input id="autoresponder_subject_override" name="autoresponder_subject_override" value="{{ old('autoresponder_subject_override', $form->autoresponder_subject_override) }}" placeholder="Leave blank to use website default" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4">
                <label class="text-sm font-medium text-slate-700" for="autoresponder_body_override">Message override</label>
                <textarea id="autoresponder_body_override" name="autoresponder_body_override" rows="6" placeholder="Leave blank to use website default" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('autoresponder_body_override', $form->autoresponder_body_override) }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Use any submitted field name as a tag, for example {email}, {phone}, or {budget}. Also available: {name}, {form_name}, {website_name}, {website_domain}, {submission_id}.</p>
            </div>
            <div class="mt-4">
                <label class="text-sm font-medium text-slate-700" for="autoresponder_delay_minutes_override">Send delay override (minutes)</label>
                <input id="autoresponder_delay_minutes_override" type="number" min="0" max="10080" name="autoresponder_delay_minutes_override" value="{{ old('autoresponder_delay_minutes_override', $form->autoresponder_delay_minutes_override) }}" placeholder="Leave blank to use website delay" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-700" for="email_recipients_override">Email recipients</label>
                <textarea id="email_recipients_override" name="email_recipients_override" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('email_recipients_override', is_array($form->email_recipients_override) ? implode(PHP_EOL, $form->email_recipients_override) : (string) $form->email_recipients_override) }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Enter one email address per line.</p>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700" for="email_subject_override">Email subject override</label>
                <input id="email_subject_override" type="text" name="email_subject_override" value="{{ old('email_subject_override', $form->email_subject_override) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-700" for="webhook_url_override">Webhook URL</label>
                <input id="webhook_url_override" type="url" name="webhook_url_override" value="{{ old('webhook_url_override', $form->webhook_url_override) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700" for="webhook_secret_override">Webhook secret</label>
                <input id="webhook_secret_override" type="text" name="webhook_secret_override" value="{{ old('webhook_secret_override', $form->webhook_secret_override) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Save settings</button>
        </div>
    </form>

</div>
@endsection
