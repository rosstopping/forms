<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Website health report</title></head>
<body style="font-family:Arial,sans-serif;background:#f6f8fb;padding:24px;color:#111827;">
    <div style="max-width:640px;margin:0 auto;background:white;border-radius:12px;padding:24px;">
        <p style="font-size:12px;text-transform:uppercase;color:#64748b;">Weekly website health report</p>
        <h1 style="margin:4px 0 8px;font-size:24px;">{{ $report->website->name }}</h1>
        <p style="color:#475569;">Overall status: <strong>{{ str_replace('_', ' ', ucfirst($report->overall_status)) }}</strong></p>
        <p style="color:#475569;">{{ data_get($report->metrics, 'changes.new_issues', 0) }} new issues and {{ data_get($report->metrics, 'changes.resolved_issues', 0) }} resolved issues since the previous report.</p>
        <p style="color:#475569;"><strong>{{ data_get($report->metrics, 'pages_analyzed', 0) }} pages analysed</strong>, including titles, descriptions, headings, indexability, canonical URLs, content depth, links, and image accessibility.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;">
            <tr>
                <td style="padding:12px;background:#ecfdf5;color:#065f46;"><strong>{{ $report->passed_checks }}</strong><br>Passed</td>
                <td style="padding:12px;background:#fffbeb;color:#92400e;"><strong>{{ $report->warning_checks }}</strong><br>Warnings</td>
                <td style="padding:12px;background:#fef2f2;color:#991b1b;"><strong>{{ $report->failed_checks }}</strong><br>Failed</td>
            </tr>
        </table>
        <h2 style="font-size:18px;">Forms in the last seven days</h2>
        <p>{{ data_get($report->metrics, 'legitimate_submissions', 0) }} legitimate submissions, {{ data_get($report->metrics, 'spam_submissions', 0) }} spam submissions, and {{ data_get($report->metrics, 'email_failures', 0) + data_get($report->metrics, 'webhook_failures', 0) }} delivery failures.</p>
        @if (data_get($report->metrics, 'search_console'))
            <h2 style="margin-top:24px;font-size:18px;">Google Search Console</h2>
            <p style="color:#64748b;font-size:13px;">{{ \Illuminate\Support\Carbon::parse(data_get($report->metrics, 'search_console.period.start'))->format('j M') }}–{{ \Illuminate\Support\Carbon::parse(data_get($report->metrics, 'search_console.period.end'))->format('j M Y') }}</p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:12px;text-align:center;">
                <tr>
                    <td style="padding:12px;background:#f8fafc;"><strong>{{ number_format(data_get($report->metrics, 'search_console.totals.clicks', 0)) }}</strong><br>Clicks</td>
                    <td style="padding:12px;background:#f8fafc;"><strong>{{ number_format(data_get($report->metrics, 'search_console.totals.impressions', 0)) }}</strong><br>Impressions</td>
                    <td style="padding:12px;background:#f8fafc;"><strong>{{ number_format(data_get($report->metrics, 'search_console.totals.ctr', 0) * 100, 1) }}%</strong><br>CTR</td>
                    <td style="padding:12px;background:#f8fafc;"><strong>{{ number_format(data_get($report->metrics, 'search_console.totals.position', 0), 1) }}</strong><br>Position</td>
                </tr>
            </table>
            @if (data_get($report->metrics, 'search_console.queries'))
                <p style="margin-bottom:6px;"><strong>Top searches</strong></p>
                <ol style="margin-top:0;padding-left:22px;">
                    @foreach (array_slice(data_get($report->metrics, 'search_console.queries', []), 0, 5) as $query)
                        <li>{{ $query['query'] }} — {{ number_format($query['clicks']) }} clicks</li>
                    @endforeach
                </ol>
            @endif
        @endif
        <p style="margin-top:24px;"><a href="{{ route('admin.website-health-reports.show', [$report->website, $report]) }}" style="display:inline-block;background:#0f172a;color:white;text-decoration:none;padding:10px 16px;border-radius:6px;">View the full report</a></p>
    </div>
</body>
</html>
