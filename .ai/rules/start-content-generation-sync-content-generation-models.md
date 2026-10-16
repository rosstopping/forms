---
paths:
  - 'app/{Services/Content*,Jobs/{StartContentGeneration,SyncContentGeneration},Models/ContentGeneration}.php'
---

# Start Content Generation Sync Content Generation Models

## Recover verified no-work content attempts within one opportunity
Only terminal Copilot tasks with verified GitHub zero-file PR/branch evidence may return untouched requests on hold and try the next queued item. Unknown/changed/multi-PR results never auto-retry. Preserve each task/evidence and one daily root reservation, cap the opportunity at three total attempts in its original week, exclude attempted request IDs/identical briefs, and retain plan locks/idempotency and staff/repository/engine checks. No-work releases typed content allowances but still counts paid Copilot usage; infer explicit brief file paths for bounded preflight, and preserve actual-change cooldowns. Manual/Amp runs never automatically spawn replacements.
