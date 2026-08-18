---
paths:
  - 'app/{Mail,Models,Services,Http/Controllers}/**/*ProspectOutreach*.php,resources/views/admin/prospects/**'
---

# Admin Prospects

## Separate open and click lead intent
Treat a tracked open as weak intent: only promote cold to warm because mail privacy and security scanners may open messages. Treat any verified tracked link click as stronger intent and promote the prospect to hot. Never let later opens downgrade hot leads; list hot, then warm, then cold by default.
