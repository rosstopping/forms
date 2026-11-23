<x-email-layout :brand-name="$submission->website->name">
<h1>New enquiry</h1>
@php
    $secondaryFields = ['gdpr', 'where_did_you_find_out_about_us'];
    $submittedData = collect($submission->data);
    $fieldLabel = fn ($key) => match ($key) {
        'choose_enquiry_type' => 'Enquiry type',
        'where_did_you_find_out_about_us' => 'Heard about us',
        'gdpr' => 'Privacy consent',
        default => ucfirst(str_replace('_', ' ', $key)),
    };
@endphp
@foreach ($submittedData->except($secondaryFields) as $key => $value)
<p style="margin-bottom:20px; overflow-wrap:anywhere; word-break:break-word;">
<strong>{{ $fieldLabel($key) }}</strong><br>
<span style="white-space:pre-line;">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $value }}</span>
</p>
@endforeach
<hr style="margin:28px 0; border:0; border-top:1px solid #ebe6e2;">
<h2 style="font-size:18px;">Submission details</h2>
<p style="font-size:14px; color:#62666d;">
<strong>Form:</strong> {{ $submission->form->name }}<br>
<strong>Received:</strong> {{ $submission->created_at->timezone('Europe/London')->format('j F Y, H:i T') }}<br>
<strong>Domain:</strong> {{ $submission->source_domain }}<br>
<strong>Source URL:</strong> <span style="overflow-wrap:anywhere; word-break:break-word;">{{ $submission->source_url }}</span><br>
<strong>IP address:</strong> {{ $submission->ip_address }}
@foreach ($submittedData->only($secondaryFields) as $key => $value)
<br><strong>{{ $fieldLabel($key) }}:</strong> {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $value }}
@endforeach
</p>
<p style="margin-top:24px; margin-bottom:8px; color:#62666d; font-size:14px;">Was this submission unwanted?</p>
<p style="margin:0; font-size:14px;"><a href="{{ $markAsSpamUrl }}">Mark as spam</a></p>
</x-email-layout>
