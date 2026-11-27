<?php

return [
    // Enable only after the Digizu static-first adapter has been reviewed and installed.
    'url' => env('PUBLIC_WEBSITE_URL'),
    'production_host' => 'digizu.co.uk',
    'static_sitemap' => env('DIGIZU_STATIC_SITEMAP', '/home/forge/digizu.co.uk/current/_site/sitemap.xml'),
    'legacy_url' => env('PUBLIC_WEBSITE_LEGACY_URL', 'https://sitewell.digizu.co.uk'),
    'session_cookie' => '__Host-digizu_tools',
    'static_routes' => [
        'marketing.home' => '/',
        'marketing.free-site-audit.legacy' => '/get-started',
        'marketing.about' => '/about/',
        'marketing.contact' => '/contact/',
        'marketing.features' => '/services/',
        'marketing.pricing' => '/seo-doncaster/',
        'marketing.examples' => '/case-studies/',
        'marketing.faqs' => '/faq/',
        'marketing.journal' => '/guides/',
        'marketing.php-security-check' => '/php-security-checker/',
        'marketing.wordpress' => '/contact/',
        'marketing.sitemap' => '/tools-sitemap.xml',
    ],
];
