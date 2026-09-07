<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Number;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MarketingController extends Controller
{
    public function home(): View
    {
        return view('marketing.home');
    }

    public function howItWorks(): View
    {
        return view('marketing.how-it-works');
    }

    public function features(): View
    {
        return view('marketing.features');
    }

    public function feature(string $feature): View
    {
        $featurePage = config("marketing.feature_pages.{$feature}");

        abort_unless(is_array($featurePage), 404);

        return view('marketing.feature', ['feature' => $featurePage]);
    }

    public function landing(string $landing): View
    {
        $landingPage = config("marketing.landing_pages.{$landing}");

        abort_unless(is_array($landingPage), 404);

        $faqSchema = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($landingPage['faqs'])->map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq[0],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq[1],
                ],
            ])->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        return view('marketing.landing', [
            'landing' => $landingPage,
            'faqSchema' => $faqSchema,
        ]);
    }

    public function pricing(): View
    {
        return view('marketing.pricing');
    }

    public function examples(): View
    {
        return view('marketing.examples');
    }

    public function comparison(): View
    {
        return view('marketing.comparison');
    }

    public function about(): View
    {
        return view('marketing.about');
    }

    public function faqs(): View
    {
        return view('marketing.faqs');
    }

    public function industry(string $industry): View
    {
        $industryPage = config("marketing.industries.{$industry}");

        abort_unless(is_array($industryPage), 404);

        return view('marketing.industry', ['industry' => $industryPage]);
    }

    public function journal(): View
    {
        return view('marketing.journal', ['articles' => $this->articles()]);
    }

    public function article(string $slug): View
    {
        $article = collect($this->articles())->firstWhere('slug', $slug);
        abort_unless($article, 404);

        return view('marketing.article', compact('article'));
    }

    public function contact(): View
    {
        return view('marketing.contact');
    }

    public function wordpress(): View
    {
        $pluginPath = $this->wordPressPluginPath();

        abort_unless(is_file($pluginPath), 404);

        return view('marketing.wordpress', [
            'pluginVersion' => '1.0.0',
            'pluginSize' => Number::fileSize(filesize($pluginPath)),
            'pluginChecksum' => hash_file('sha256', $pluginPath),
        ]);
    }

    public function downloadWordPressPlugin(): BinaryFileResponse
    {
        $pluginPath = $this->wordPressPluginPath();

        abort_unless(is_file($pluginPath), 404);

        return response()->download($pluginPath, 'sitewell-by-digizu.zip', [
            'Cache-Control' => 'no-store, private',
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function privacy(): View
    {
        return view('marketing.privacy-policy');
    }

    public function terms(): View
    {
        return view('marketing.terms-of-service');
    }

    public function sitemap(): Response
    {
        $urls = collect([
            'marketing.home',
            'marketing.how-it-works',
            'marketing.features',
            'marketing.pricing',
            'marketing.examples',
            'marketing.comparison',
            'marketing.about',
            'marketing.faqs',
            'marketing.free-site-audit',
            'marketing.journal',
            'marketing.contact',
            'marketing.wordpress',
            'marketing.privacy',
            'marketing.terms',
        ])->map(fn (string $routeName): string => route($routeName))
            ->merge(collect(array_keys(config('marketing.feature_pages')))->map(
                fn (string $feature): string => route('marketing.feature', $feature)
            ))
            ->merge(collect(array_keys(config('marketing.landing_pages')))->map(
                fn (string $landing): string => route('marketing.landing', $landing)
            ))
            ->merge(collect(array_keys(config('marketing.industries')))->map(
                fn (string $industry): string => route('marketing.industry', $industry)
            ))
            ->merge(collect($this->articles())->map(
                fn (array $article): string => route('marketing.article', $article['slug'])
            ));

        return response()
            ->view('marketing.sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml');
    }

    private function wordPressPluginPath(): string
    {
        return base_path('wordpress-plugin/sitewell-by-digizu.zip');
    }

    /** @return array<int, array<string, mixed>> */
    protected function articles(): array
    {
        return [
            [
                'slug' => 'a-clean-website-handover',
                'category' => 'Operations',
                'title' => 'A clean website handover is the start of good care',
                'seo_title' => 'A clean website handover',
                'excerpt' => 'The five connections and decisions that turn a finished build into a website your team can confidently support.',
                'date' => '8 August 2026',
                'date_iso' => '2026-08-08',
                'read_time' => '6 min read',
                'sections' => [
                    ['heading' => 'A launch is a beginning, not a finish line', 'body' => 'The fragile period for a website often starts just after launch. Forms need watching, search data needs time to settle, and small technical issues can quietly become expensive. A good handover makes ownership explicit before any of that happens.'],
                    ['heading' => 'Give one team the complete picture', 'body' => 'A specialist should understand the website, search performance, forms, notification recipients, and the people responsible for business decisions. That context makes ongoing management more useful and keeps technical work off the client’s desk.'],
                    ['heading' => 'Agree what healthy means', 'body' => 'Decide how often the site should be checked, where enquiries should arrive, which improvements matter most, and who receives reports. Clear expectations let the specialist team take responsibility while keeping clients informed.'],
                ],
            ],
            [
                'slug' => 'forms-that-never-lose-a-lead',
                'category' => 'Forms',
                'title' => 'Build forms that never leave a lead wondering',
                'seo_title' => 'Build forms that capture leads',
                'excerpt' => 'A practical checklist for reliable delivery, useful notifications, spam handling, and better follow-up.',
                'date' => '31 July 2026',
                'date_iso' => '2026-07-31',
                'read_time' => '5 min read',
                'sections' => [
                    ['heading' => 'Treat delivery as part of the experience', 'body' => 'A polished form is not complete when the button is pressed. Visitors need a clear success state, the right people need the submission promptly, and the data should be available when an inbox rule inevitably fails.'],
                    ['heading' => 'Name every intent', 'body' => 'A useful form name turns an anonymous submission into a clear request. Contact, quote, callback, brochure, and booking enquiries can then follow different notification and webhook paths without different endpoints.'],
                    ['heading' => 'Keep spam invisible to genuine visitors', 'body' => 'Good form management filters common spam without adding friction for genuine customers. Suspected submissions should remain available for review while staying out of the main lead inbox.'],
                ],
            ],
            [
                'slug' => 'search-data-to-content-decisions',
                'category' => 'Search',
                'title' => 'Turn search data into the next useful improvement',
                'seo_title' => 'Turn search data into action',
                'excerpt' => 'Move beyond top-line clicks by combining search queries, ranking pages, website findings, and specialist content decisions.',
                'date' => '22 July 2026',
                'date_iso' => '2026-07-22',
                'read_time' => '7 min read',
                'sections' => [
                    ['heading' => 'Start with the page behind the query', 'body' => 'A ranking number becomes actionable when it is paired with the page Google is showing. That relationship reveals mismatched intent, competing pages, and opportunities to strengthen an existing answer.'],
                    ['heading' => 'Prioritise useful movement', 'body' => 'Queries sitting just outside the strongest positions often offer a clearer next step than broad traffic targets. Combine impressions, position, and business relevance before deciding what deserves attention.'],
                    ['heading' => 'Turn analysis into managed improvement', 'body' => 'A useful SEO specialist does more than report an opportunity. The recommendation should become a considered website improvement, with a clear record of what changed and why it mattered.'],
                ],
            ],
        ];
    }
}
