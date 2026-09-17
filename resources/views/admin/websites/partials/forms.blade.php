@php
    $formsSections = ['list' => 'Your forms', 'defaults' => 'Default customer reply', 'installation' => 'Connect a form'];
    $formsSection = request('forms_section', 'list');
    $formsSection = is_string($formsSection) && isset($formsSections[$formsSection]) ? $formsSection : 'list';
    if (collect(array_keys($errors->getMessages()))->contains(fn ($key) => str_starts_with($key, 'autoresponder_'))) {
        $formsSection = 'defaults';
    }
@endphp
<div id="website-panel-forms" class="space-y-6" role="region" aria-labelledby="website-tab-forms" data-tab-panel="forms" @if ($currentWebsiteSection !== 'forms') hidden @endif>
    <header><h2 class="text-xl font-semibold text-balance text-slate-950">Forms and email setup</h2><p class="mt-2 text-base text-pretty text-slate-500 sm:text-sm">Choose a form to set team notifications. Set one default customer reply for the website, then customise individual forms only where needed.</p></header>
    <nav class="overflow-x-auto border-b border-slate-950/10" aria-label="Forms sections"><div class="flex min-w-max gap-1 pb-2 text-sm">
        @foreach ($formsSections as $key => $label)
            <a id="forms-tab-{{ $key }}" href="{{ route('admin.websites.section', [$website, 'forms', 'forms_section' => $key]) }}" @if ($formsSection === $key) aria-current="page" @endif @class(['rounded-lg px-3 py-2 font-medium', 'bg-teal-50 text-teal-900' => $formsSection === $key, 'text-slate-500 hover:bg-slate-50' => $formsSection !== $key])>{{ $label }}</a>
        @endforeach
    </div></nav>
    <section id="forms-section-list" class="space-y-4" aria-labelledby="forms-tab-list" @if ($formsSection !== 'list') hidden @endif>
        <div class="rounded-xl bg-slate-50 p-4 text-base text-slate-600 sm:text-sm"><p><strong class="font-medium text-slate-900">Two different emails:</strong> team notifications tell you about an enquiry; customer replies acknowledge it to the visitor. Replies to a team notification go to the visitor automatically.</p></div>
        <div class="divide-y divide-slate-950/10">
            @forelse ($website->forms as $form)
                @php
                    $hasReplyCustomisation = collect(['autoresponder_enabled_override', 'autoresponder_subject_override', 'autoresponder_body_override', 'autoresponder_content_type_override', 'autoresponder_delay_minutes_override', 'autoresponder_reply_to_email_override'])->contains(fn ($field) => $form->{$field} !== null && $form->{$field} !== '');
                @endphp
                <article class="flex flex-wrap items-center justify-between gap-4 py-5 first:pt-2">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold text-slate-950"><a href="{{ route('admin.forms.show', $form) }}" class="hover:text-teal-700">{{ $form->name }}</a></h3>
                        <p class="mt-1 text-base text-slate-500 sm:text-sm">{{ $form->is_active ? 'Active' : 'Disabled' }} · {{ $form->submissions_count }} submissions</p>
                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                            <p class="text-slate-600">Team notifications: <strong class="font-medium">{{ $form->email_enabled_override ? (filled($form->email_recipients_override) ? 'On' : 'Recipients needed') : 'Off' }}</strong></p>
                            <p class="text-slate-600">Customer reply: <strong class="font-medium">{{ $hasReplyCustomisation ? 'Custom settings' : 'Website defaults' }}</strong></p>
                        </div>
                    </div>
                    <div class="shrink-0 text-sm"><a href="{{ route('admin.forms.show', $form) }}" class="inline-flex rounded-lg bg-teal-50 px-3 py-2 font-medium text-teal-800 hover:bg-teal-100">{{ $canManageWebsite ? 'Set up form' : 'View form' }} →</a></div>
                </article>
            @empty
                <div class="py-8"><h3 class="font-medium text-slate-900">No forms registered for this website.</h3><p class="mt-2 text-base text-slate-500 sm:text-sm">Connect a website form and it will appear here when its first submission is received. Then choose your notification recipients.</p><p class="mt-4 text-sm"><a href="{{ route('admin.websites.section', [$website, 'forms', 'forms_section' => 'installation']) }}" class="font-medium text-teal-700 underline">Connect your first form</a></p></div>
            @endforelse
        </div>
    </section>
    <section id="forms-section-defaults" aria-labelledby="forms-tab-defaults" @if ($formsSection !== 'defaults') hidden @endif>@include('admin.websites.partials.forms-defaults')</section>
    <section id="forms-section-installation" aria-labelledby="forms-tab-installation" @if ($formsSection !== 'installation') hidden @endif>@include('admin.websites.partials.forms-installation')</section>
</div>
