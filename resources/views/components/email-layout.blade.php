@props(['linked' => true, 'brandName' => null])

@php
    $html = view('vendor.mail.html.layout', [
        'slot' => $slot,
        'markdown' => false,
        'brandName' => $brandName,
        'header' => view('vendor.mail.html.header', ['url' => $linked && $brandName === null ? route('marketing.home') : null, 'brandName' => $brandName])->render(),
        'footer' => $brandName === null ? view('vendor.mail.html.footer', ['linked' => $linked])->render() : '',
    ])->render();

    $styles = file_get_contents(resource_path('views/vendor/mail/html/themes/default.css'));
@endphp
{!! (new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles)->convert($html, $styles) !!}
