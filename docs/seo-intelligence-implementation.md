# SEO Intelligence implementation checklist

## Architecture review

- [x] `Website` is the aggregate root. It owns many `WebsiteDomain` records and `primaryDomain()` selects the analysis target.
- [x] Website access is owner-or-membership based through `Website::accessibleTo()`, `isAccessibleBy()`, and `isManageableBy()`.
- [x] First-party SEO data already lives behind `SearchConsoleConnection`, `SearchConsoleClient`, `SearchOpportunityFinder`, and `SearchOpportunity`.
- [x] Technical/on-page data already lives behind `WebsiteCrawler`, `WebsiteHealthAuditor`, `PageSpeedInsightsClient`, and immutable `WebsiteHealthReport` observations.
- [x] Existing integrations use dedicated clients, Laravel HTTP, explicit timeouts, retries, configuration in `services.php`, queued unique jobs, and mocked HTTP tests.
- [x] Existing admin website pages and partials are the appropriate UI location; the SEO Intelligence feature should be a distinct website section/tab with explicit “DataForSEO estimate” labels.

## Recommended database design

- `seo_snapshots`: website FK, provider, domain, locale, status, aggregate organic/backlink metrics, observed date, small metadata, safe error summary, timestamps. Never update a completed observation with a later observation.
- `seo_keywords`: website and snapshot FKs plus provider ranking, keyword, ranking URL, volume, CPC, paid competition, intent, ETV, difficulty, and locale. Unique per snapshot/keyword/ranking URL.
- `seo_referring_domains`: website and snapshot FKs plus domain, rank, backlink count, first/last seen. Unique per snapshot/domain.
- `seo_competitors`: website and snapshot FKs plus domain, intersections/common keywords and the provider’s organic metrics. Unique per snapshot/domain.
- `seo_opportunities`: snapshot-scoped derived DataForSEO opportunities. Keep separate from the current Search Console `search_opportunities` contract so third-party estimates cannot masquerade as first-party impressions/clicks.
- `external_api_usages`: provider, endpoint, website/snapshot FKs, request type, result count, exact provider cost when returned, requested timestamp, and non-sensitive metadata.
- Index snapshot queries by `(website_id, provider, snapshot_date)` and keyword ranges by `(seo_snapshot_id, position)`.

## DataForSEO endpoints

- Domain metrics: `dataforseo_labs/google/domain_rank_overview/live`.
- Ranked keywords: `dataforseo_labs/google/ranked_keywords/live` (initial configurable limit 500).
- Organic competitors: `dataforseo_labs/google/competitors_domain/live`.
- Backlink aggregates: `backlinks/summary/live`.
- Referring domains: `backlinks/referring_domains/live` (initial configurable limit 250).
- Later gap analysis: `dataforseo_labs/google/domain_intersection/live` or page intersection, selected after validating the desired domain-vs-page semantics.
- Later history: Sitewell’s own snapshots first; use provider historical endpoints only where their availability and additional cost justify it.

## Architectural adjustments

- Treat the provider response envelope and its reported USD cost as a boundary DTO; endpoint services own request shape and later map result items into typed DTOs before persistence.
- Use one unique orchestration job with independently caught dataset steps. This preserves partial results and avoids a fragile job chain while still allowing extraction if runtime warrants it.
- Create the pending snapshot under a cache lock before dispatch. Return the newest snapshot inside the seven-day refresh window and prevent duplicate pending/processing snapshots.
- Store safe per-dataset errors, never credentials, authorization headers, full request bodies, or raw provider error payloads.
- A domain need not resolve or respond: DataForSEO calls use the normalized stored domain and never depend on the crawler.

## Delivery phases

- [x] Phase 1: configuration, authenticated DataForSEO client, validated response envelope, safe failures/logging, rate-limit-aware retries, endpoint service boundaries, mocked tests.
- [x] Phase 2: snapshot, usage, keyword, referring-domain, competitor, and opportunity schema/models/factories/relationships.
- [ ] Phase 3: endpoint result DTO mapping and snapshot persistence for domain overview and ranked keywords.
- [ ] Phase 4: independently tested DataForSEO opportunity calculation and historical comparisons.
- [ ] Phase 5: backlink overview/referring-domain and competitor persistence with partial-failure orchestration.
- [ ] Phase 6: refresh-window lock, unique queued generation job, usage/cost accounting, scheduling/manual refresh protection.
- [ ] Phase 7: initial labelled SEO Intelligence dashboard and filterable keyword table.
- [ ] Phase 8: end-to-end tests, broader regression/static analysis, and operational documentation.

## Current status

Phase 2 complete. Next: Phase 3 endpoint result DTO mapping and snapshot persistence for domain overview and ranked keywords.
