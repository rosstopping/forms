---
paths:
  - 'app/{Models,Services}/SeoTargetKeyword*.php,resources/views/admin/websites/partials/seo-target-keywords.blade.php'
---

# Websites Partials

## Compare target competitors within one SERP observation
Compare the website and non-excluded tracked competitors using the same successful target-keyword SERP snapshot and market. Retain bounded best organic results per canonical domain from the existing check; do not issue extra paid requests while rendering comparisons. Null organic_results means legacy/unavailable evidence, while an empty array is a completed check with no matching results. Preserve fetched time for cached observations and show the prior successful comparison after a failed refresh.
