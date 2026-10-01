<x-mail::message>
# New search audit request

{{ $audit->email }} asked for the report for {{ $audit->domain }}.

<x-mail::button :url="$onboardingUrl">
Open in Onboarding
</x-mail::button>

[View the report]({{ $reportUrl }})
</x-mail::message>
