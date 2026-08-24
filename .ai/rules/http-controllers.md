---
paths:
  - 'app/{Services,Http/Controllers}/**/*ProspectOutreach*.php,app/Http/Controllers/ProspectReportController.php,config/outreach.php'
---

# Http Controllers

## Classify prospect intent through configurable scoring
Tracked opens and link/report visits must create deduplicated ProspectEngagementEvent rows and use config/outreach.php weights and temperature bands; do not promote every click directly to hot. Known scanner requests may be classified transiently and scored zero, but never persist IP addresses or user-agent strings. Notify admins only when a genuine scored signal crosses into hot.
