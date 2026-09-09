@props(['linked' => true])

@php
    $html = view('vendor.mail.html.layout', [
        'slot' => $slot,
        'markdown' => false,
        'header' => view('vendor.mail.html.header', ['url' => $linked ? route('marketing.home') : null])->render(),
        'footer' => view('vendor.mail.html.footer', ['linked' => $linked])->render(),
    ])->render();

    $styles = file_get_contents(resource_path('views/vendor/mail/html/themes/default.css'));
@endphp
{!! (new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles)->convert($html, $styles) !!}
