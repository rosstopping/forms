<x-mail::message>
# New search audit request

{{ $audit->email }} asked for the report for {{ $audit->domain }}.

@if ($audit->personal_review_requested_at)
**Personal review due:** {{ $audit->personal_review_due_at->timezone(config('app.timezone'))->format('j F Y, H:i') }}.

Record a personal Loom video review and send its share link from Onboarding. The email asks whether they want enquiries, bookings or sales. When they reply, explain how Sitewell could help, give an indication of price, and invite them to a call.

**Ongoing advice:** {{ $audit->marketing_consent_at ? 'Opted in to website advice and follow-up ('.$audit->marketing_consent_version.').' : 'No marketing opt-in. Respond only to their requested review and replies.' }}
@endif

<x-mail::button :url="$onboardingUrl">
Open in Onboarding
</x-mail::button>

[View the report]({{ $reportUrl }})
</x-mail::message>
