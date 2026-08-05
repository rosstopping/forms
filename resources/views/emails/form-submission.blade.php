<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Form submission received</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f6f8fb; padding:24px; color:#111827;">
    <div style="max-width:640px; margin:0 auto; background:white; border-radius:12px; padding:24px;">
        <h2 style="margin-top:0;">New form submission</h2>
        <p><strong>Website:</strong> {{ e($submission->website->name) }}</p>
        <p><strong>Domain:</strong> {{ e($submission->source_domain) }}</p>
        <p><strong>Form:</strong> {{ e($submission->form->name) }}</p>
        <p><strong>Received:</strong> {{ $submission->created_at->toDayDateTimeString() }}</p>
        <p><strong>Source URL:</strong> {{ e($submission->source_url) }}</p>
        <p><strong>IP address:</strong> {{ e($submission->ip_address) }}</p>
        <hr>
        <h3>Submitted data</h3>
        <ul>
            @foreach ($submission->data as $key => $value)
                <li><strong>{{ e($key) }}:</strong> {{ e(is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (string) $value) }}</li>
            @endforeach
        </ul>
        <p><a href="{{ url('/admin/form-submissions/'.$submission->id) }}">View submission in the admin area</a></p>
    </div>
</body>
</html>
