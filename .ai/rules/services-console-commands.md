---
paths:
  - 'app/{Services,Console/Commands}/**/*Prospect*.php,routes/console.php'
---

# Services Console Commands

## Cool inactive prospects after fourteen days
Run outreach:cool-inactive hourly. Hot/warm prospects become cold strictly after 14 days without interaction (creation time fallback for missing engagement), including manual temperature overrides. Retain scores/history and protected lifecycle statuses, clear stale manual queues and due automation without resuming email. Ignore scanner sources for last-engagement time and reheating; genuine later interaction may restore score-based temperature.
