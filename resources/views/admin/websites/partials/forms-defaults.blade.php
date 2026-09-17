        <div class="ui-panel ui-section lg:col-span-2">
            <h2 class="font-semibold text-teal-950">Automatic customer reply</h2>
            @if (! $canUseAutoresponders)
                <p class="mt-1 text-base text-slate-600 sm:text-sm">Automatic customer replies require an active Sitewell plan.</p>
                <a href="{{ route('admin.billing.index') }}" class="ui-button ui-button-secondary mt-4">View plans</a>
            @elseif (! $canManageWebsite)
                <p class="mt-2 text-base text-slate-600 sm:text-sm">A website manager can change the default customer reply.</p>
            @else
            <p class="mt-1 text-base text-teal-800 sm:text-sm">This is the default acknowledgement for every form on {{ $website->name }}. Form-specific customisations take priority; team notifications are set inside each form.</p>
            <form method="POST" action="{{ route('admin.websites.autoresponder.update', $website) }}" class="space-y-4 border-t border-slate-950/10 p-4">
                @csrf
                @method('PUT')
                @if ($errors->any())
                    <div role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">
                        <p class="font-medium">Check these settings before saving.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <input type="hidden" name="forms_section" value="defaults">
                <input type="hidden" name="autoresponder_enabled" value="0">
                <label class="ui-label flex items-start gap-3 rounded-lg border border-teal-600/15 bg-white p-3">
                    <input type="checkbox" name="autoresponder_enabled" value="1" class="mt-1" @checked(old('autoresponder_enabled', $website->autoresponder_enabled))>
                    <span><span class="block text-sm font-medium text-slate-900">Automatically acknowledge new enquiries</span><span class="block text-xs text-slate-500">Only sends when a valid customer email is present and the submission passes spam checks.</span></span>
                </label>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label class="ui-label" for="autoresponder_from_name">From name</label>
                        <input id="autoresponder_from_name" name="autoresponder_from_name" value="{{ old('autoresponder_from_name', $website->autoresponder_from_name) }}" placeholder="{{ config('mail.from.name') }}" class="ui-input mt-1 w-full">
                        @error('autoresponder_from_name')<p class="mt-1 text-red-600 text-base sm:text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="autoresponder_reply_to_email">Reply-to email address</label>
                        <input id="autoresponder_reply_to_email" name="autoresponder_reply_to_email" type="email" maxlength="255" value="{{ old('autoresponder_reply_to_email', $website->autoresponder_reply_to_email) }}" placeholder="hello@yourbusiness.com" class="ui-input mt-2 w-full">
                        <p class="mt-2 text-base text-slate-500 sm:text-sm">When a customer replies to the acknowledgement, their message goes here. Leave blank to use the sender address.</p>
                        @error('autoresponder_reply_to_email')<p class="mt-2 text-rose-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                    </div>
                    <p class="text-base text-slate-500 lg:col-span-2 sm:text-sm">Sent from {{ config('forms.autoresponder_from_address') }}. Your reply-to address can be your own business inbox; no Postmark setup is needed.</p>
                    <div>
                        <label class="ui-label" for="autoresponder_subject">Email subject</label>
                        <input id="autoresponder_subject" name="autoresponder_subject" value="{{ old('autoresponder_subject', $website->autoresponder_subject) }}" placeholder="We've received your {form_name} enquiry" class="ui-input mt-1 w-full">
                    </div>
                    @php
                        $autoresponderContentType = old('autoresponder_content_type', $website->autoresponder_content_type ?? 'text');
                    @endphp
                    <div class="space-y-4 lg:row-span-2" data-autoresponder-content-editor data-field-name="autoresponder_body">
                        <div>
                            <label class="ui-label" for="autoresponder_content_type">Message format</label>
                            <select id="autoresponder_content_type" name="autoresponder_content_type" class="ui-input mt-1 w-full" data-autoresponder-content-type>
                                <option value="text" @selected($autoresponderContentType === 'text')>Text</option>
                                <option value="html" @selected($autoresponderContentType === 'html')>HTML</option>
                            </select>
                        </div>
                        <div data-autoresponder-content-panel="text" @if ($autoresponderContentType !== 'text') hidden @endif>
                            <label class="ui-label" for="autoresponder_body">Email message</label>
                            <x-trix-editor id="autoresponder_body" name="autoresponder_body" :value="old('autoresponder_body', $website->autoresponder_body)" placeholder="Write the automatic reply…" />
                        </div>
                        <div data-autoresponder-content-panel="html" @if ($autoresponderContentType !== 'html') hidden @endif>
                            <label class="ui-label" for="autoresponder_body_html">Raw HTML</label>
                            <textarea id="autoresponder_body_html" name="autoresponder_body" rows="10" class="ui-input mt-1 w-full font-mono" placeholder="<!doctype html>…">{{ old('autoresponder_body', $website->autoresponder_body) }}</textarea>
                            <p class="mt-1 text-slate-500 text-base sm:text-sm">HTML is sent exactly as entered. Use complete email-safe markup and inline styles where needed.</p>
                        </div>
                        @error('autoresponder_body')
                            <p class="text-red-600 text-base sm:text-sm">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-slate-500 text-base sm:text-sm">Use any submitted field name as a tag, for example {email}, {phone}, or {budget}. Also available: {name}, {form_name}, {website_name}, {website_domain}, {submission_id}.</p>
                    </div>
                    <div>
                        <label class="ui-label" for="autoresponder_delay_minutes">Send delay (minutes)</label>
                        <input id="autoresponder_delay_minutes" type="number" min="0" max="10080" name="autoresponder_delay_minutes" value="{{ old('autoresponder_delay_minutes', $website->autoresponder_delay_minutes ?? 0) }}" class="ui-input mt-1 w-full">
                        <p class="mt-1 text-slate-500 text-base sm:text-sm">Use 0 to queue the reply immediately.</p>
                    </div>
                    <div><button type="submit" class="ui-button ui-button-primary">Save automatic reply</button></div>
                </div>
            </form>

            @endif
        </div>
