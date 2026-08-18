# SEO Opportunities Prospect Discovery

Last updated: 18 August 2026

## Current phase

All five planned phases are complete. The existing Outreach > Find Prospects page has two discovery modes:

- Local Businesses: the existing OpenStreetMap workflow, unchanged.
- SEO Opportunities: persisted keyword searches, queued organic SERP retrieval, domain deduplication, ranking evidence, search history, isolated candidate crawling/auditing, contact enrichment, page-count qualification, deterministic opportunity scoring, evidence-backed observations, result filters, manual import into Outreach, cached reruns, forced fresh reruns, freshness reporting, and cost estimates.

Suitable SEO candidates can now be selected and added to the existing Outreach workflow. Import prepares the existing analysis/draft flow and never sends an email; approval remains mandatory.

## Existing implementation

### OpenStreetMap finder

`ProspectDiscoveryController` creates a `ProspectDiscovery` owned by the admin and dispatches the unique `DiscoverProspects` job. `OpenStreetMapProspectFinder` queries Overpass, caches area/category results for seven days, and stores up to 50 `ProspectDiscoveryCandidate` rows keyed by the OpenStreetMap element ID.

The existing routes, tables, service, job, result screen, and import behavior were not replaced or refactored.

### Import into Outreach

`ImportProspectDiscoveryCandidatesController` locks selected candidate rows in a transaction. Website candidates use `Prospect::firstOrCreate()` by website URL; website-less listings create the existing website-opportunity prospect shape. New website prospects dispatch `AnalyzeProspect` after commit. Import only creates or links the existing `Prospect` model and never sends outreach.

### Contact enrichment and outreach

`ProspectWebsiteAnalyzer` performs the current prospect homepage analysis and delegates contact extraction to `ProspectContactFinder`. The existing `AnalyzeProspect` job prepares an outreach draft. Approval, suppression, duplicate-send protection, test email, and live sending remain in the existing Outreach workflow.

### Crawler and audits

`WebsiteCrawler` is the full multi-page crawler used by website health reports. It discovers sitemap and internal links, handles canonical/indexability signals, and respects configured page/depth limits. `WebsiteHealthAuditor` and the website health report models provide the deeper audit path. `ProspectWebsiteAnalyzer` is reusable for lightweight prospect checks and contact enrichment but is not a substitute for the requested page count and multi-page audit.

`WebsiteCrawler` now exposes a candidate-safe URL entry point that shares its existing SSRF checks, request limits, sitemap/link traversal, URL normalization, blocked paths, and page checks. Managed website crawling still retains its audit-history prioritisation. Candidate analysis does not create temporary `Website` records.

### Existing ranking integrations

Sitewell already uses DataForSEO for known-domain SEO snapshots through `DataForSEOClient`, `RankedKeywordsService`, and the SEO intelligence services. Those services answer “what does this known domain rank for?” They do not answer the query-first discovery question “which domains rank for this local keyword?”

Search Console opportunities are first-party data for managed websites and must not be mixed with prospect discovery estimates. Existing `SeoOpportunity` records are content recommendations for managed websites, not prospect candidates, so they are intentionally not reused for this feature.

## Phase 1 architecture decisions

### Separate search aggregate, shared Outreach destination

SEO discovery uses `SeoProspectSearch`, `SeoProspectCandidate`, and `SeoProspectRanking`. These are search/history/evidence records, not a second CRM or lead model. Selected qualified candidates create or link the existing `Prospect` exactly as the OpenStreetMap importer does.

### Provider boundary

`SerpProvider` is the application boundary. It returns a provider-neutral `SerpSearchResponse` containing typed `SerpResult` records and usage metadata. `DataForSEOSerpProvider` is the first implementation and uses the existing authenticated, retried, validated `DataForSEOClient`.

The provider stores organic position, URL, normalized domain, page title, description, website name, keyword, and check time. Paid and non-organic results are ignored.

### Domain deduplication

Candidates are unique by search and normalized domain. Hosts are lowercased and a leading `www.` is removed using the existing Sitewell URL normalization behavior. One candidate can therefore contain ranking evidence for several keywords. Existing Outreach prospects are detected by normalized host and linked for display rather than duplicated.

### Ranking thresholds

The search stores the requested minimum and maximum positions, defaulting to 20-100. Retrieval keeps all organic evidence up to the maximum depth. The minimum is not used as a hard rejection rule: a domain at position 12 for one term and positions 38 and 57 for others remains available for later visibility scoring.

