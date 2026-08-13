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

### Phase 1E — complete

- Added a dedicated optimisation workspace for every crawled health-report page and linked it from page-by-page report analysis.
- Crawl issues and current title/description are shown as recommendation evidence, separately from draft, approved, live, failed, and rolled-back optimisations.
- Website managers can create structured drafts, create immutable revisions, approve and deploy through Pixel, and roll back individual optimisations. Read-only website users can inspect the workspace and history without mutation controls.
- Added strict nested ownership checks across website, report, crawled page, and optimisation routes.
- Added server-side content safety for initial values and revisions: active elements, scripts, event handlers, inline styles, unsafe URLs, unsupported attributes, external “internal” links, and invalid JSON-LD are rejected.
- Safe HTML is reduced to the same element/attribute allow-list as the runtime, and JSON-LD is stored in canonical parsed form.
- The Pixel driver revalidates the exact stored version at deployment time, so content that bypasses HTTP validation cannot become live.
- The workspace displays original/proposed/live values, timestamps, immutable version history, and the deployment/rollback ledger.
- Successful deploy/rollback lifecycle changes produce structured application log entries without logging page views or optimisation content.
- Added workflow, authorization, nested scoping, payload visibility, rollback, history, and security tests.

### Phase 1F — complete

- Added five-minute application caching for public payload changes, partitioned by website payload version and normalized URL hash. Deploy, rollback, site enable/disable, and key rotation invalidate results through the monotonic version.
- Limited each public payload to 1,000 active changes as a defensive response-size bound.
- Added throttled observability for unknown/disabled keys, invalid domains, successful payload generation, and payload failures. Logs contain hashes and structured reasons rather than public keys, URLs, or optimisation content.
- Added a site-wide Pixel kill switch without altering optimisation history or deployment state.
- Added page-wide rollback that records an individual rollback ledger entry for every live Pixel optimisation.
- Added public-key rotation, which invalidates the old key and resets connection/last-seen state so the UI cannot imply that the replacement snippet is already installed.
- Tightened selector validation by rejecting ASCII control characters.
- Added manager-only emergency controls to the Pixel connection and page optimisation interfaces.
- Added focused hardening tests covering cache invalidation, controls, history preservation, key rotation, logging privacy/throttling, selector validation, and UI visibility.
- A controlled external-site production smoke test remains an operator rollout task because it requires the deployed asset/API hosts and a customer-controlled test page.

### Phase 1G — complete

- Added a read-only `sitewell:pixel:check` Artisan command for repeatable production verification of the configured Pixel asset and a site/domain-specific payload request.
- The check validates asset identity, payload shape, public CORS, cache headers, and ETags, uses short connection/request timeouts, and returns a non-zero exit code on failure.
- Added mocked command tests for successful production checks, rejected payloads, and invalid operator input without making external requests during the test suite.
- Added customer installation, CSP, detection troubleshooting, emergency-operation, production smoke-test, and release-checklist documentation in `docs/sitewell-pixel-installation.md`.
- Re-ran the complete PHP and dependency-free JavaScript suites, formatting, diff validation, and production asset build.
- Phase 1 is code-complete. Deployment migrations/configuration and the controlled external-site smoke test remain environment-specific release steps.

### Outstanding work

- Deploy the Phase 1 migrations and application changes to the target environment.
- Configure the public HTTPS asset/API URLs and CDN/proxy behavior.
- Run the documented command and browser smoke test against a controlled external website.
- Confirm production logs, cache, rate limiter, and heartbeat connection state during the initial rollout.

## Security and known issues

- HTML and JSON-LD are validated and canonicalized on input, revalidated at deployment, and independently sanitized by the runtime. JSON-LD is the sole permitted script element and must contain valid JSON.
- URL matching uses parsed hostnames and normalized paths; it never uses string-prefix domain checks. Registered domains accept their corresponding `www`/apex form.
- Public keys are bearer-like lookup identifiers, not authorization credentials. Rotation is available if a key needs replacing, while management operations remain authenticated and policy-authorized.
- Payload and heartbeat endpoints are intentionally public and CORS-enabled, domain-bound, rate-limited, and return indistinguishable failures for unknown keys, disabled sites, and invalid domains.
- A restrictive customer CSP may need the configured asset host in `script-src` and API host in `connect-src`. The customer must retain any nonce or integrity requirements imposed by their policy; Sitewell does not weaken CSP.
- Stale or syntactically invalid runtime selectors fail silently and independently. Phase 1 does not send selector-failure telemetry, avoiding per-view analytics traffic; operators diagnose these through preview/manual verification.
- Basic SPA navigation remains deferred because safe restoration across route transitions needs router-aware DOM lifecycle handling.
- Production readiness still requires migration/deployment, configuration of public asset/API URLs, CDN compression/cache verification, and an external-site smoke test in the target environment.

## WordPress Phase 2 notes

- WordPress should implement `DeploymentDriver` but persist native CMS identifiers/revisions in adapter-specific metadata or records, not on the generic optimisation value history.
- WordPress rollback may be a compensating native write, unlike Pixel omission, so the deployment ledger must retain every attempt and exact version.
- Capability checks should remain deployment-method aware because some optimisation types may be native in WordPress while server-level changes remain unsupported.
