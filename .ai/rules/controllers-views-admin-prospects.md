---
paths:
  - 'app/{Models,Services,Jobs,Http/Controllers}/**/*Prospect*.php,resources/views/admin/prospects/**'
---

# Controllers Views Admin Prospects

## Retain deleted outreach prospects without further outreach
Prospect deletion is soft deletion through ProspectDeletion: stop automation and cancel unsent pending/scheduled/failed deliveries while preserving sent history and activity. The Deleted status filter searches only trashed records across temperatures and displays read-only results; exclude them from ordinary lists, mutation route binding, tracking and queued processing. Deleted is not a mutable lifecycle status.
