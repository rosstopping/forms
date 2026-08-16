<x-mail::message>
# AI answer reported

{{ $websiteAiQuestion->user->name }} has reported an AI response for **{{ $websiteAiQuestion->website->name }}**.

**Question**

{{ $websiteAiQuestion->question }}

**Response**

{{ $websiteAiQuestion->answer ?: $websiteAiQuestion->error }}

@if ($websiteAiQuestion->report_reason)
**Why they reported it**

{{ $websiteAiQuestion->report_reason }}
@endif

@if ($websiteAiQuestion->failure_type)
**Failure diagnostic:** {{ $websiteAiQuestion->failure_type }} — {{ $websiteAiQuestion->failure_detail }}
@endif

<x-mail::button :url="$reviewUrl">
Review report
</x-mail::button>

Reference: WAI-{{ $websiteAiQuestion->id }}
</x-mail::message>
