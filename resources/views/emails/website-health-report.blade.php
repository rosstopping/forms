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
        @if (data_get($report->metrics, 'forms_count', 0) > 0)
            <h2 style="font-size:18px;">Forms in the last seven days</h2>
            <p>{{ data_get($report->metrics, 'legitimate_submissions', 0) }} legitimate submissions, {{ data_get($report->metrics, 'spam_submissions', 0) }} spam submissions, and {{ data_get($report->metrics, 'email_failures', 0) + data_get($report->metrics, 'webhook_failures', 0) }} delivery failures.</p>
        @endif
        @if (data_get($report->metrics, 'content_updates'))
            <h2 style="margin-top:24px;font-size:18px;">Content updates this week</h2>
            @foreach (data_get($report->metrics, 'content_updates', []) as $update)
                <div style="margin-top:12px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;">
                    <h3 style="margin:0;font-size:16px;">
                        @if ($showGithubLinks)
                            <a href="{{ $update['url'] }}" style="color:#0f172a;">{{ $update['title'] }}</a>
                        @else
                            {{ $update['title'] }}
                        @endif
                    </h3>
                    <p style="margin:6px 0;color:#64748b;font-size:13px;">Merged {{ \Illuminate\Support\Carbon::parse($update['merged_at'])->format('j M Y') }} · {{ number_format($update['changed_files']) }} files · <span style="color:#047857;">+{{ number_format($update['additions']) }}</span> <span style="color:#b91c1c;">−{{ number_format($update['deletions']) }}</span></p>
                    @if ($update['summary'])
                        <p style="margin:10px 0;white-space:pre-line;">{{ $update['summary'] }}</p>
                    @endif
                    @if ($update['files'])
                        <p style="margin:10px 0 4px;"><strong>Changed files</strong></p>
                        <ul style="margin:0;padding-left:20px;">
                            @foreach (array_slice($update['files'], 0, 5) as $file)
                                <li>{{ $file['name'] }} ({{ $file['status'] }})</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        @endif
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
        <p style="margin-top:24px;"><a href="{{ $reportUrl }}" style="display:inline-block;background:#0f172a;color:white;text-decoration:none;padding:10px 16px;border-radius:6px;">View the full report</a></p>
        <p style="color:#64748b;font-size:12px;">This secure report link works for 30 days and does not require you to log in.</p>
    </div>
</body>
</html>
