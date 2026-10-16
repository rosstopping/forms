@props(['noscript' => false])

@if (! $noscript)
    <script>
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('consent', 'default', {
            ad_storage: 'denied', ad_user_data: 'denied',
            ad_personalization: 'denied', analytics_storage: 'denied'
        });
        window.sitewellConsentDefaultsSet = true;
        // GTM-N36VDJNH is loaded by the consent controller only after acceptance.
    </script>
    @vite('resources/js/marketing-consent.js')
@endif
