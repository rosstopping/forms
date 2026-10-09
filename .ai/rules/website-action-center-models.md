---
paths:
  - 'app/{Services/Content*,Services/WebsiteActionCenter,Models/Content*,Jobs/StartContentGeneration}.php'
---

# Website Action Center Models

## Content plans, intent assignments and bounded discovery
Planned ContentRequests reuse evidence/source links but stay out of execution and Pixel until explicit queue approval; dates never force publishing. Restricted content modes fail closed for unclassified work and new-only never falls back to target optimisation or free choice. Copilot monthly ceilings reserve immutable work_units before paid submission in the plan timezone; uncertain failures retain reservations, and these counters do not cover manual/Pixel work or currency. Daily discovery is opt-in, reads recent same-market saved competitor comparisons without AI/provider calls, deduplicates by market and term, and requires coverage/business review rather than claiming missing rankings prove missing content. Intended keyword destinations override observed ranking URLs for target selection/impact scope; differing URLs alone do not prove cannibalisation.

## Final content discovery and queue budget safeguards
Supersedes the Copilot-only budget scope above: typed content queue ceilings also cover manual and Pixel preparation, with immutable request reservations retained across retries/returns and protected from deletion or reclassification. Copilot task reservations retain their original month independently of started_at; no currency cap is inferred. Separately opt-in related-keyword research reserves one no-retry provider purchase per seven days, rotates active business seeds, validates market and positive demand/non-navigation intent, and deduplicates candidates before planning. New candidates require explicit coverage/business approval into the queue; every publication remains review-first. Copilot defers requests under an active Pixel preparation lock.
