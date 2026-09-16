# AI Visibility

AI Visibility is a website-scoped section alongside Search performance and SEO Intelligence. Its dashboard, prompt evidence pages and the main Overview read stored data only. Tracking starts disabled for every site; adding prompts or opening a page never buys checks.

## Enable a provider

| Provider label | Credentials | Feature configuration | Actual integration |
| --- | --- | --- | --- |
| OpenAI | `OPENAI_API_KEY` with API billing and access to a search-capable model | `AI_VISIBILITY_OPENAI_ENABLED=true` (default); optional `AI_VISIBILITY_OPENAI_MODEL` | Installed Laravel AI SDK, OpenAI Responses API with WebSearch |
| Gemini | `GEMINI_API_KEY` with access to Google Search grounding and the selected model | `AI_VISIBILITY_GEMINI_ENABLED=true`; optional `AI_VISIBILITY_GEMINI_MODEL` | Installed Laravel AI SDK, Gemini generateContent with Google Search grounding |
| Perplexity | `PERPLEXITY_API_KEY` with Sonar API access and billing | `AI_VISIBILITY_PERPLEXITY_ENABLED=true`; `AI_VISIBILITY_PERPLEXITY_MODEL=sonar` (default) | Perplexity's own `/chat/completions` endpoint and source URLs |

Gemini and Perplexity remain disabled by default. Missing keys make any provider unavailable. There is no cross-provider fallback or relabelling. OpenAI/Gemini use the installed SDK's default text model unless overridden; pin a supported model for consistent tracking. OpenAI uses the project's SDK connection configuration (`OPENAI_URL` must point to the intended OpenAI service). Credentials stay in environment configuration and never enter site settings, job snapshots or views.

After deploying, run `php artisan migrate --no-interaction`, build frontend assets with `npm run build`, and refresh cached configuration/restart queue workers using the existing deployment process. In the site's AI Visibility tracking settings, confirm the business name, aliases, services, locations and enabled providers, then enable tracking. Add/review prompts and select **Check due prompts** for an initial sample. Existing website domains supply the citation identifiers.

No live paid calls are part of automated verification. Configure credentials before expecting real results; API availability, billing and model access are verified by the provider when a check actually runs.

## Prompts and evidence

Prompts are separate from SEO keywords. Suggestions use tracked keywords, configured services/locations and available Business Profile locality. They yield up to 15 distinct suggestions (usually 5–15 when sufficient source data exists), remove minor wording duplicates, and require review before saving. Insufficient profile data produces fewer suggestions rather than invented services. An optional linked Google keyword enables cross-module opportunities. Generation never overwrites existing prompts.

Each check stores its prompt wording, identifiers, provider/model, returned model, system instructions, timestamps, attempts, bounded response text, source URLs/domains, detected list businesses and analysis version. Responses exceeding 30,000 characters or interrupted/empty responses fail instead of contributing partial scores. Raw provider envelopes are not retained. Deleting a prompt soft-deletes it; historical results and their detail pages remain available. Re-adding the same deleted question explicitly restores its record and retained history. Changing wording does not rewrite old evidence.

Brand detection normalises case, punctuation and “&”/“and”, using business names and explicit aliases with word boundaries. URLs alone do not count as textual brand mentions. Domain detection accepts the client's host and its subdomains, but rejects lookalike suffixes and credential-bearing URLs. A written website URL is a mention; a citation requires an actual source URL supplied by the provider.

Positions require an explicit, consecutive numbered recommendation list with at least two entries and one identifiable client entry. Prose and unordered lists have no position. Competitors are extracted conservatively from explicit recommendation labels; prose-only businesses may be missed. Their response share counts each detected competitor at most once per completed check, so shares need not add to 100%. A mention is not a claim of endorsement or positive sentiment.

Provider citations can contain redirect URLs, particularly Google grounding URLs. Sitewell preserves those URLs without fetching redirects or guessing the original domain from a title. This can undercount direct domain citations; the evidence screen makes the limitation visible.

