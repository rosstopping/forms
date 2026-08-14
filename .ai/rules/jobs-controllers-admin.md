---
paths:
  - 'app/{Jobs/GeneratePagePixelOptimisations.php,Http/Controllers/Admin/*ReportOptimisationsController.php}'
  - 'app/{Jobs/BuildWebsite.php,Http/Controllers/Admin/WebsiteBuilderController.php,Models/WebsiteBuild.php}'
---

# Jobs Controllers Admin

## Queue report-wide Pixel generation per eligible page
Bulk report generation must queue one unique job per page and only include warning/failed checks the automated generator supports. Never run many AI prompts in the report HTTP request. Report-wide approval may deploy the reviewed drafts together but must preserve individual optimisation histories.

## Run website builds as durable queued jobs
Website builder submissions must persist a WebsiteBuild containing onboarding data and IDs, then dispatch an encrypted unique job. Resolve GitHub credentials inside the worker, never queue tokens. Persist queued/running/completed/failed states so API failures are visible after the browser request ends.
