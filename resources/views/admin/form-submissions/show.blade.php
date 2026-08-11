@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $formSubmission->displayName() }}</h1>
            <p class="text-sm text-slate-600">{{ $formSubmission->replyToEmail() ?: 'No email address supplied' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canManage)
                @unless ($formSubmission->is_spam)
                    <form method="POST" action="{{ route('admin.form-submissions.spam', $formSubmission) }}" data-confirm-action-form>
                        @csrf
                        @method('PATCH')
                        <button type="button" data-confirm-action data-confirm-title="Mark this lead as spam?" data-confirm-message="The lead will be hidden from the default inbox, but it will not be deleted." data-confirm-label="Mark as spam" class="rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50">Mark as spam</button>
                    </form>
                @endunless
                <form method="POST" action="{{ route('admin.form-submissions.destroy', $formSubmission) }}" data-confirm-action-form>
                    @csrf
                    @method('DELETE')
                    <button type="button" data-confirm-action data-confirm-title="Delete this lead?" data-confirm-message="This submission will be permanently deleted. This cannot be undone." data-confirm-label="Delete lead" data-confirm-danger class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
                </form>
            @endif
            <a href="{{ route('admin.form-submissions.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to submissions</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Lead overview</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Source domain</dt><dd class="font-medium">{{ e($formSubmission->source_domain ?: 'Unknown') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Source URL</dt><dd class="font-medium break-all">{{ e($formSubmission->source_url ?: 'Unknown') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Form</dt><dd class="font-medium">{{ e($formSubmission->form?->name ?: 'Unknown form') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Website</dt><dd class="font-medium">{{ e($formSubmission->website?->name ?: 'Unknown website') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-medium"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $formSubmission->resolvedStatusLabel() }}</span></dd></div>
                {{-- <div class="flex justify-between"><dt class="text-slate-500">Owner</dt><dd class="font-medium">{{ e($formSubmission->assignee?->name ?: 'Unassigned') }}</dd></div> --}}
            </dl>

            <form method="POST" action="{{ route('admin.form-submissions.update', $formSubmission) }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="status">Status</label>
                    <select id="status" name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'] as $value => $label)
                            <option value="{{ $value }}" @selected($formSubmission->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- <div>
                    <label class="block text-sm font-medium text-slate-700" for="assigned_to">Assigned to</label>
                    <select id="assigned_to" name="assigned_to" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Unassigned</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($formSubmission->assigned_to === $user->id)>{{ e($user->name) }}</option>
                        @endforeach
                    </select>
                </div> --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $formSubmission->notes) }}</textarea>
                </div>
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Save lead</button>
            </form>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Enquiry details</h2>
            <dl class="mt-3 divide-y divide-slate-100">
                @foreach ($formSubmission->data ?? [] as $key => $value)
                    <div class="py-3"><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ str($key)->replace(['_', '-'], ' ')->title() }}</dt><dd class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-800">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES) }}</dd></div>
                @endforeach
            </dl>
            <h3 class="mt-6 font-semibold">Delivery history</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Team email</dt><dd>{{ $formSubmission->email_sent_at ? 'Sent '.$formSubmission->email_sent_at->diffForHumans() : ($formSubmission->email_failed_at ? 'Failed' : 'Not sent') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Customer reply</dt><dd>{{ $formSubmission->autoresponder_sent_at ? 'Sent '.$formSubmission->autoresponder_sent_at->diffForHumans() : ($formSubmission->autoresponder_failed_at ? 'Failed' : 'Not sent') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Webhook</dt><dd>{{ $formSubmission->webhook_sent_at ? 'Sent '.$formSubmission->webhook_sent_at->diffForHumans() : ($formSubmission->webhook_failed_at ? 'Failed' : 'Not sent') }}</dd></div>
            </dl>
        </div>
    </div>

    <dialog data-confirm-action-dialog class="m-auto w-[min(30rem,calc(100%-2rem))] rounded-xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/50">
        <div class="p-5">
            <h2 data-confirm-action-title class="text-lg font-semibold text-slate-950">Confirm action</h2>
            <p data-confirm-action-message class="mt-2 text-sm text-slate-600"></p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" data-confirm-action-cancel class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" data-confirm-action-submit class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Confirm</button>
            </div>
        </div>
    </dialog>
</div>
@endsection
