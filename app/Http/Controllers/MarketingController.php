<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Number;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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
            'pluginVersion' => $this->wordPressPluginMetadata()['version'],
            'pluginSize' => Number::fileSize(filesize($pluginPath)),
            'pluginChecksum' => hash_file('sha256', $pluginPath),
        ]);
    }

    public function downloadWordPressPlugin(Request $request): BinaryFileResponse
    {
        $pluginPath = $this->wordPressPluginPath();

        abort_unless(is_file($pluginPath), 404);

        if ($request->has('version')) {
            abort_unless($request->query('version') === $this->wordPressPluginMetadata()['version'], 409, 'This release is no longer current. Check for updates again.');
        }

        return response()->download($pluginPath, 'sitewell-by-digizu.zip', [
            'Cache-Control' => 'no-store, private',
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function wordPressPluginUpdate(): JsonResponse
    {
        $metadata = $this->wordPressPluginMetadata();

        return response()->json($metadata + [
            'package' => route('marketing.wordpress.download', ['version' => $metadata['version']]),
            'sha256' => hash_file('sha256', $this->wordPressPluginPath()),
        ])->header('Cache-Control', 'no-store');
    }

    /** @return array{version: string, requires: string, requires_php: string, tested: string} */
    private function wordPressPluginMetadata(): array
    {
        $archive = new ZipArchive;
        abort_unless(is_file($this->wordPressPluginPath()) && $archive->open($this->wordPressPluginPath()) === true, 503);
        try {
            $header = $archive->getFromName('sitewell-by-digizu/sitewell-static-frontend.php');
            $readme = $archive->getFromName('sitewell-by-digizu/readme.txt');
        } finally {
            $archive->close();
        }
        abort_unless(is_string($header) && is_string($readme), 503);
        $metadata = [];
        foreach (['version' => 'Version', 'requires' => 'Requires at least', 'requires_php' => 'Requires PHP', 'tested' => 'Tested up to'] as $key => $label) {
            abort_unless(preg_match('/^\s*(?:\*\s*)?'.preg_quote($label, '/').':\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?)/m', $key === 'tested' ? $readme : $header, $matches) === 1, 503);
            $metadata[$key] = $matches[1];
        }

        return $metadata;
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
                'slug' => 'why-isnt-my-website-showing-on-google',
                'category' => 'Search',
                'title' => 'Why isn\'t my website showing on Google?',
                'seo_title' => 'Website not showing on Google?',
                'excerpt' => 'A practical guide to diagnosing when a business website is missing from Google, including the difference between indexing issues and low rankings.',
                'date' => '18 September 2026',
                'date_iso' => '2026-09-18',
                'read_time' => '8 min read',
                'sections' => [
                    [
                        'heading' => 'First, separate two different problems',
                        'paragraphs' => [
                            'When a business owner says their website is not showing on Google, it usually means one of two things: Google has not indexed the page, or the page exists in Google but ranks too low to be seen.',
                            'Those are different problems and need different fixes. A clear diagnosis saves time and prevents unnecessary rebuilds.',
                        ],
                    ],
                    [
                        'heading' => 'Problem A: The page is not indexed',
                        'paragraphs' => [
                            'If a page is not indexed, Google has not added it to searchable results yet. In that case, ranking work will not help until indexing is resolved.',
                            'A practical first check is searching Google for site:yourdomain.co.uk plus a key page topic. If the page does not appear at all, start with indexing and crawl checks.',
                        ],
                        'points' => [
                            'Check whether the page returns properly and is not broken or blocked.',
                            'Check whether the page is linked from other pages on your website.',
                            'Check whether important pages were accidentally set to noindex during a redesign or migration.',
                        ],
                    ],
                    [
                        'heading' => 'Problem B: The page is indexed but ranking poorly',
                        'paragraphs' => [
                            'If your page appears in Google but far down the results, the challenge is usually relevance, quality, and competition rather than indexing.',
                            'This is where service-page clarity, stronger local context, and regular page improvements tend to make the biggest difference.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.article',
                                'parameters' => ['why-isnt-my-website-ranking-on-google'],
                                'label' => 'Read our guide on why websites struggle to rank',
                                'description' => 'Useful when pages are indexed but not earning visible positions.',
                            ],
                        ],
                    ],
                    [
                        'heading' => 'A simple diagnosis workflow for business owners',
                        'paragraphs' => [
                            'Work through your key service pages one by one rather than trying to fix everything at once.',
                        ],
                        'points' => [
                            'List three to five services that should bring enquiries.',
                            'Check whether each service page is indexed.',
                            'Search the service and location terms your customers would actually use.',
                            'Note whether Google shows the right page, the wrong page, or no page.',
                            'Prioritise fixes on pages tied to commercial enquiries first.',
                        ],
                    ],
                    [
                        'heading' => 'Common reasons visibility drops after a website change',
                        'paragraphs' => [
                            'A site can disappear from expected searches after platform changes, URL changes, or content rewrites. Often this happens because old pages were removed without proper redirects or new pages became harder for Google to understand.',
                            'If traffic has also dropped, it is worth checking broader causes before assuming one technical fault explains everything.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.article',
                                'parameters' => ['why-has-my-website-traffic-dropped'],
                                'label' => 'Read the full guide to diagnosing traffic drops',
                            ],
                        ],
                    ],
                    [
                        'heading' => 'What to do next if you are still unsure',
                        'paragraphs' => [
                            'If you cannot tell whether the issue is indexing, ranking, or both, use an audit process that prioritises what affects enquiries first.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.landing',
                                'parameters' => ['managed-seo-services'],
                                'label' => 'Explore managed SEO services',
                            ],
                            [
                                'route' => 'marketing.free-site-audit',
                                'label' => 'Start a free website audit',
                                'description' => 'Get a practical diagnosis and clear next actions.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'why-has-my-website-traffic-dropped',
                'category' => 'Search',
                'title' => 'Why has my website traffic dropped?',
                'seo_title' => 'Why website traffic drops',
                'excerpt' => 'A practical explanation of why website traffic can fall, from algorithm shifts and ranking losses to tracking errors, seasonality, and content decay.',
                'date' => '18 September 2026',
                'date_iso' => '2026-09-18',
                'read_time' => '10 min read',
                'sections' => [
                    [
                        'heading' => 'Traffic drops are rarely caused by one thing',
                        'paragraphs' => [
                            'A traffic dip feels urgent, but the cause is not always obvious. Many businesses lose visits because several smaller issues stack up at the same time.',
                            'Treat this as a diagnosis exercise: isolate what changed, which pages were affected, and whether lost traffic also reduced enquiries.',
                        ],
                    ],
                    [
                        'heading' => 'Cause 1: Search results changed after an algorithm update',
                        'paragraphs' => [
                            'Google regularly changes how it evaluates pages. Some updates favour clearer, more useful pages and can reduce visibility for pages that became thin, outdated, or less relevant than competitors.',
                            'Do not panic and rewrite everything. Start with the pages that lost the most important commercial queries.',
                        ],
                    ],
                    [
                        'heading' => 'Cause 2: Ranking losses on key pages',
                        'paragraphs' => [
                            'Small ranking movements on high-impression queries can create large traffic drops. If your page moved from lower page one to page two, clicks can fall quickly even though the page still appears in results.',
                            'This is often a page-improvement problem, not a full-site rebuild problem.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.landing',
                                'parameters' => ['seo-for-small-businesses'],
                                'label' => 'See how ongoing SEO improvements are prioritised',
                            ],
                        ],
                    ],
                    [
                        'heading' => 'Cause 3: Technical problems that block or weaken pages',
                        'paragraphs' => [
                            'Broken pages, redirect mistakes, accidental noindex tags, and major performance problems can all reduce search visibility.',
                            'A common pattern is that traffic declines after website changes where important pages were moved, renamed, or replaced without careful checks.',
                        ],
                    ],
                    [
                        'heading' => 'Cause 4: Seasonality, demand shifts, or market events',
                        'paragraphs' => [
                            'Not every decline is a website fault. Some industries have predictable quiet periods, and some demand changes are driven by economic or local events.',
                            'Compare year-on-year periods and enquiry quality before assuming your SEO has failed.',
                        ],
                    ],
                    [
                        'heading' => 'Cause 5: Tracking problems, not real demand loss',
                        'paragraphs' => [
                            'Analytics setup issues can make traffic look worse than it is. Tag changes, broken scripts, consent-mode changes, and property misconfiguration can all distort reporting.',
                            'Always check whether form submissions and calls dropped as well. If leads remained stable, measurement could be part of the issue.',
                        ],
                    ],
                    [
                        'heading' => 'Cause 6: Competitors improved while your pages stood still',
                        'paragraphs' => [
                            'Competitors who keep refining their pages can overtake businesses with static websites. This is especially common in local services where multiple businesses offer similar work.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.landing',
                                'parameters' => ['local-seo-services'],
                                'label' => 'Understand how local SEO improvements compound over time',
                            ],
                        ],
                    ],
                    [
                        'heading' => 'Cause 7: Content decay on once-useful pages',
                        'paragraphs' => [
                            'Pages that previously performed can decline when details become outdated, examples no longer match your offer, or competitors provide clearer answers.',
                            'Refreshing proven pages is often more effective than publishing unrelated new posts.',
                        ],
                    ],
                    [
                        'heading' => 'A practical recovery checklist',
                        'paragraphs' => [
                            'Use this order to avoid wasting time:',
                        ],
                        'points' => [
                            'Confirm the drop in both Search Console and analytics.',
                            'Identify which pages and queries lost clicks first.',
                            'Check indexing and technical health on affected pages.',
                            'Review competitor pages now outranking yours.',
                            'Update the highest-impact pages before creating new ones.',
                        ],
                    ],
                    [
                        'heading' => 'When to bring in outside support',
                        'paragraphs' => [
                            'If the cause is still unclear after these checks, a focused audit can separate urgent fixes from longer-term improvements.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.free-site-audit',
                                'label' => 'Start a free website audit',
                                'description' => 'Get a practical diagnosis of visibility, content, and lead-path issues.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'why-isnt-my-website-ranking-on-google',
                'category' => 'Search',
                'title' => 'Why isn\'t my website ranking on Google?',
                'seo_title' => 'Why your website is not ranking',
                'excerpt' => 'A practical guide for small businesses on why pages struggle in Google, what to check first, and what to fix before spending more on marketing.',
                'date' => '16 September 2026',
                'date_iso' => '2026-09-16',
                'read_time' => '9 min read',
                'sections' => [
                    [
                        'heading' => 'Ranking problems are usually a combination, not one single issue',
                        'paragraphs' => [
                            'Most small-business websites do not fail in Google because of one technical mistake. More often, a page underperforms because several smaller problems add up: unclear service messaging, weak local relevance, outdated content, and a website that is not reviewed often enough.',
                            'The good news is that this can be diagnosed. You do not need to guess or rebuild everything straight away. Start by finding where the mismatch sits between what people search for and what your key pages currently show.',
                        ],
                    ],
                    [
                        'heading' => 'Reason 1: The page does not clearly answer the search',
                        'paragraphs' => [
                            'If someone searches for a specific service, Google wants to show a page that clearly explains that exact service, who it is for, and what the next step is. A broad homepage often cannot do that job on its own.',
                            'Pages that rank better usually provide direct answers, practical detail, and a clear next action instead of vague sales language.',
                        ],
                        'points' => [
                            'Make sure each core service has its own dedicated page.',
                            'Use headings that match what customers actually ask.',
                            'Add specific proof and practical detail, not only general claims.',
                        ],
                    ],
                    [
                        'heading' => 'Reason 2: Local relevance is too weak',
                        'paragraphs' => [
                            'For businesses that rely on local customers, Google needs clear local signals. If your website and profile details are inconsistent, or your pages never mention the areas you genuinely serve, competitors with stronger local context can outrank you.',
                            'Local SEO is not only a Google Business Profile task. It also depends on whether your website pages support the same locations and services.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.landing',
                                'parameters' => ['local-seo-services'],
                                'label' => 'See how local SEO services connect page content, Google visibility and local search',
                            ],
                        ],
                    ],
                    [
                        'heading' => 'Reason 3: The website has hidden quality or trust issues',
                        'paragraphs' => [
                            'Pages can struggle when contact routes are unreliable, content is outdated, or important trust information is missing. These issues affect both customer confidence and search performance over time.',
                            'You do not need every metric to be perfect, but your key pages should be accurate, usable on mobile, and easy for a potential customer to act on.',
                        ],
                        'points' => [
                            'Check that your contact form actually delivers submissions.',
                            'Update outdated service details, opening hours, and pricing context where relevant.',
                            'Remove or fix broken pages and links on core customer journeys.',
                        ],
                    ],
                    [
                        'heading' => 'Reason 4: No ongoing content or optimisation process',
                        'paragraphs' => [
                            'Google results change constantly. If your website has not been improved for months, it can slowly lose ground to competitors who keep refining their pages.',
                            'Ongoing SEO usually means improving existing pages first, then adding focused new content only where a real search gap exists.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.landing',
                                'parameters' => ['seo-for-small-businesses'],
                                'label' => 'Read how ongoing SEO for small businesses is prioritised',
                            ],
                        ],
                    ],
                    [
                        'heading' => 'A practical diagnosis checklist before you spend more',
                        'paragraphs' => [
                            'Before investing in more ads or commissioning a full redesign, run a basic diagnosis on the pages that matter most.',
                        ],
                        'points' => [
                            'Search for your main service + location and note which page Google currently shows (if any).',
                            'Check whether that page clearly explains the service, location and next step.',
                            'Confirm your contact route works and enquiries are visible after submission.',
                            'Review whether your Google profile details and website details match.',
                            'List the pages that have not been updated in the last six months.',
                        ],
                    ],
                    [
                        'heading' => 'When to use a professional website audit',
                        'paragraphs' => [
                            'If you can see something is wrong but cannot confidently prioritise what to fix first, a structured audit is usually the quickest way to avoid wasted effort.',
                            'Sitewell’s free website audit highlights practical issues across visibility, page quality and enquiry handling so you can make an informed next decision.',
                        ],
                        'links' => [
                            [
                                'route' => 'marketing.free-site-audit',
                                'label' => 'Start a free website audit',
                                'description' => 'Get a practical starting point before committing to wider SEO or website changes.',
                            ],
                            [
                                'route' => 'marketing.landing',
                                'parameters' => ['managed-seo-services'],
                                'label' => 'Explore managed SEO services',
                            ],
                        ],
                    ],
                ],
            ],
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
                            [
                                'route' => 'marketing.article',
                                'parameters' => ['why-isnt-my-website-ranking-on-google'],
                                'label' => 'Read our guide to diagnosing ranking problems',
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
                                'route' => 'marketing.landing',
                                'parameters' => ['website-maintenance-packages'],
                                'label' => 'Explore our website maintenance packages',
                                'description' => 'See how Sitewell approaches ongoing website care for small businesses.',
                            ],
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
                    [
                        'heading' => 'Turn analysis into managed improvement',
                        'body' => 'A useful SEO specialist does more than report an opportunity. The recommendation should become a considered website improvement, with a clear record of what changed and why it mattered.',
                        'links' => [
                            [
                                'route' => 'marketing.landing',
                                'parameters' => ['seo-for-small-businesses'],
                                'label' => 'See how Sitewell handles SEO for small businesses',
                                'description' => 'A practical view of ongoing SEO work, measurable movement, and commercially useful priorities.',
                            ],
                            [
                                'route' => 'marketing.landing',
                                'parameters' => ['managed-seo-services'],
                                'label' => 'Explore managed SEO services',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
