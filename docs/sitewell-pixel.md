# Sitewell Pixel implementation

This document is the source of truth for the phased Sitewell Pixel rollout.

## Existing application findings

- `Website` is the customer site/project aggregate and `WebsiteDomain` stores its accepted domains.
- Crawled URLs are currently snapshots in `WebsiteHealthReportPage`, owned through `WebsiteHealthReport`.
- Audit findings live in report page checks and SEO opportunities. There was no existing approved, deployable optimisation concept.
- GitHub remediation is a report-level Copilot workflow using `RemediationRun`, queued jobs, and GitHub App/OAuth services. It is intentionally unchanged.
- The authenticated administration UI is server-rendered Blade under `/admin`, with website ownership/management policy checks.
- Queues use Laravel jobs and Horizon. Phase 1A deployment transitions are synchronous and transactional; Pixel publishing does not require remote work.

## Architecture decisions

- `Optimisation` is the stable deployment intent, scoped to a `Website` and optionally linked to a crawled `WebsiteHealthReportPage`.
- `OptimisationVersion` is append-only value history. Revisions create a new numbered row and retain the previous value chain.
- `OptimisationDeployment` is an append-only ledger of deployment and rollback attempts against an exact version.
- PHP enums define optimisation types, lifecycle states, deployment methods, and deployment ledger states.
- `DeploymentDriver` is the adapter boundary. Only `PixelDeploymentDriver` is implemented in Phase 1; GitHub remediation is not forced into the abstraction.
- `OptimisationDeploymentManager` owns approval, deployment, rollback, version creation, and payload-version invalidation.
- Websites and optimisations use random prefixed public identifiers. The site key grants only future public payload retrieval and is not authentication.
- A live optimisation must be rolled back before revision. This avoids replacing an active payload without an explicit rollback record.
- Pixel deploy/rollback is logical: a deployed optimisation is included in the public payload and a rolled-back one is omitted. The customer origin remains unchanged.

## Phase progress

### Phase 1A — complete

- Added optimisation, immutable version history, and deployment ledger tables/models/factories.
- Added deployment and optimisation enums.
- Added deployment driver contract, result value object, Pixel driver, and transactional lifecycle manager.
- Added website Pixel public key, enabled flag, and monotonic payload version.
- Added focused lifecycle and history tests.

### Phase 1B — complete

- Added strict URL normalisation and hostname ownership validation using `WebsiteDomain`.
- Added `GET /api/pixel/{siteKey}` with request validation, rate limiting, public CORS, ETags, and edge-ready cache headers.
- Payloads contain only deployed Pixel optimisations for the normalized URL and only public runtime fields.
- Added indexed normalized URL hashes so payload lookup does not scan every site optimisation.
- HTTP/HTTPS, leading `www`, fragments, query strings, non-root trailing slashes, and percent-encoded unreserved characters normalize consistently. Paths remain case-sensitive.
- Unknown keys, disabled Pixels, and wrong hostnames share a 404 response to reduce enumeration signals.
- Added API tests covering keys, domains, matching, lifecycle exclusions, response privacy, validation, caching, and ETags.

### Phase 1C — complete

- Added the standalone dependency-free `public/pixel.js` runtime.
- The runtime reads `data-site`, supports an optional `data-api` override, requests the current URL payload with a four-second timeout, waits for DOM readiness, and fails silently.
- Every optimisation is validated and applied independently. A missing selector, malformed value, failed request, or individual exception cannot block later changes or the customer page.
- Supports title, meta description, H1/text, sanitized HTML replacement/append/prepend, allow-listed attributes, internal link href, image alt, and validated JSON-LD.
- HTML sanitisation removes active/embedded/form/script/style/SVG/MathML elements, unwraps unknown elements, and retains only safe content attributes. Executable URLs and event attributes are rejected.
- JSON-LD is parsed before insertion, serialized as data, escapes `<`, and is the only script element the runtime creates.
- Added tests using Node's built-in runner and DOM doubles; no dependency or framework was introduced.
- Basic SPA navigation was investigated but deferred. Applying URL changes safely requires tracking and restoring each original DOM state without overwriting changes made by the site's router. Phase 1 avoids stale cross-route modifications and aggressive observers; robust lifecycle-aware SPA support belongs in Phase 2.

### Phase 1D — complete

- Added a Pixel tab to the existing website workspace with connection state, last seen, detected pages, active optimisation count, runtime version, installation snippet, copy action, and CSP guidance.
- Pixel asset and API hosts are configurable with `SITEWELL_PIXEL_ASSET_URL` and `SITEWELL_PIXEL_API_URL`; the snippet does not hardcode production domains.
- Added a public heartbeat endpoint with the same key/domain validation as payload retrieval, CORS/preflight handling, input validation, and a 30-request-per-minute IP limit.
- The runtime reports only after a valid payload response and uses local storage to report at most once per browser, normalized page, and day.
- Sitewell stores a site-level last-seen summary and one deduplicated row per normalized page. It does not store page-view events or counters.
- Invalid keys, wrong hostnames, and disabled Pixels return the same empty `204` heartbeat response and do not write data.
- Added heartbeat, deduplication, normalization, validation, CORS, UI, configuration, authorization, and runtime throttling tests.

### Phase 1E — next

- Add page-level optimisation creation and history UI within the existing crawled health-report page experience.
- Add explicit request validation and server-side content safety before values may be approved.
- Add approve/deploy and individual rollback actions, using the existing lifecycle manager and website authorization rules.
- Show recommendation, approved version, live Pixel state, deployment timestamps, and append-only version/deployment history distinctly.

### Outstanding phases

- Phase 1E: page optimisation create/approve/deploy/rollback UI and visible history.
- Phase 1F: sanitisation, selector/attribute allow-lists, JSON-LD validation, caching, CORS/CSP, logging, and abuse hardening.
- Phase 1G: broader automated tests, JavaScript DOM tests if supported without a large dependency, and operator/customer documentation.

## Security and known issues

- No public Pixel endpoint or JavaScript runtime exists yet, so the Phase 1A data layer is not publicly reachable.
- HTML, attributes, selectors, and JSON-LD are not accepted through an application endpoint yet. Their validation must be implemented before creation UI/API ships.
- Phase 1B must compare parsed hostnames, never string prefixes, and must define the project rule for `www` aliases from registered `WebsiteDomain` records.
- Pixel HTML must reject script elements, executable URLs, event attributes, and unsafe elements/attributes. JSON-LD is the sole permitted script element and must contain valid JSON.
- Customer CSP may need `script-src` permission for the configured Pixel asset host and `connect-src` permission for the API host. Sitewell will document these additions rather than weaken CSP.

## WordPress Phase 2 notes

- WordPress should implement `DeploymentDriver` but persist native CMS identifiers/revisions in adapter-specific metadata or records, not on the generic optimisation value history.
- WordPress rollback may be a compensating native write, unlike Pixel omission, so the deployment ledger must retain every attempt and exact version.
- Capability checks should remain deployment-method aware because some optimisation types may be native in WordPress while server-level changes remain unsupported.
