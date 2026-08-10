<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Form submission received</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f6f8fb; padding:24px; color:#111827;">
    <div style="max-width:640px; margin:0 auto; background:white; border-radius:12px; padding:24px;">
        <h2 style="margin-top:0;">New form submission</h2>
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
        <p><a href="{{ url('/admin/form-submissions/'.$submission->id) }}">View submission in the admin area</a></p>
        <hr style="margin:24px 0; border:0; border-top:1px solid #e5e7eb;">
        <p style="margin-bottom:8px; color:#4b5563; font-size:14px;">Was this submission unwanted?</p>
        <p style="margin:0;">
            <a href="{{ $markAsSpamUrl }}" style="display:inline-block; border-radius:6px; background:#b91c1c; color:#ffffff; padding:10px 16px; font-size:14px; font-weight:bold; text-decoration:none;">Mark as spam</a>
        </p>
    </div>
</body>
</html>
