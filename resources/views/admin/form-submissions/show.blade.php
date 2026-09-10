@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $formSubmission->displayName() }}</h1>
            <p class="text-sm text-slate-600">{{ $formSubmission->replyToEmail() ?: 'No email address supplied' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($canManage)
                @unless ($formSubmission->is_spam)
                    <form method="POST" action="{{ route('admin.form-submissions.resend-notification', $formSubmission) }}" data-confirm-action-form>
                        @csrf
                        <button type="button" data-confirm-action data-confirm-title="Resend this email notification?" data-confirm-message="The original lead notification will be sent again to the form's configured recipients." data-confirm-label="Resend notification" class="rounded-md border border-blue-300 bg-white px-3 py-2 text-sm font-medium text-blue-800 hover:bg-blue-50">Resend email notification</button>
                    </form>
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
                <div class="flex justify-between"><dt class="text-slate-500">Source domain</dt><dd class="font-medium">{{ $formSubmission->source_domain ?: 'Unknown' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Source URL</dt><dd class="font-medium break-all">{{ $formSubmission->source_url ?: 'Unknown' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Form</dt><dd class="font-medium">{{ $formSubmission->form?->name ?: 'Unknown form' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Website</dt><dd class="font-medium">{{ $formSubmission->website?->name ?: 'Unknown website' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-medium"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $formSubmission->resolvedStatusLabel() }}</span></dd></div>
                {{-- <div class="flex justify-between"><dt class="text-slate-500">Owner</dt><dd class="font-medium">{{ $formSubmission->assignee?->name ?: 'Unassigned' }}</dd></div> --}}
                <div class="flex items-start justify-between gap-3"><dt class="text-slate-500">Tags</dt><dd class="flex flex-wrap justify-end gap-2">
                    @forelse ($formSubmission->tags as $tag)
                        <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-800">{{ $tag->name }}</span>
                    @empty
                        <span class="text-slate-500">No tags</span>
                    @endforelse
                </dd></div>
            </dl>

            @if ($canManage)
            <form method="POST" action="{{ route('admin.form-submissions.update', $formSubmission) }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="status">Status</label>
                    <select id="status" name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach (\App\Models\FormSubmission::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected($formSubmission->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- <div>
                    <label class="block text-sm font-medium text-slate-700" for="assigned_to">Assigned to</label>
                    <select id="assigned_to" name="assigned_to" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Unassigned</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($formSubmission->assigned_to === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div> --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="follow_up_at">Follow up</label>
                    <input id="follow_up_at" name="follow_up_at" type="datetime-local" value="{{ old('follow_up_at', $formSubmission->follow_up_at?->format('Y-m-d\\TH:i')) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Setting or changing this date schedules one email to the eligible assignee, or otherwise the website owner. Leave blank to cancel it.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $formSubmission->notes) }}</textarea>
                </div>
                <fieldset>
                    <legend class="text-sm font-medium text-slate-700">Lead tags</legend>
                    <input type="hidden" name="tags_present" value="1">
                    <p class="mt-1 text-xs text-slate-500">Select tags to keep, or untick them to remove. Tags are shared across this website.</p>
                    @php
                        $selectedTagIds = old('tag_ids', old('tags_present') ? [] : $formSubmission->tags->modelKeys());
                        $selectedTagIds = is_array($selectedTagIds) ? $selectedTagIds : [];
                    @endphp
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($leadTags as $tag)
                            <label class="flex min-h-11 items-center gap-2 rounded-md border border-slate-200 px-3 text-sm text-slate-700">
                                <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTagIds)) class="rounded border-slate-300">{{ $tag->name }}
                            </label>
                        @endforeach
                    </div>
                    <label for="new_tag" class="mt-3 block text-sm font-medium text-slate-700">New tag</label>
                    <input id="new_tag" name="new_tag" value="{{ is_string(old('new_tag')) ? old('new_tag') : '' }}" maxlength="40" placeholder="e.g. Quote requested" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Added to this lead when you save. Up to 40 characters.</p>
                    @error('new_tag')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    @error('tag_ids')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    @foreach ($errors->get('tag_ids.*') as $messages)
                        @foreach ($messages as $message)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@endforeach
                    @endforeach
                </fieldset>
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Save lead</button>
            </form>
            @else
                <dl class="mt-5 space-y-3 text-sm">
                    <div><dt class="font-medium text-slate-700">Follow up</dt><dd class="mt-1 text-slate-600">{{ $formSubmission->follow_up_at?->format('j M Y, H:i') ?? 'Not scheduled' }}</dd></div>
                    <div><dt class="font-medium text-slate-700">Notes</dt><dd class="mt-1 whitespace-pre-line text-slate-600">{{ $formSubmission->notes ?: 'No notes' }}</dd></div>
                </dl>
            @endif
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
            @error('email_notification')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </div>

    <section class="rounded-lg border bg-white p-4 shadow-sm" aria-labelledby="review-invitation-title">
        <h2 id="review-invitation-title" class="font-semibold">Customer review invitation</h2>
        <p class="mt-1 text-sm text-slate-600">After marking work completed, invite the customer to share an honest review. One invitation per lead; no automatic reminders.</p>
        @if ($canManage)
            <details class="mt-4 rounded-md border border-slate-200 p-3" @if (! $formSubmission->website->review_url || $errors->has('review_url')) open @endif>
                <summary class="cursor-pointer text-sm font-medium text-slate-700">Website review link</summary>
                <form method="POST" action="{{ route('admin.form-submissions.review-link', $formSubmission) }}" class="mt-3 space-y-2">
                    @csrf @method('PUT')
                    <label for="review_url" class="block text-sm text-slate-700">Direct link to leave a review</label>
                    <input type="url" id="review_url" name="review_url" value="{{ is_string(old('review_url', $formSubmission->website->review_url)) ? old('review_url', $formSubmission->website->review_url) : '' }}" maxlength="2048" placeholder="https://…" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="text-xs text-slate-500">Use your Google review link or another review platform. This setting is shared by all leads on {{ $formSubmission->website->name }}. Leave blank to disable new invitations.</p>
                    @error('review_url')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">Save review link</button>
                </form>
            </details>
        @endif
        @if ($invitation = $formSubmission->reviewInvitation)
            <div class="mt-4 rounded-md bg-slate-50 p-4">
                <h3 class="text-sm font-semibold">Invitation history</h3>
                <dl class="mt-2 space-y-2 text-sm">
                    <div><dt class="inline text-slate-500">Status:</dt> <dd class="inline font-medium">{{ ucfirst($invitation->status) }}</dd></div>
                    <div><dt class="inline text-slate-500">To:</dt> <dd class="inline">{{ $invitation->recipient }}</dd></div>
                    <div><dt class="inline text-slate-500">Requested:</dt> <dd class="inline">{{ $invitation->created_at->format('j M Y, H:i') }} by {{ $invitation->requester?->name ?? 'Former website manager' }}</dd></div>
                    @if ($invitation->sent_at)<div><dt class="inline text-slate-500">Sent:</dt> <dd class="inline">{{ $invitation->sent_at->format('j M Y, H:i') }}</dd></div>@endif
                    @if ($invitation->failed_at)<div><dt class="inline text-slate-500">Failed:</dt> <dd class="inline">{{ $invitation->failed_at->format('j M Y, H:i') }}. Sending could not be confirmed. Contact support before trying again.</dd></div>@endif
                    @if ($invitation->cancelled_at)<div><dt class="inline text-slate-500">Cancelled:</dt> <dd class="inline">{{ $invitation->cancelled_at->format('j M Y, H:i') }}. {{ $invitation->error }}</dd></div>@endif
                </dl>
                <details class="mt-3 text-sm"><summary class="cursor-pointer font-medium">View invitation</summary>
                    <p class="mt-2 font-medium">{{ $invitation->subject }}</p>
                    <p class="mt-2 whitespace-pre-line">{{ $invitation->body }}</p>
                    <a href="{{ $invitation->review_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block break-all underline">{{ $invitation->review_url }}</a>
                </details>
                <p class="mt-3 text-xs text-slate-500">Sent means the email service accepted the invitation. Review submissions are not tracked.</p>
            </div>
        @elseif ($canManage && ! $reviewUnavailableReason)
            <details class="mt-4 rounded-md border border-slate-200 p-4" @if ($errors->has('review_invitation') || $errors->has('preview_hash')) open @endif>
                <summary class="cursor-pointer text-sm font-medium">Preview review invitation</summary>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="inline text-slate-500">To:</dt> <dd class="inline">{{ $reviewPreview['recipient'] }}</dd></div>
                    <div><dt class="inline text-slate-500">From:</dt> <dd class="inline">{{ $reviewPreview['from_name'] }} &lt;{{ $reviewPreview['from_email'] }}&gt;</dd></div>
                    <div><dt class="inline text-slate-500">Subject:</dt> <dd class="inline">{{ $reviewPreview['subject'] }}</dd></div>
                </dl>
                <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $reviewPreview['body'] }}</p>
                <a href="{{ $reviewPreview['review_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block break-all text-sm underline">Leave an honest review: {{ $reviewPreview['review_url'] }}</a>
                <p class="mt-2 text-xs text-slate-500">Sent via Sitewell.</p>
                <form method="POST" action="{{ route('admin.form-submissions.review-invitations.store', $formSubmission) }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="preview_hash" value="{{ $reviewPreviewHash }}">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Send review invitation</button>
                </form>
            </details>
        @elseif ($canManage)
            <p class="mt-4 text-sm text-slate-600">{{ $reviewUnavailableReason }}</p>
        @else
            <p class="mt-4 text-sm text-slate-500">No review invitation has been sent.</p>
        @endif
        @error('review_invitation')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        @error('preview_hash')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
    </section>

    <div class="rounded-lg border bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Activity</h2>
        <div class="mt-4 flow-root">
            <ol class="space-y-4">
                @forelse ($formSubmission->activities as $activity)
                    <li class="flex gap-3">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-slate-400"></span>
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800">{{ $activity->description }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $activity->user?->name ?: 'System' }} · {{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No activity recorded yet.</li>
                @endforelse
            </ol>
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
