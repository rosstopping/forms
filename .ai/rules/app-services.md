---
paths:
  - 'app/{Services/Github*,Models/GithubUserAuthorization.php,Jobs/{StartCopilotRemediation,SyncCopilotRemediation}.php}'
  - 'app/{Services/SearchConsoleHistoryStore.php,Services/WebsiteAiContext.php,Jobs/SyncSearchConsoleHistory.php}'
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
