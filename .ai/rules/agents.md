---
paths:
  - 'app/{Ai/Agents/CompetitorAnalyst.php,Services/CompetitorBriefGenerator.php}'
---

# Agents

## Bound competitor analyst lists before validation
Structured-output providers may return arrays beyond declared schema maxima. Deduplicate and cap opportunity/list fields to the supported brief limits before Laravel validation, while retaining validation and provenance checks for types, supplied keyword IDs, fetched source URLs, and own-site page targets.
