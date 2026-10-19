---
paths:
  - '{app/Models/ContentPlan.php,app/Services/ContentSchedule.php,app/Jobs/{StartContentGeneration,GenerateContentRequestPixelOptimisations}.php,app/Http/Controllers/Admin/{ContentPlanController,ManualContentRequestController,ContentRequestController}.php,resources/views/admin/{content-queue,websites/partials/content*}.blade.php}'
---

# Content Queuewebsites Partials

## Manual content engine uses a personal staff queue
Manual is a staff-selected content engine: saving it disables scheduled execution, queued automated content/Pixel jobs must skip it, and generation/retry controls must not fall through to Copilot. My content queue spans accessible sites with approved pending manual requests plus the acting admin’s active reservations; preserve bump/FIFO order, request safeguards and saved prompt snapshots. Start reserves work; done records manual completion only, then returns to the next task. Manual settings and requests work without GitHub or Pixel connections.
