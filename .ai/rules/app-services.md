---
paths:
  - 'app/{Services/Github*,Models/GithubUserAuthorization.php,Jobs/{StartCopilotRemediation,SyncCopilotRemediation}.php}'
  - 'app/{Services/SearchConsoleHistoryStore.php,Services/WebsiteAiContext.php,Jobs/SyncSearchConsoleHistory.php}'
  - 'app/{Services/Content*,Jobs/StartContentGeneration.php}'
  - 'app/{Services/ProspectPersonalisedVideo.php,Models/ProspectOutreachState.php,Mail/ProspectOutreach.php}'
---

# App Services

## Encrypt and rotate GitHub user authorizations
Copilot Agent Tasks require a GitHub App user token, not an installation token. Store access and refresh tokens only with encrypted casts, hide them from serialization, refresh shortly before expiry under an atomic lock, and never put tokens into queued job payloads or logs.

## Persist bounded query histories before AI analysis
The website assistant must analyze stored Search Console data only and must not make live Google calls. Weekly history sync should proactively discover a bounded set of top current queries, retain a bounded set of previously tracked queries, and persist their monthly histories so keyword-movement comparisons do not depend on users opening individual query pages.

## Persist bounded query histories before AI analysis
The website assistant analyzes stored Search Console data only and never makes live Google calls. Weekly sync persists up to 1,000 highest-click queries for each available month in bounded monthly requests, and question context compares the explicitly requested month with the latest month while reporting sample coverage and Search Console omissions.

## Monthly samples supersede tracked-query refresh
This supersedes the earlier top-current/previously-tracked query strategy. Do not reintroduce per-query weekly API calls; use the bounded per-month sample so comparisons cover a materially broader and date-aligned query set.

## Preserve recent content across all generation triggers
Queued requests and manual staff runs retain the 14-day target/known-page cooldown; merged_at restarts protection, with accepted task start as fallback. Match duplicate request text as well as known URLs and terms. Briefs include bounded site-specific history and require inspecting recent repository changes. Every new public indexable page requires verified sitemap inclusion, inbound links, and an explicit navigation decision in the PR; merge is not proof of deployment.

## Keep personalised video drafts separate from live delivery
Store the editable video subject, body, URL and thumbnail in ProspectOutreachState.personalised_video_draft. Save/test actions must not reserve deliveries, create tracking links, update the initial outreach, change lifecycle state or modify an existing schedule. Test the saved video variant only to the acting admin, with direct links and no engagement tracking; explicit send/schedule continues through the existing delivery ledger.