### Idempotency and failure isolation

`DiscoverSeoProspects` is unique per search and uses `updateOrCreate` for both domains and keyword rankings. Each keyword request is isolated; one failed keyword is recorded without discarding successful keyword results. If every request fails, the job is retried and ultimately marks the search failed.

After discovery, one unique `AnalyzeSeoProspectCandidate` job is dispatched per deduplicated domain. Each job updates only its candidate, can be retried safely, and updates aggregate search progress under a database row lock. A terminal failure marks that candidate `analysis_failed` without failing sibling candidates or importing anything.

### Candidate analysis

Candidate crawling counts unique, successful, indexable canonical pages. Query-string duplicates, assets, admin/login/cart/feed paths, tag/author archives, obvious pagination, external links, duplicate canonicals, and noindex pages are excluded. The crawl is bounded at enough pages to classify the configured maximum and the 40+ unsuitable band.

Sites above the search's configurable page maximum are retained as `too_large`. Their page count and migration assessment are stored, but the heavier homepage audit and contact enrichment are skipped. Suitable sites reuse `ProspectWebsiteAnalyzer`, `WebsiteHealthAuditor` checks, and `ProspectContactFinder`; non-homepage crawler issues are retained with source URLs.

Migration difficulty is deterministic and stored with a reason:

- Easy: small brochure-style site with no detected complex paths.
- Medium: above the configured page limit, 21-40 pages, or booking/archive indicators.
- Hard: 40+ pages or ecommerce/account/membership indicators.
- Unknown: no indexable pages could be confirmed or analysis failed.

### Opportunity scoring and observations

Only candidates with a `suitable` qualification are scored. The deterministic 100-point score uses four stored-evidence components:

- Ranking visibility (40): the candidate's average stored organic position, normalized between the search's configured minimum and maximum positions. A result at or above the minimum receives 40; one at the maximum receives 0.
- Audit opportunity (25): the inverse of the stored audit score, scaled to 25 points. A lower current audit score represents more demonstrable improvement opportunity.
- Site fit (20): 20 points for 1-10 indexable pages, 15 for 11 pages through the configured maximum, and 0 when no suitable page count is available.
- Migration ease (15): Easy 15, Medium 8, Hard 2, and Unknown 0.

Each component stores its score, maximum, deterministic explanation, and supporting record or field references. Structured outreach observations are generated only from stored ranking rows, crawl observations, and audit findings. They retain ranking IDs or candidate JSON field/index references so later outreach drafting can trace every statement back to its source.

### Import into Outreach

Only manually selected, suitable candidates can be imported. The importer locks the search and candidates in a transaction, links an existing prospect by normalized domain where possible, creates only missing prospects, and stores the rankings, score breakdown, and observations as prospect activity evidence. Newly created prospects are queued for the existing analysis/draft preparation after commit. Import never sends outreach.

### Cost records, cache, and reruns

Fresh provider responses create an `ExternalApiUsage` row whose metadata contains the SEO search ID, keyword, and location. Cached responses cost zero and do not create a paid usage record. Estimated and actual costs, fresh/cached keyword totals, and per-keyword fetch times are stored and displayed.

SERPs are cached for seven days by normalized keyword, location, depth, language, and device. A rerun creates a separate dated search so previous evidence remains immutable. Admins can reuse eligible cached results or explicitly force fresh provider requests. No Google HTML is scraped.

## Database structure

### `seo_prospect_searches`

Stores owner, industry, location, service keywords, manually editable final keywords, ranking range, maximum pages, provider, status, counts, API cost, errors, and run timestamps.

### `seo_prospect_candidates`

Stores one normalized domain per search, its representative URL/name/location, an optional existing `prospect_id`, page count, existing audit score/findings, evidence-backed contact details, migration assessment/reason, crawl observations, qualification status, analysis errors, and completion time. Score breakdown remains reserved for Phase 3.

### `seo_prospect_rankings`

Stores candidate, keyword, organic position, ranking URL, title, description, and checked time. The candidate/keyword pair is unique and updated safely on rerun.

No new prospect, status enum, campaign, audit, or website table was created.

## SERP provider recommendation

