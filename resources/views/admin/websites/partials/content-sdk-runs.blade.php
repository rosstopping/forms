<div class="space-y-3" data-sdk-active="{{ $sdkRuns->contains(fn ($run) => in_array($run->status, ['queued', 'publish_queued', 'running', 'validated', 'publishing'], true)) ? 'true' : 'false' }}">
    @forelse ($sdkRuns as $sdkRun)
        @php
            $sdkStatus = match ($sdkRun->status) {
                'queued' => 'Waiting for worker', 'running' => 'Preparing change', 'validated' => 'Change validated',
                'publish_queued' => 'Publishing queued', 'publishing' => 'Opening draft PR', 'pull_request_open' => 'Ready for review',
                'pull_request_merged' => 'Merged', 'pull_request_closed' => 'PR closed', 'publish_failed' => 'Publishing needs attention',
                'failed' => 'Run failed', default => 'Status unavailable',
            };
        @endphp
        <article class="ui-panel ui-section space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h4 class="break-words font-medium text-slate-950">{{ $sdkRun->title }}</h4>
                    <p class="mt-1 break-all text-sm text-slate-600">{{ $sdkRun->path }} · {{ $sdkRun->created_at->format('j M Y, H:i') }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $sdkStatus }}</span>
            </div>
            @if ($sdkRun->error) <p class="text-sm text-amber-800">{{ $sdkRun->error }}</p> @endif
            @if ($sdkRun->usage !== null)
                <p class="text-sm text-slate-600 tabular-nums">Reported tokens: {{ number_format(data_get($sdkRun->usage, 'inputTokens', 0)) }} input / {{ number_format(data_get($sdkRun->usage, 'outputTokens', 0)) }} output</p>
            @endif
            @if ($sdkRun->pull_request_url)
                <a href="{{ $sdkRun->pull_request_url }}" target="_blank" rel="noopener noreferrer" class="ui-button ui-button-secondary ui-button-small">Review pull request →</a>
            @elseif ($sdkRun->status === 'publish_failed' && $sdkRun->requested_by === auth()->id() && config('copilot_sdk.enabled'))
                <form method="POST" action="{{ route('admin.sdk-runs.resume', [$website, $sdkRun]) }}">
                    @csrf
                    <button type="submit" class="ui-button ui-button-secondary ui-button-small">Resume publishing · no model call</button>
                </form>
            @endif
            <p class="break-all text-xs text-slate-500">Run {{ $sdkRun->run_id }}</p>
        </article>
    @empty
        <p class="ui-well text-sm text-slate-600">No SDK runs yet. Prepare a title change above to test this repository.</p>
    @endforelse
</div>
