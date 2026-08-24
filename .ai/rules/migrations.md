---
paths:
  - 'app/{Enums,Models,Services,Jobs,Http/Controllers}/**/*Prospect*.php,config/outreach.php,database/migrations/**/*prospect*.php'
---

# Migrations

## Separate prospect lifecycle, score evidence, and timeline
Use ProspectOutreachState for current lifecycle/automation state, ProspectEngagementEvent as the immutable deduplicated scoring ledger, and ProspectActivity for the human-readable audit timeline. Keep lead_temperature as a compatibility field synced from the configurable score unless a manual override exists. Automated transitions must never replace protected terminal lifecycle states.
