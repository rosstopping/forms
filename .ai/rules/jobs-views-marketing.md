---
paths:
  - 'app/{Services,Jobs}/MarketingAudit*.php,app/Jobs/CheckMarketingAuditAiVisibility.php,resources/views/marketing/website-audit.blade.php'
---

# Jobs Views Marketing

## Bound public competitor and AI observations
Public audits may add at most one five-result Google competitor discovery and one five-term shared-position comparison, cached per domain/market for seven days. Show competitor domains as search-overlap estimates, not necessarily business rivals. Derive one unbranded AI question from measured ranking terms and run one queued OpenAI check; report only observed domain mention/citation with date, never a universal AI rank. Reads must use saved audit insights and never trigger paid calls.

## Public AI sample uses at most two questions
Supersedes the one-question cap in Bound public competitor and AI observations: derive at most two distinct unbranded questions from measured ranking terms, then run at most two queued OpenAI checks per audit. Show each observed website mention/citation separately with its date; never present a fixed AI rank or infer visibility from a missing/failed answer. Cache successful checks for seven days.
