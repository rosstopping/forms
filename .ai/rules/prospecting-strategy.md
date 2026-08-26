---
paths:
  - 'app/{Console/Commands,Jobs,Services,Models,Http/Controllers/Admin}/**/*Prospect*.php,database/migrations/**/*prospect*.php,resources/views/admin/prospecting-strategy/**'
---

# Prospecting Strategy

## Bound automatic prospect discovery and preserve approval
Automatic industry/location discovery must create normal SeoProspectSearch records and reuse the existing SERP, candidate analysis, scoring, importer, and outreach pipeline. Bound runs by SERP keyword operations, deduplicate profile/location runs per period, reuse recent domain analysis, retain industry/location/query evidence on prospects, and never approve or send outreach automatically.
