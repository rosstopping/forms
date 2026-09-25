@extends('layouts.app')

@section('content')
@php
    $stepKeys = array_keys($steps);
    $stepNumber = array_search($step, $stepKeys, true) + 1;
    $saved = $setup?->saved_steps ?? [];
    $descriptions = [
        'business' => 'Start with who the business serves and what it does.',
        'goals' => 'Give the SEO work a clear business priority.',
        'connection' => 'Connect the tools needed to prepare and deliver website changes.',
        'google' => 'Connect the client’s search data and, where relevant, local business profile.',
        'targets' => 'Choose the searches and competitors that matter to this business.',
        'content' => 'Set the language, voice and facts every content draft should use.',
        'delivery' => 'Choose when work is prepared and where enquiries go.',
        'review' => 'Review the saved brief, connection status and settings before applying them.',
    ];
@endphp
<div class="space-y-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-base font-medium text-teal-700 sm:text-sm">Client setup{{ $website ? ' · '.$website->name : '' }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-balance text-slate-950">{{ $website ? 'Set up the service.' : 'Set up a client.' }}</h1>
            <p class="mt-2 max-w-[64ch] text-base text-pretty text-slate-600 sm:text-sm">Work through this together on a call, or save your progress and finish later.</p>
        </div>
        <a href="{{ $website ? route('admin.websites.show', $website) : route('admin.websites.index') }}" class="ui-button ui-button-secondary">{{ $website ? 'View website' : 'All websites' }}</a>
    </header>

    @foreach (['status' => 'bg-teal-50 text-teal-900', 'error' => 'bg-red-50 text-red-900'] as $message => $classes)
        @if (session($message))<p role="status" class="rounded-lg p-4 text-base sm:text-sm {{ $classes }}">{{ session($message) }}</p>@endif
    @endforeach

    <div class="grid items-start gap-8 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <nav aria-label="Setup progress" class="min-w-0">
            <p class="mb-3 text-base text-slate-500 sm:text-sm">Step <span class="tabular-nums">{{ $stepNumber }} of {{ count($steps) }}</span></p>
            <ol role="list" class="grid gap-1 sm:grid-cols-2 lg:grid-cols-1">
                @foreach ($steps as $key => $label)
                    <li>
                        @if ($website)
                            <a href="{{ route('admin.website-setup.edit', [$website, 'step' => $key]) }}" @if ($step === $key) aria-current="step" @endif @class(['flex min-h-12 items-center gap-3 rounded-lg px-3 py-2 text-base font-medium sm:text-sm', 'bg-teal-50 text-teal-950' => $step === $key, 'text-slate-600 hover:bg-slate-100' => $step !== $key])>
                                <span class="tabular-nums text-slate-500">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="flex-1">{{ $label }}</span>
                                @if (in_array($key, $saved))<span class="text-teal-700" aria-label="Answers saved">✓</span>@endif
                            </a>
                        @else
                            <p @class(['flex min-h-12 items-center gap-3 rounded-lg px-3 py-2 text-base font-medium sm:text-sm', 'bg-teal-50 text-teal-950' => $step === $key, 'text-slate-500' => $step !== $key])><span class="tabular-nums">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $label }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if ($website)
                <p class="mt-4 text-base text-slate-500 sm:text-sm">Steps marked ✓ have saved answers. Connections are checked separately.</p>
            @endif
        </nav>

        <section class="ui-panel ui-section min-w-0" aria-labelledby="setup-step-heading">
            <div class="mb-8">
                <h2 id="setup-step-heading" class="text-2xl font-semibold tracking-tight text-balance text-slate-950">{{ $steps[$step] }}</h2>
                <p class="mt-2 max-w-[64ch] text-base text-pretty text-slate-600 sm:text-sm">{{ $descriptions[$step] }}</p>
            </div>
            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-lg bg-red-50 p-4 text-base text-red-900 sm:text-sm">
                    <p class="font-medium">Check the following before continuing:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if (in_array($step, ['connection', 'google', 'review']))
                @include('admin.websites.partials.setup-connections')
            @endif

            <form method="POST" action="{{ $website ? route('admin.website-setup.update', [$website, 'step' => $step]) : route('admin.website-setup.store') }}" class="space-y-6">
                @csrf
                @if ($website) @method('PUT') @endif

                @if ($step === 'business')
                    @include('admin.websites.partials.setup-field', ['field' => 'name', 'label' => 'Business name', 'required' => true, 'maxlength' => 255, 'rows' => 1])
                    @unless ($website)
                        @include('admin.websites.partials.setup-field', ['field' => 'domain', 'label' => 'Website address', 'required' => true, 'maxlength' => 253, 'rows' => 1, 'hint' => 'A domain or full URL, for example https://example.com.'])
                        <div class="grid gap-2">
                            <label for="manager_id" class="ui-label">Client manager</label>
                            <select id="manager_id" name="manager_id" class="ui-input w-full">
                                <option value="">Assign later</option>
                                @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) old('manager_id') === (string) $user->id)>{{ $user->name }} · {{ $user->email }}</option>@endforeach
                            </select>
                            <p class="text-base text-slate-500 sm:text-sm">Choose an existing client account. <a href="{{ route('admin.users.create') }}" target="_blank" rel="noopener" class="underline underline-offset-4">Create an account in a new tab</a> if needed, then reload this page before filling it in.</p>
                        </div>
                    @else
                        <div class="ui-well p-4 text-base sm:text-sm">
                            <p class="font-medium">{{ $website->primaryDomain()?->domain }}</p>
                            <p class="mt-1 text-slate-600">Client membership: {{ $website->owner?->hasActiveMembership() ? ucfirst($website->owner->effectiveMembershipTier()) : 'No active membership' }}.</p>
                            <p class="mt-2"><a href="{{ route('admin.websites.section', [$website, 'settings']) }}" target="_blank" rel="noopener" class="underline underline-offset-4">Manage website users in a new tab</a></p>
                        </div>
                    @endunless
                    @include('admin.websites.partials.setup-field', ['field' => 'services', 'label' => 'What does the business sell or do?', 'required' => true, 'hint' => 'List the main services or products.'])
                    @include('admin.websites.partials.setup-field', ['field' => 'audience', 'label' => 'Who are the ideal customers?', 'required' => true, 'maxlength' => 1800, 'hint' => 'Include who they are, what they need and what helps them choose.'])
                    @include('admin.websites.partials.setup-field', ['field' => 'locations', 'label' => 'Where does the business work?', 'required' => true, 'hint' => 'Towns, regions or countries. Say whether customers visit a physical location.'])
                    @include('admin.websites.partials.setup-field', ['field' => 'difference', 'label' => 'Why do customers choose this business?', 'hint' => 'Specific strengths, specialisms or evidence. Leave unknown details blank.'])
                @elseif ($step === 'goals')
                    @include('admin.websites.partials.setup-field', ['field' => 'objective', 'label' => 'What should the website achieve?', 'required' => true, 'hint' => 'For example: more enquiries for commercial installations.'])
                    @include('admin.websites.partials.setup-field', ['field' => 'priority_services', 'label' => 'Which services or jobs matter most?', 'required' => true, 'hint' => 'Consider value, profitability and the work the team has capacity to take on.'])
                    @include('admin.websites.partials.setup-field', ['field' => 'avoid', 'label' => 'What work or customers should we avoid?'])
                    @include('admin.websites.partials.setup-field', ['field' => 'success_measure', 'label' => 'How will we judge success?', 'hint' => 'Record the baseline and a realistic outcome, if known. This brief does not install analytics or conversion tracking.'])
                @elseif ($step === 'connection')
                    <div class="grid gap-2">
                        <label for="method" class="ui-label">Which connection will we use?</label>
                        <select id="method" name="method" class="ui-input w-full">
                            @foreach (['github' => 'GitHub repository', 'wordpress' => 'WordPress with GitHub content delivery', 'later' => 'Decide or connect later'] + (config('forms.pixel_ui_enabled') ? ['pixel' => 'Sitewell Pixel'] : []) as $key => $label)
                                <option value="{{ $key }}" @selected(old('method', data_get($values, 'connection.method')) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-base text-slate-500 sm:text-sm">Choosing a method records the plan. A connection only shows as connected after the relevant setup succeeds.</p>
                    </div>
                @elseif ($step === 'google')
                    <div class="grid gap-2">
                        <label for="local_business" class="ui-label">Does this business need Google Business Profile management?</label>
                        <select id="local_business" name="local_business" class="ui-input w-full">
                            <option value="0" @selected(! old('local_business', data_get($values, 'google.local_business')))>No, or decide later</option>
                            <option value="1" @selected(old('local_business', data_get($values, 'google.local_business')))>Yes — a local business or service area</option>
                        </select>
                        <p class="text-base text-slate-500 sm:text-sm">Managed Business Profile work is included with Complete. You can save setup while access is being arranged.</p>
                    </div>
                @elseif ($step === 'targets')
                    @if ($website->seoTargetKeywords->isNotEmpty())
                        <div class="ui-well p-4 text-base sm:text-sm"><p class="font-medium">Already tracked</p><p class="mt-2 text-pretty text-slate-600">{{ $website->seoTargetKeywords->whereNull('archived_at')->pluck('term')->implode(', ') ?: 'No active terms.' }}</p></div>
                    @endif
                    @include('admin.websites.partials.setup-field', ['field' => 'keywords', 'label' => 'What should customers find this business for?', 'rows' => 6, 'maxlength' => 6000, 'hint' => 'One search term per line, including location where relevant. Up to 20 active keywords in total. Existing keywords stay in place; duplicates and archived terms are not added again.'])
                    @if ($website->competitors->isNotEmpty())
                        <p class="text-base text-slate-600 sm:text-sm">Existing competitors: {{ $website->competitors->pluck('domain')->implode(', ') }}.</p>
                    @endif
                    @include('admin.websites.partials.setup-field', ['field' => 'competitors', 'label' => 'Who are the competitors?', 'rows' => 5, 'maxlength' => 3000, 'hint' => 'One domain per line. Add known business competitors or relevant search competitors. Existing competitors remain unchanged. Saving this list does not run paid research.'])
                @elseif ($step === 'content')
                    <div class="grid gap-6 sm:grid-cols-2">
                        @include('admin.websites.partials.setup-field', ['field' => 'language', 'label' => 'Language and spelling', 'required' => true, 'rows' => 1, 'maxlength' => 100])
                        @include('admin.websites.partials.setup-field', ['field' => 'tone', 'label' => 'How should the writing sound?', 'required' => true, 'rows' => 1, 'maxlength' => 500])
                    </div>
                    @include('admin.websites.partials.setup-field', ['field' => 'facts', 'label' => 'Verified business facts', 'hint' => 'Qualifications, experience, guarantees and other claims we have checked.'])
                    @include('admin.websites.partials.setup-field', ['field' => 'examples', 'label' => 'Examples of writing to follow', 'hint' => 'Paste short examples or reference URLs. URLs are saved as guidance; their contents are not automatically imported.'])
                    @include('admin.websites.partials.setup-field', ['field' => 'avoid', 'label' => 'Words, claims and topics to avoid'])
                    @include('admin.websites.partials.setup-field', ['field' => 'guidance', 'label' => 'Anything else the writer should know?', 'maxlength' => 20000, 'hint' => 'Existing guidance is preserved here. Keep the combined business, goals and content brief within 4,500 characters so it all reaches the writer.'])
                @elseif ($step === 'delivery')
                    @if ($contentReadiness)<p class="ui-well p-4 text-base text-slate-700 sm:text-sm">Before scheduled content can start: {{ $contentReadiness }} You can finish setup with it switched off.</p>@endif
                    <fieldset class="space-y-4">
                        <legend class="mb-4 font-medium text-slate-950">Content preparation</legend>
                        @include('admin.websites.partials.setup-toggle', ['field' => 'enabled', 'label' => 'Enable scheduled content after finishing setup'])
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="grid gap-2"><label for="weekday" class="ui-label">Day</label><select id="weekday" name="weekday" class="ui-input w-full">@foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day => $label)<option value="{{ $day }}" @selected((int) old('weekday', data_get($values, 'delivery.weekday')) === $day)>{{ $label }}</option>@endforeach</select></div>
                            <div class="grid gap-2"><label for="hour" class="ui-label">Time</label><select id="hour" name="hour" class="ui-input w-full">@for ($hour = 0; $hour < 24; $hour++)<option value="{{ $hour }}" @selected((int) old('hour', data_get($values, 'delivery.hour')) === $hour)>{{ sprintf('%02d:00', $hour) }}</option>@endfor</select></div>
                            @include('admin.websites.partials.setup-field', ['field' => 'timezone', 'label' => 'Timezone', 'required' => true, 'rows' => 1, 'maxlength' => 100])
                        </div>
                        @if ($weeklyLimit >= 3)
                            <fieldset><legend class="ui-label">Additional days (up to two)</legend><div class="mt-2 flex flex-wrap gap-4">@foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day => $label)<label class="flex min-h-12 items-center gap-2 text-base sm:text-sm"><input type="checkbox" name="additional_weekdays[]" value="{{ $day }}" @checked(in_array($day, old('additional_weekdays', session()->hasOldInput() ? [] : data_get($values, 'delivery.additional_weekdays', []))))>{{ $label }}</label>@endforeach</div></fieldset>
                        @endif
                        <p class="text-base text-slate-500 sm:text-sm">Content is prepared for review through the existing workflow. Finishing setup does not publish website changes.</p>
                    </fieldset>
                    <div class="grid gap-2">
                        <label for="competitor_research_mode" class="ui-label">Competitor research</label>
                        <select id="competitor_research_mode" name="competitor_research_mode" class="ui-input w-full">@foreach (['manual' => 'Only when requested', 'research' => 'Schedule research only', 'drafts' => 'Schedule research and use it for content drafts'] as $mode => $label)<option value="{{ $mode }}" @selected(old('competitor_research_mode', data_get($values, 'delivery.competitor_research_mode')) === $mode)>{{ $label }}</option>@endforeach</select>
                        <p class="text-base text-slate-500 sm:text-sm">Scheduled research uses paid provider requests within the client’s membership limits. Choosing it here enables it when you finish setup.</p>
                    </div>
                    <fieldset class="space-y-4"><legend class="mb-4 font-medium">Reports</legend>
                        @include('admin.websites.partials.setup-toggle', ['field' => 'health_reports_enabled', 'label' => 'Enable scheduled website health reports'])
                        @include('admin.websites.partials.setup-toggle', ['field' => 'weekly_ranking_reports_enabled', 'label' => 'Enable weekly ranking reports'])
                        <p class="text-base text-slate-500 sm:text-sm">Reports use the existing website-user delivery rules. Manage recipients through website users. Paid SEO snapshots keep their current setting.</p>
                    </fieldset>
                    @include('admin.websites.partials.setup-toggle', ['field' => 'email_enabled', 'label' => 'Email new website enquiries'])
                    @include('admin.websites.partials.setup-field', ['field' => 'email_recipients', 'label' => 'Enquiry email recipients', 'maxlength' => 3000, 'hint' => 'Email addresses separated by commas or new lines. This changes website defaults; form-specific overrides stay in place.'])
                @elseif ($step === 'review')
                    @if ($setup?->completed_at)
                        <p class="rounded-lg bg-teal-50 p-4 text-base text-teal-900 sm:text-sm">Setup settings were applied {{ $setup->completed_at->diffForHumans() }}. You can revisit any step to make changes.</p>
                    @endif
                    <dl class="divide-y divide-slate-950/10">
                        @foreach (['business' => ['name' => 'Business', 'services' => 'Services', 'audience' => 'Customers', 'locations' => 'Locations', 'difference' => 'Business strengths'], 'goals' => ['objective' => 'Goal', 'priority_services' => 'Priority services', 'success_measure' => 'Success measure', 'avoid' => 'Work to avoid'], 'targets' => ['keywords' => 'New keywords', 'competitors' => 'New competitors'], 'content' => ['language' => 'Language', 'tone' => 'Voice', 'facts' => 'Verified facts', 'examples' => 'Writing examples', 'avoid' => 'Content exclusions', 'guidance' => 'Writing guidance']] as $section => $fields)
                            @foreach ($fields as $field => $label)
                                <div class="grid gap-2 py-4 sm:grid-cols-[10rem_minmax(0,1fr)]"><dt class="font-medium text-slate-700">{{ $label }}</dt><dd class="whitespace-pre-line break-words text-base text-slate-600 sm:text-sm">{{ data_get($values, $section.'.'.$field) ?: 'Not supplied' }}</dd></div>
                            @endforeach
                        @endforeach
                    </dl>
                    <div class="ui-well space-y-3 p-4 text-base sm:text-sm">
                        <p class="font-medium">Settings to apply</p>
                        <p>Website connection: {{ ['github' => 'GitHub', 'wordpress' => 'WordPress with GitHub', 'pixel' => 'Sitewell Pixel', 'later' => 'Connect later'][data_get($values, 'connection.method', 'later')] }}. Business Profile management: {{ data_get($values, 'google.local_business') ? 'Requested' : 'Not requested' }}.</p>
                        <p>Content schedule: {{ ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][(int) data_get($values, 'delivery.weekday', 1)] }}{{ collect(data_get($values, 'delivery.additional_weekdays', []))->map(fn ($day) => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][(int) $day])->map(fn ($day) => ', '.$day)->implode('') }} at {{ sprintf('%02d:00', data_get($values, 'delivery.hour', 8)) }} ({{ data_get($values, 'delivery.timezone', 'Europe/London') }}).</p>
                        <p>Scheduled content: <strong>{{ data_get($values, 'delivery.enabled') ? 'On' : 'Off' }}</strong>. Competitor research: <strong>{{ ['manual' => 'Only when requested', 'research' => 'Scheduled research', 'drafts' => 'Scheduled research and draft suggestions'][data_get($values, 'delivery.competitor_research_mode', 'manual')] }}</strong>.</p>
                        <p>Health reports: {{ data_get($values, 'delivery.health_reports_enabled') ? 'On' : 'Off' }}. Weekly ranking reports: {{ data_get($values, 'delivery.weekly_ranking_reports_enabled') ? 'On' : 'Off' }}.</p>
                        <p>Enquiry notifications: {{ data_get($values, 'delivery.email_enabled') ? data_get($values, 'delivery.email_recipients') : 'Off' }}.</p>
                        @if ($contentReadiness)<p class="text-amber-800">Content needs attention: {{ $contentReadiness }}</p>@endif
                        <p class="text-slate-600">Missing connections can be completed later. Scheduled content can only be enabled when its prerequisites are met.</p>
                    </div>
                    @unless ($setup?->completed_at)
                        <label class="flex min-h-12 items-center gap-3 text-base sm:text-sm"><input name="confirm" value="1" type="checkbox" required @checked(old('confirm'))>I have reviewed the brief and settings.</label>
                    @endunless
                @endif

                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-950/10 pt-6">
                    <div>
                        @if ($website && $stepNumber > 1)<a href="{{ route('admin.website-setup.edit', [$website, 'step' => $stepKeys[$stepNumber - 2]]) }}" class="ui-button ui-button-secondary">Back</a>@endif
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if ($website && $step !== 'review')<button name="navigation" value="save" class="ui-button ui-button-secondary">Save and exit</button>@endif
                        @if ($step === 'review' && $setup?->completed_at)
                            <a href="{{ route('admin.websites.show', $website) }}" class="ui-button ui-button-primary">Open website</a>
                        @else
                            <button type="submit" name="navigation" value="next" class="ui-button ui-button-primary">{{ $step === 'review' ? 'Finish setup' : ($website ? 'Save and continue' : 'Create client website') }}</button>
                        @endif
                    </div>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection
