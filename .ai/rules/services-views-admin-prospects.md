---
paths:
  - 'app/Services/Prospect*.php,resources/views/admin/prospects/**'
---

# Services Views Admin Prospects

## Customers have no lead temperature label
Converting to Customer clears temperature overrides and manual lead follow-up recommendations; store cold for compatibility but show no temperature badge for converted prospects. Exclude existing converted records from warm/hot queues and counts even if they retain a historical temperature. Later engagement must not reheat customers or replace their Customer lifecycle with Replied.
