<div class="space-y-6">
    <header>
        <p class="text-sm font-medium text-teal-700">Admin preview</p>
        <h3 class="mt-1 text-lg font-semibold text-slate-950">Prepare a title change</h3>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">Test the connected repository by changing one HTML page title. Sitewell checks the change and opens a draft pull request for you to review and merge.</p>
    </header>
    @error('sdk') <p role="alert" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ $message }}</p> @enderror
    @if (config('copilot_sdk.enabled'))
        <form method="POST" action="{{ route('admin.sdk-runs.store', $website) }}" class="ui-panel ui-section space-y-4">
            @csrf
            <p class="text-sm text-slate-600">Repository: <span class="font-medium text-slate-900">{{ $website->repository->full_name }}</span> · {{ $website->repository->default_branch }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="sdk-path" class="ui-label">HTML file</label>
                    <input id="sdk-path" name="path" class="ui-input mt-1" value="{{ old('path', trim($website->repository->project_path ?? '', '/') ? trim($website->repository->project_path, '/').'/index.html' : 'index.html') }}" required maxlength="240" aria-describedby="sdk-file-help">
                    <p id="sdk-file-help" class="mt-1 text-xs text-slate-500">Existing repository file, up to 8 KB.</p>
                    @error('path') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="sdk-title" class="ui-label">New page title</label>
                    <input id="sdk-title" name="title" class="ui-input mt-1" value="{{ old('title') }}" placeholder="Enter the title you want to use" required maxlength="200">
                    @error('title') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button class="ui-button ui-button-primary" type="submit">Run with SDK</button>
                <p class="text-xs text-slate-500">Uses the configured model API. Nothing is merged automatically.</p>
            </div>
        </form>
    @else
        <p class="ui-well text-sm text-slate-600">SDK execution is disabled. Existing runs remain available below.</p>
    @endif
    <section aria-labelledby="sdk-runs-heading" class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h3 id="sdk-runs-heading" class="text-lg font-semibold text-slate-950">Recent SDK runs</h3>
            <a href="{{ route('admin.websites.section', [$website, 'content', 'content_section' => 'sdk']) }}" class="ui-button ui-button-secondary ui-button-small">Refresh</a>
        </div>
        <div data-sdk-runs data-sdk-status-url="{{ route('admin.sdk-runs.status', $website) }}" aria-live="polite">
            @include('admin.websites.partials.content-sdk-runs')
        </div>
        <p class="text-xs text-slate-500">Showing the latest 10 runs. Active runs refresh automatically. Token counts are reported when execution finishes.</p>
    </section>
</div>
