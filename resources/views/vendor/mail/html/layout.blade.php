<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ $brandName ?? 'Sitewell' }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 620px) {
.outer-cell { padding: 12px 8px !important; }
.inner-body, .footer { width: 100% !important; }
.content-cell, .header { padding: 28px 22px !important; }
.metrics td { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: left !important; }
.content-cell p, .content-cell li, .content-cell td { font-size: 16px !important; }
}
</style>
{!! $head ?? '' !!}
</head>
<body>
<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr><td class="outer-cell" align="center">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
<table class="inner-body" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}
<tr><td class="content-cell">
@if ($markdown ?? true)
{!! Illuminate\Mail\Markdown::parse($slot) !!}
@else
{!! $slot !!}
@endif
{!! $subcopy ?? '' !!}
</td></tr>
</table>
{!! $footer ?? '' !!}
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
