---
paths:
  - 'app/Services/ContentQueueOverview.php,resources/views/admin/overview.blade.php'
  - 'app/Services/DashboardWorkActivity.php,resources/views/admin/overview.blade.php'
---

# Services Views Admin

## Explain pending content queues using actual scheduling rules
Dashboard requests awaiting preparation count unpicked content requests, not reviewable output. Show a per-website queue with setup blockers first, real ContentSchedule next runs, and contextual action links. Pending/running generations pause scheduling. An open PR only pauses the queue if all waiting requests overlap its work using ContentWorkSelector keys; unrelated requests can still run. Read stored data only; dashboard rendering must not launch generation or external API calls.

## Keep dashboard work activity read-only and cautious about stalls
Needs attention displays failed work and possibly stalled persisted jobs: queued over one hour, running audits over 15 minutes, running content/remediation over two hours. Exclude future-dated pending content. These are display heuristics, never status transitions or retry triggers. Bound each dashboard list to 10; order completions by merged_at/completed_at, and distinguish manually completed fixes from GitHub merges. Scope to accessible websites and use internal report/content links.
