@props(['url', 'brandName' => null])
<tr>
<td class="header" bgcolor="#faf7f4">
@if ($brandName !== null)
<span class="brand">{{ $brandName }}</span>
@else
@php($url = \App\Support\PublicWebsite::origin() ?? $url)
@if ($url)<a href="{{ $url }}" class="brand">digizu<span class="brand-dot">.</span></a>
@else<span class="brand">digizu<span class="brand-dot">.</span></span>@endif
@endif
</td>
</tr>