## Scores and history

- **AI Visibility:** completed checks mentioning the brand / completed checks × 100.
- **Citation visibility:** completed checks citing a client domain / completed checks × 100.
- **Average position:** mean client position across checks with a measurable list position.
- **Prompts visible:** distinct prompts with at least one brand appearance in the selected period.
- **Competitor visibility:** completed responses containing the detected competitor / completed checks × 100.

Failed, queued, running and cancelled checks are excluded. No completed checks means unavailable, not 0%. Scores are unweighted and rounded to one decimal place. Periods with unequal prompt/provider/model/identifier/methodology coverage or unequal successful-check counts suppress the headline change. Individual gains/losses compare the latest equivalent observations. API responses are samples and can vary; the product measures OpenAI, not consumer ChatGPT, and does not claim parity with any consumer AI interface.

The chart shows observed dates only, without filling missing dates with zeros. It uses the existing Sitewell chart component; date labels identify observations rather than equal calendar intervals. Range options appear as history reaches 30, 90, 180 and 365 days, with “Since first check” beforehand. Provider filters show platforms actually queried. Prompt evidence retains older wording alongside current wording.

Opportunities include missing mentions, missing citations, lost appearances, repeated earlier competitor positions, and strong recent Google rankings (top 10 desktop in the configured market) paired with absent AI mentions. Opportunities carry result IDs and a prompt-detail link; no tasks are created automatically.

## Scheduling and costs

The existing `ranking-reports:dispatch` weekly command (Monday 08:00 in the application's timezone) queues due AI checks as well as existing reports. There is no new scheduler entry, separate email or separate AI report. AI tracking is independent of the site's email subscription. Newly queued checks normally feed the following week's overview, which covers the prior seven days.

Site settings support 7, 14 or 28 days between checks. Global controls:

- `AI_VISIBILITY_MAX_ACTIVE_PROMPTS` defaults to 20 per site. When reduced below existing active counts, high-priority prompts are selected first within the cap, including manual requests.
- `AI_VISIBILITY_CHECKS_PER_MINUTE` defaults to 10 per provider across the application.
- Checks use the existing queue, unique job locks, atomic result locks and a prompt/provider/week uniqueness constraint.
- Provider attempts are capped at three with 60/300/900-second backoff. Rate-limit releases do not consume that provider-attempt budget; jobs expire after six days. The scheduler can recover stale queued work or replace expired checks when the next interval is due.
- Job timeout is 120 seconds; provider timeout is 90 seconds. Keep the queue connection's retry-after/visibility timeout above the job timeout (the existing database/Redis settings satisfy this).
- Disabled/deleted/edited prompts, disabled providers, inactive sites and expired memberships cancel or prevent checks. Saving or repeatedly pressing Check due prompts does not bypass the configured cadence.
- Failure messages exclude credentials and are visible on the evidence page; exceptions use application logging. Successful requests record token usage in `external_api_usages`; costs remain unknown unless a provider supplies an actual cost. No estimated price is fabricated.

## Weekly Overview and existing email

`WeeklyReportBuilder` adds an optional structured `ai_visibility` source with scores, equivalent-period comparisons, provider breakdowns, notable changes and evidence-backed opportunities. Sources without current or preceding completed observations remain absent.

The saved Weekly Overview appends the deterministic AI Visibility summary verbatim. Its narrative writer handles other supplied facts and is prevented from substituting an AI-platform narrative. The dashboard and the existing weekly email reuse the same persisted overview. No visibility checks occur during email rendering or report generation, and existing snapshots remain frozen on retries.

## Verification

Run `php artisan test --compact tests/Feature/AiVisibilityTest.php tests/Feature/AiVisibilityProvidersTest.php tests/Feature/WeeklyOverviewTest.php tests/Feature/WeeklyRankingReportTest.php`. Provider tests use mocked HTTP responses and block stray requests; feature tests fake the AI observer and narrative writer. Never substitute live paid requests in these tests.
