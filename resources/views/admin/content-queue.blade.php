@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950">My content queue</h1>
            <p class="mt-2 text-sm text-slate-600">Work through manual content across your websites. Start a task, copy its prompt into Codex, then mark it done.</p>
        </header>
        @if ($dueWebsites->isNotEmpty())
            <section class="ui-panel ui-section" aria-labelledby="due-content-title">
                <h2 id="due-content-title" class="text-lg font-semibold text-slate-950">Due today</h2>
                <p class="mt-2 text-sm text-slate-600">These websites are due content attention. What would you like to work on? Suggestions need your approval before they enter the queue.</p>
                <div class="mt-5 space-y-4">
                    @foreach ($dueWebsites as $dueWebsite)
                        <article class="ui-well p-4">
                            <h3 class="font-semibold text-slate-950">{{ $dueWebsite['website'] }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ \Carbon\CarbonImmutable::parse($dueWebsite['scheduled_at'])->format('H:i') }} · {{ $dueWebsite['timezone'] }}</p>
                            <p class="mt-2 text-sm text-slate-700">{{ $dueWebsite['waiting_count'] ? $dueWebsite['waiting_count'].' approved '.Str::plural('request', $dueWebsite['waiting_count']).' waiting. Check the queue for any holds or blockers.' : 'There’s nothing approved in this website’s queue.' }}</p>
                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <a href="{{ $dueWebsite['suggestions_url'] }}" class="ui-button ui-button-primary ui-button-small">Choose a suggestion</a>
                                @if ($dueWebsite['planned_count'])
                                    <a href="{{ $dueWebsite['plan_url'] }}" class="ui-button ui-button-secondary ui-button-small">Review planned content ({{ $dueWebsite['planned_count'] }})</a>
                                @endif
                                <a href="{{ $dueWebsite['queue_url'] }}" class="ui-button ui-button-secondary ui-button-small">{{ $dueWebsite['waiting_count'] ? 'Open queue' : 'Add a request' }}</a>
                                <form method="POST" action="{{ route('admin.content-queue.skip-today', $dueWebsite['website_id']) }}">
                                    @csrf
                                    <button type="submit" class="ui-button ui-button-secondary ui-button-small">Skip today</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
        @if ($tasks->isEmpty())
            <section class="ui-panel ui-section">
                <h2 class="font-semibold text-slate-950">{{ $dueWebsites->isEmpty() ? 'You’re all caught up' : 'No approved tasks waiting' }}</h2>
                <p class="mt-2 text-sm text-slate-600">No manual tasks are waiting. Select Manual in a website’s Content engine settings and add or approve requests into its content queue.</p>
            </section>
        @else
            <div class="grid gap-6 lg:grid-cols-3">
                <section class="space-y-3" aria-label="Content tasks">
                    <p class="text-sm font-medium text-slate-600">{{ $tasks->total() }} {{ Str::plural('task', $tasks->total()) }} remaining</p>
                    @foreach ($tasks as $task)
                        <a href="{{ route('admin.content-queue.index', ['task' => $task->id, 'page' => $tasks->currentPage()]) }}" @class(['ui-panel block p-4 hover:bg-slate-50', 'ring-2 ring-teal-500' => $selected?->is($task)]) @if($selected?->is($task)) aria-current="true" @endif>
                            <span class="text-sm font-semibold text-teal-700">{{ $task->website->name }}</span>
                            <p class="mt-2 line-clamp-3 break-words text-sm text-slate-800">{{ $task->instructions }}</p>
                            <p class="mt-2 text-xs text-slate-500">{{ $task->manual_started_at ? 'In progress · yours' : ($task->held_at ? 'On hold' : 'Waiting') }}</p>
                        </a>
                    @endforeach
                    {{ $tasks->links() }}
                </section>
                @if ($selected)
                    <section class="ui-panel ui-section min-w-0 lg:col-span-2" aria-labelledby="manual-task-title">
                        <p class="text-sm font-medium text-teal-700">{{ $selected->website->name }}</p>
                        <h2 id="manual-task-title" class="mt-2 text-lg font-semibold text-slate-950">{{ $selected->manual_started_at ? 'Continue in Codex' : 'Next content task' }}</h2>
                        <p class="mt-3 whitespace-pre-line break-words text-sm text-slate-700">{{ $selected->instructions }}</p>
                        @if ($selected->manual_started_at)
                            <div class="mt-5 flex flex-wrap gap-3">
                                <button type="button" class="ui-button ui-button-primary js-copy-text" data-copy-target="manual-task-prompt" data-copy-label="Copy AI prompt" data-copied-label="Copied">Copy AI prompt</button>
                                <form method="POST" action="{{ route('admin.content-requests.manual.complete', [$selected->website, $selected]) }}">
                                    @csrf
                                    <input type="hidden" name="return_to" value="my-queue">
                                    <button type="submit" class="ui-button ui-button-secondary">Mark done &amp; next</button>
                                </form>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">Done records your work as complete; publication and SEO results are tracked separately.</p>
                            <label for="manual-task-prompt" class="ui-label mt-5 block">AI prompt</label>
                            <textarea id="manual-task-prompt" class="ui-input mt-2 h-80 w-full resize-y font-mono text-sm" readonly>{{ $selected->manual_prompt }}</textarea>
                            <form method="POST" action="{{ route('admin.content-requests.manual.release', [$selected->website, $selected]) }}" class="mt-4">
                                @csrf
                                <input type="hidden" name="return_to" value="my-queue">
                                <button type="submit" class="ui-button ui-button-secondary ui-button-small">Return to queue</button>
                            </form>
                        @elseif ($pauseReason)
                            <p class="ui-well mt-5 p-4 text-sm text-amber-800">{{ $pauseReason }}</p>
                        @else
                            <form method="POST" action="{{ route('admin.content-requests.manual.take', [$selected->website, $selected]) }}" class="mt-5">
                                @csrf
                                <input type="hidden" name="return_to" value="my-queue">
                                <button type="submit" class="ui-button ui-button-primary">Start task</button>
                            </form>
                            <p class="mt-3 text-xs text-slate-500">Starting reserves this task for you and prepares its full AI prompt.</p>
                        @endif
                        <a href="{{ route('admin.websites.section', [$selected->website, 'content', 'content_section' => 'queue']) }}" class="mt-5 inline-block text-sm font-medium text-teal-700 underline">Open website content queue</a>
                    </section>
                @endif
            </div>
        @endif
    </div>
@endsection
