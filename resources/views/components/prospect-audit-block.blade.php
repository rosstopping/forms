@props(['url', 'hasVideo' => false])

<div style="margin:28px 0 0;border:1px solid #dce3dd;border-radius:12px;background:#eef3ec;padding:20px;">
<p style="margin:0;font-size:18px;font-weight:600;color:#315a46;">Your website audit</p>
<p style="margin:6px 0 16px;font-size:14px;color:#59685f;">See the public website checks and opportunities behind this email.</p>
<a href="{{ $url }}" class="button {{ $hasVideo ? 'button-secondary' : 'button-primary' }}">View your website audit</a>
<p style="margin:14px 0 0;font-size:14px;color:#59685f;">This private link is available for 30 days.</p>
</div>
