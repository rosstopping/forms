---
paths:
  - 'app/Services/ContentQueueOverview.php,resources/views/admin/overview.blade.php'
---

# Services Views Admin

## Explain pending content queues using actual scheduling rules
Dashboard requests awaiting preparation count unpicked content requests, not reviewable output. Show a per-website queue with setup blockers first, real ContentSchedule next runs, and contextual action links. Pending/running generations pause scheduling. An open PR only pauses the queue if all waiting requests overlap its work using ContentWorkSelector keys; unrelated requests can still run. Read stored data only; dashboard rendering must not launch generation or external API calls.