Use DataForSEO initially because Sitewell already has its client, credentials, retries, error handling, SEO data conventions, and external cost ledger. Its Google Organic SERP endpoint supports depth 100 and returns all required organic fields. The provider contract keeps the feature replaceable by SerpAPI or another source later.

As of 18 August 2026, DataForSEO prices Google Organic SERPs per ten results:

- Live: $0.002 per ten results. Depth 100 is approximately $0.02 per keyword, or $0.10 for five keywords.
- Standard queue: $0.0006 per ten results. Depth 100 is approximately $0.006 per keyword, or $0.03 for five keywords.
- Priority queue: $0.0012 per ten results. Depth 100 is approximately $0.012 per keyword, or $0.06 for five keywords.

The implementation uses Live because the existing client is a synchronous POST client running inside a queued Sitewell job. Standard queue support was evaluated but would require a separate asynchronous task-posting and polling lifecycle. Seven-day provider-neutral caching now makes unchanged reruns cost-free while preserving the simpler and already-tested Live adapter. Standard queue support remains an optional future optimisation for always-fresh, high-volume workloads. Optional SERP parameters can add multipliers and are not enabled.

## External configuration

The existing `DATAFORSEO_LOGIN` and `DATAFORSEO_PASSWORD` values are required. No new provider key is required. The UI disables starting an SEO search when these credentials are absent.

Free-form UK place names are resolved against DataForSEO’s free Google locations endpoint and cached for 30 days. SERP requests use the resulting numeric location code while retaining the location in each final keyword.

## Completed work

- Added Local Businesses and SEO Opportunities modes to the existing Find Prospects page.
- Kept all OpenStreetMap behavior and import routes intact.
- Added SEO search history and read-only result detail.
- Added validated service/final keywords, ranking range, and maximum page settings.
- Added provider-neutral SERP contract and typed response data.
- Added DataForSEO Google Organic Live adapter.
- Added cached UK location-code resolution using DataForSEO’s free locations endpoint.
- Added unique queued discovery with per-keyword failure isolation.
- Added normalized-domain deduplication and per-keyword ranking evidence.
- Added existing Outreach prospect detection.
- Added provider cost/task usage records.
- Added focused coverage with a fake provider; tests do not make paid calls.
- Added one unique, retry-safe analysis job per candidate domain.
- Added candidate URL crawling without creating managed websites.
- Added canonical/indexable page counting and 1-10, 11-20, 21-40, and 40+ bands.
- Added oversized-site retention with audit/contact short-circuiting.
- Reused the existing prospect audit and contact enrichment for suitable sites.
- Added Easy/Medium/Hard/Unknown migration assessment with stored reasons.
- Added candidate analysis status, error, and aggregate search progress tracking.
- Added page count, audit findings, contacts, migration difficulty, and qualification to the results table.
- Added the deterministic 40/25/20/15 opportunity score and stored component explanations.
- Added evidence-referenced ranking, crawl, and audit outreach observations without AI-generated claims.
- Added qualification, migration, and minimum-score result filters.
- Added score details and default score-descending ordering to the results table.
- Added manual selection and transactional import of suitable candidates into Outreach.
- Added normalized-domain prospect deduplication and attached discovery evidence.
- Kept approval mandatory and dispatches prospect analysis only after commit.
- Added seven-day provider-neutral SERP caching and visible per-keyword freshness.
- Added immutable dated reruns with cached and force-fresh options.
- Added live cost estimates plus actual, fresh, and cached totals in search history.

## Remaining work

No work remains in the planned five-phase V1. DataForSEO Standard queue support is an optional future optimisation rather than a completion requirement because repeat searches already use the zero-cost cache.

## Architecture concerns

- Candidate crawling retains the existing crawler's DNS/private-network checks and disables redirect following before arbitrary discovered domains are fetched.
- SERP directories, social networks, aggregators, and national publishers will appear and need an explicit exclusion/qualification policy before analysis fan-out.
- Search depth 100 can return hundreds of domains across several keywords. Candidate analysis is fan-out queued and bounded; production Horizon concurrency remains the current throttle and should be monitored before increasing worker counts.
- Existing `SeoOpportunity` and `SearchOpportunity` names describe managed-site content opportunities. Keep prospect discovery naming distinct to avoid mixing third-party prospect evidence with managed-site recommendations.
- Ratings and reviews are optional for V1. Organic result rating fields may occasionally be present, but they are not reliable enough to make qualification depend on them.
