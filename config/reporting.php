<?php

return [
    'staff_only_reports' => env('SITEWELL_STAFF_REPORTING_ONLY', true),
    // Maps Sitewell website IDs to GA4 property IDs.
    'analytics_properties' => json_decode(env('SITEWELL_GA4_PROPERTIES', '{}'), true, flags: JSON_THROW_ON_ERROR),
];
