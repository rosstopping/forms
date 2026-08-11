---
paths:
  - 'app/{Ai,Jobs,Mail,Models,Http/Controllers/Admin}/**/*Prospect*.php'
---

# Admin

## Keep prospect outreach approval-first
Automated prospect research may generate outreach drafts, but it must never send them automatically. Editing a draft resets approval; sending requires explicit approval and must honour suppression and duplicate-send guards.
