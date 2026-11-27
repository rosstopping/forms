---
paths:
  - 'app/{Ai/Agents/CompetitorAnalyst.php,Services/CompetitorBriefGenerator.php}'
---

# Agents

## Bound competitor analyst lists before validation
Structured-output providers may return arrays beyond declared schema maxima. Deduplicate and cap opportunity/list fields to the supported brief limits before Laravel validation, while retaining validation and provenance checks for types, supplied keyword IDs, fetched source URLs, and own-site page targets.

## Generate natural buyer questions before independent AI observations
The November 27 buyer-intent refinement supersedes service-name extraction plus a fixed recommendation template. First use bounded structured OpenAI generation to write at most two natural buying, booking, comparison or cost questions from evidenced own offers and served/event locations. Reject the old template, target company/domain hints, client names, invented evidence, prices and locations. Invalid generation yields no fabricated fallback. Preserve exact evidence, question intent and a versioned cache. Second-stage web-search answers receive only the generated question, never the target identity or source text. Preserve the internal AI pause and separate public-audit opt-in.
