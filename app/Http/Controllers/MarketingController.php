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
                'slug' => 'what-website-maintenance-actually-includes',
                'category' => 'Website care',
                'title' => 'What does website maintenance actually include?',
                'seo_title' => 'Website maintenance explained',
                'excerpt' => 'A practical guide to what website maintenance should cover for a small business, what hosting does not include, and how to tell whether your site is being properly looked after.',
                'date' => '31 August 2026',
                'date_iso' => '2026-08-31',
                'read_time' => '8 min read',
                'sections' => [
                    [
                        'heading' => 'Website maintenance is more than hosting and renewals',
                        'paragraphs' => [
                            'Many small businesses are told their website is “looked after” when what they really have is hosting, a domain renewal, and someone available if something goes badly wrong. That is useful, but it is not the same as ongoing maintenance.',
                            'Proper website maintenance means keeping the website dependable, checking that important journeys still work, and making sensible improvements over time. If your website helps generate enquiries, the standard should be closer to “is this still helping the business?” than “is the server still on?”',
                        ],
                    ],
                    [
                        'heading' => 'A maintained website should protect your enquiries',
                        'paragraphs' => [
                            'For many businesses, the contact form matters more than almost any other page feature. If enquiries are not reaching the right inbox, are buried in spam, or never receive a clear acknowledgement, the website can appear busy while the business quietly misses work.',
                            'Good maintenance includes checking the full enquiry journey, not just whether the submit button still appears on the page.',
                        ],
                        'points' => [
                            'Forms should submit reliably and show a clear success message.',
                            'Enquiries should reach the right person quickly.',
                            'Spam should be filtered without making genuine visitors jump through hoops.',
                            'Important submissions should stay visible in a system you can review, not only in one inbox.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.article',
                                'parameters' => ['forms-that-never-lose-a-lead'],
                                'label' => 'Read our guide to more reliable contact forms',
                                'description' => 'A practical checklist for form delivery, spam handling, and follow-up.',
                            ],
                        ],
                    ],
                    [
                        'heading' => 'Someone should be checking for quiet problems before customers find them',
                        'paragraphs' => [
                            'The most expensive website problems are often the ones nobody notices straight away. A page breaks after an update. A security certificate expires. A key page drops out of Google. An old plugin causes a vulnerability. None of those problems usually arrive with a helpful phone call.',
                            'Maintenance should include routine checks that catch issues early, while the fix is still small and before the business impact grows.',
                        ],
                        'points' => [
                            'Software and security updates',
                            'Backups and the ability to restore them',
                            'Broken pages, broken links, and obvious technical errors',
                            'Search visibility drops on important pages',
                            'Basic performance issues that make the website feel frustrating to use',
                        ],
                    ],
                    [
                        'heading' => 'Good maintenance also includes improvement',
                        'paragraphs' => [
                            'A website that never changes rarely keeps helping the business for long. Services evolve, competitors publish better pages, and Google starts showing different results for the searches that matter to you.',
                            'That does not mean publishing endless blog posts for the sake of it. It usually means spotting the page that is nearly doing the job, improving the answer, tightening the message, and making sure the next useful change is obvious.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.article',
                                'parameters' => ['search-data-to-content-decisions'],
                                'label' => 'See how search data can guide content improvements',
                                'description' => 'Use ranking and query data to decide which page is worth improving next.',
                            ],
                            [
                                'route' => 'marketing.features',
                                'label' => 'Explore how Sitewell handles health checks and search opportunities',
                            ],
                        ],
                    ],
                    [
                        'heading' => 'Ask what actually happens each month',
                        'paragraphs' => [
                            'If you are paying a monthly fee, you should be able to understand what that care includes in plain English. Vague reassurance is not the same as a process.',
                            'A good provider should be comfortable answering questions like these:',
                        ],
                        'points' => [
                            'What gets checked regularly?',
                            'How do you know if enquiries stop coming through?',
                            'Who is told when something needs attention?',
                            'What gets improved when the website is technically healthy but underperforming?',
                            'What happens if I need to move to another provider later?',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.article',
                                'parameters' => ['a-clean-website-handover'],
                                'label' => 'Read why a clean handover matters',
                                'description' => 'Ownership, access, and reporting should be clear before problems appear.',
                            ],
                        ],
                    ],
                    [
                        'heading' => 'When a business usually needs more than ad-hoc support',
                        'paragraphs' => [
                            'Ad-hoc support can be fine for a brochure site that is rarely updated and brings in little business. It becomes risky when the website is expected to generate regular enquiries, support local visibility, or represent a growing company properly.',
                            'If you are unsure whether your current setup is enough, start by checking whether the basics are being covered consistently. If they are not, a free website audit is usually a more useful next step than guessing.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.free-site-audit',
                                'label' => 'Run a free website audit',
                                'description' => 'Find the most important issues and next steps without committing to a rebuild.',
                            ],
                            [
                                'route' => 'marketing.pricing',
                                'label' => 'See how Sitewell structures ongoing website care',
                            ],
                        ],
                    ],
                ],
            ],
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
