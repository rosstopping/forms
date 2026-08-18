# SEO Opportunities Prospect Discovery

Last updated: 18 August 2026

## Current phase

Phase 1 foundation is complete. The existing Outreach > Find Prospects page now has two discovery modes:

- Local Businesses: the existing OpenStreetMap workflow, unchanged.
- SEO Opportunities: persisted keyword searches, queued organic SERP retrieval, domain deduplication, ranking evidence, search history, and read-only candidate results.

Phase 1 deliberately stops before crawling, auditing, scoring, qualification, and importing. No SEO candidate can yet be added to Outreach, and no email is sent.

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

The crawler currently operates around a managed `Website` aggregate. A later phase should introduce a candidate analysis adapter/orchestrator around the crawler rather than creating a second crawler or prematurely creating managed websites for unqualified prospects.

### Existing ranking integrations

Sitewell already uses DataForSEO for known-domain SEO snapshots through `DataForSEOClient`, `RankedKeywordsService`, and the SEO intelligence services. Those services answer “what does this known domain rank for?” They do not answer the query-first discovery question “which domains rank for this local keyword?”

Search Console opportunities are first-party data for managed websites and must not be mixed with prospect discovery estimates. Existing `SeoOpportunity` records are content recommendations for managed websites, not prospect candidates, so they are intentionally not reused for this feature.

## Phase 1 architecture decisions

### Separate search aggregate, shared Outreach destination

SEO discovery uses `SeoProspectSearch`, `SeoProspectCandidate`, and `SeoProspectRanking`. These are search/history/evidence records, not a second CRM or lead model. In a later phase, selected qualified candidates will create or link the existing `Prospect` exactly as the OpenStreetMap importer does.

### Provider boundary

`SerpProvider` is the application boundary. It returns a provider-neutral `SerpSearchResponse` containing typed `SerpResult` records and usage metadata. `DataForSEOSerpProvider` is the first implementation and uses the existing authenticated, retried, validated `DataForSEOClient`.

The provider stores organic position, URL, normalized domain, page title, description, website name, keyword, and check time. Paid and non-organic results are ignored.

### Domain deduplication

Candidates are unique by search and normalized domain. Hosts are lowercased and a leading `www.` is removed using the existing Sitewell URL normalization behavior. One candidate can therefore contain ranking evidence for several keywords. Existing Outreach prospects are detected by normalized host and linked for display rather than duplicated.

### Ranking thresholds

The search stores the requested minimum and maximum positions, defaulting to 20-100. Retrieval keeps all organic evidence up to the maximum depth. The minimum is not used as a hard rejection rule: a domain at position 12 for one term and positions 38 and 57 for others remains available for later visibility scoring.

### Idempotency and failure isolation

`DiscoverSeoProspects` is unique per search and uses `updateOrCreate` for both domains and keyword rankings. Each keyword request is isolated; one failed keyword is recorded without discarding successful keyword results. If every request fails, the job is retried and ultimately marks the search failed.

### Cost records

Every provider response creates an `ExternalApiUsage` row. Its metadata contains the SEO search ID, keyword, and location. The provider-reported cost is also accumulated on the search for history display. No Google HTML is scraped.

## Database structure

### `seo_prospect_searches`

Stores owner, industry, location, service keywords, manually editable final keywords, ranking range, maximum pages, provider, status, counts, API cost, errors, and run timestamps.

### `seo_prospect_candidates`

Stores one normalized domain per search, its representative URL/name/location, an optional existing `prospect_id`, and nullable fields reserved for page count, audit score, migration assessment, score breakdown, observations, and qualification status.

### `seo_prospect_rankings`

Stores candidate, keyword, organic position, ranking URL, title, description, and checked time. The candidate/keyword pair is unique and updated safely on rerun.

No new prospect, status enum, campaign, audit, or website table was created.

## SERP provider recommendation

Use DataForSEO initially because Sitewell already has its client, credentials, retries, error handling, SEO data conventions, and external cost ledger. Its Google Organic SERP endpoint supports depth 100 and returns all required organic fields. The provider contract keeps the feature replaceable by SerpAPI or another source later.

As of 18 August 2026, DataForSEO prices Google Organic SERPs per ten results:

- Live: $0.002 per ten results. Depth 100 is approximately $0.02 per keyword, or $0.10 for five keywords.
- Standard queue: $0.0006 per ten results. Depth 100 is approximately $0.006 per keyword, or $0.03 for five keywords.
- Priority queue: $0.0012 per ten results. Depth 100 is approximately $0.012 per keyword, or $0.06 for five keywords.

Phase 1 uses Live because the existing client is a synchronous POST client running inside a queued Sitewell job. A later cost-optimisation phase should add DataForSEO Standard task POST/GET support and a result cache before wider use. Optional SERP parameters can add multipliers and are not enabled.

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

## Remaining work

### Phase 2: candidate analysis

- Create an idempotent per-candidate analysis job so one crawl/audit failure cannot fail the search.
- Adapt the existing `WebsiteCrawler` for an unowned candidate URL without creating a managed `Website` prematurely.
- Count canonical, indexable pages and flag 1-10, 11-20, 21-40, and 40+ page bands.
- Stop expensive audit work after a candidate is known to exceed the configured maximum, while retaining it as too large/unsuitable.
- Reuse `ProspectContactFinder` for evidence-backed contact enrichment.
- Add simple Easy/Medium/Hard/Unknown migration heuristics and a reason.
- Reuse existing Sitewell audit checks and store the resulting score/key issues.

### Phase 3: scoring and observations

- Implement `SeoOpportunityScoringService` with the agreed 40/25/20/15 breakdown.
- Store each component and generate deterministic explanations from stored data.
- Generate structured outreach observations only from ranking, crawl, and audit records, with references to their supporting row/data.
- Add result filters and default score-descending ordering.

### Phase 4: import into Outreach

- Add manual candidate selection and Add to Outreach.
- Use a transaction and row locks following the existing OpenStreetMap importer.
- Deduplicate by normalized domain against existing prospects, link rather than duplicate, and attach discovery evidence.
- Dispatch existing prospect enrichment/draft behavior only after commit.
- Keep approval mandatory and never send automatically.

### Phase 5: cost and reruns

- Add cached SERP reuse with a visible freshness policy.
- Add a rerun action that creates a new dated run or explicitly refreshes the existing run without duplicating candidates.
- Consider DataForSEO Standard queue support for roughly 70% lower SERP cost.
- Add provider location-code resolution and cache.
- Add per-search cost estimates before submission and cost totals in search history.

## Architecture concerns

- Candidate crawling must retain SSRF protections equivalent to `ProspectWebsiteAnalyzer` before arbitrary discovered domains are fetched.
- `WebsiteCrawler` currently expects a managed website/domain context; do not work around that by creating fake managed websites.
- SERP directories, social networks, aggregators, and national publishers will appear and need an explicit exclusion/qualification policy before analysis fan-out.
- Search depth 100 can return hundreds of domains across several keywords. Candidate analysis must be fan-out queued, rate-limited, and bounded.
- Existing `SeoOpportunity` and `SearchOpportunity` names describe managed-site content opportunities. Keep prospect discovery naming distinct to avoid mixing third-party prospect evidence with managed-site recommendations.
- Ratings and reviews are optional for V1. Organic result rating fields may occasionally be present, but they are not reliable enough to make qualification depend on them.