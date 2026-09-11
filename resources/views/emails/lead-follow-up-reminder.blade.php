<x-mail::message>
# A lead is ready for follow-up

Your scheduled follow-up for **{{ $submission->displayName() }}** on **{{ $submission->website->name }}** is due.

**Scheduled for:** {{ $reminder->due_at->timezone(config('app.timezone'))->format('j M Y, H:i') }} ({{ config('app.timezone') }})

@if ($submission->messageExcerpt())
{{ $submission->messageExcerpt() }}
@endif

<x-mail::button :url="route('admin.form-submissions.show', $submission)">
View lead
</x-mail::button>

Sign in to Sitewell to follow up or schedule another reminder. This reminder has not been sent to the customer.
</x-mail::message>
