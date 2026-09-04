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

    public function pricing(): View
    {
        return view('marketing.pricing');
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
            'marketing.free-site-audit',
            'marketing.journal',
            'marketing.contact',
            'marketing.wordpress',
            'marketing.privacy',
            'marketing.terms',
        ])->map(fn (string $routeName): string => route($routeName))
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
                    ['heading' => 'Connect the signals that matter', 'body' => 'Start with the source repository, Search Console property, form routes, notification recipients, and the people responsible for decisions. Together they create a useful operating picture without asking the client to learn another technical workflow.'],
                    ['heading' => 'Agree what healthy means', 'body' => 'Decide how often the site should be checked, where enquiries should arrive, which changes need approval, and who receives reports. Clear defaults remove uncertainty while keeping clients in control.'],
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
                    ['heading' => 'Keep spam invisible to genuine visitors', 'body' => 'Honeypots and lightweight scoring stop most automated noise without adding friction. Suspected spam should remain available for review while staying out of inboxes and downstream systems.'],
                ],
            ],
            [
                'slug' => 'search-data-to-content-decisions',
                'category' => 'Search',
                'title' => 'Turn search data into the next useful improvement',
                'seo_title' => 'Turn search data into action',
                'excerpt' => 'Move beyond top-line clicks by connecting queries, ranking pages, health findings, and a reviewable content workflow.',
                'date' => '22 July 2026',
                'date_iso' => '2026-07-22',
                'read_time' => '7 min read',
                'sections' => [
                    ['heading' => 'Start with the page behind the query', 'body' => 'A ranking number becomes actionable when it is paired with the page Google is showing. That relationship reveals mismatched intent, competing pages, and opportunities to strengthen an existing answer.'],
                    ['heading' => 'Prioritise useful movement', 'body' => 'Queries sitting just outside the strongest positions often offer a clearer next step than broad traffic targets. Combine impressions, position, and business relevance before deciding what deserves attention.'],
                    ['heading' => 'Keep every change reviewable', 'body' => 'Recommendations should become clear repository changes, not invisible edits. A pull request keeps the agency or client in control and leaves a useful record of what changed and why.'],
                ],
            ],
        ];
    }
}
