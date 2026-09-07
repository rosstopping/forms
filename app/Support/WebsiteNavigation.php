<?php

namespace App\Support;

use App\Models\Website;
use Illuminate\Http\Request;

class WebsiteNavigation
{
    public const DEFAULT_SECTION = 'health';

    public const SECTIONS = [
        'health',
        'search',
        'seo',
        'content',
        'wordpress',
        'pixel',
        'business-profile',
        'forms',
        'leads',
        'settings',
    ];

    public static function sectionForRequest(Request $request): string
    {
        $routeSection = $request->route('section');

        if (is_string($routeSection) && in_array($routeSection, self::SECTIONS, true)) {
            return $routeSection;
        }

        $querySection = $request->query('tab');

        if (is_string($querySection) && in_array($querySection, self::SECTIONS, true)) {
            return $querySection;
        }

        return match (true) {
            $request->routeIs('admin.website-health-reports.*', 'admin.website-health-report-pages.*') => 'health',
            $request->routeIs('admin.search-console.*', 'admin.search-opportunities.*') => 'search',
            $request->routeIs('admin.seo-*') => 'seo',
            $request->routeIs('admin.forms.*') => 'forms',
            $request->routeIs('admin.form-submissions.*') => 'leads',
            default => self::DEFAULT_SECTION,
        };
    }

    public static function routeFor(Website $website, string $section): string
    {
        if ($section === 'leads') {
            return route('admin.form-submissions.index');
        }

        return route('admin.websites.section', [
            'website' => $website,
            'section' => in_array($section, self::SECTIONS, true) ? $section : self::DEFAULT_SECTION,
        ]);
    }
}
