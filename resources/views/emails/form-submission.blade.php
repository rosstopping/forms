<x-email-layout>
<h1>New form submission</h1>
<p><strong>Website:</strong> {{ $submission->website->name }}</p>
<p><strong>Domain:</strong> {{ $submission->source_domain }}</p>
<p><strong>Form:</strong> {{ $submission->form->name }}</p>
<p><strong>Received:</strong> {{ $submission->created_at->toDayDateTimeString() }}</p>
<p><strong>Source URL:</strong> {{ $submission->source_url }}</p>
<p><strong>IP address:</strong> {{ $submission->ip_address }}</p>
<hr>
<h3>Submitted data</h3>
<ul>
@foreach ($submission->data as $key => $value)
<li><strong>{{ $key }}:</strong> {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (string) $value }}</li>
@endforeach
</ul>
<p><a class="button button-primary" href="{{ url('/admin/form-submissions/'.$submission->id) }}">View submission in the admin area</a></p>
<hr style="margin:24px 0; border:0; border-top:1px solid #e5e7eb;">
<p style="margin-bottom:8px; color:#59685f; font-size:14px;">Was this submission unwanted?</p>
<p style="margin:0;">
<a href="{{ $markAsSpamUrl }}" style="display:inline-block; border-radius:6px; background:#eef3ec; color:#315a46; padding:10px 16px; font-size:14px; font-weight:600; text-decoration:none;">Mark as spam</a>
</p>
</x-email-layout>
