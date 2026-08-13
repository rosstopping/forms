---
paths:
  - 'app/{Jobs/GeneratePagePixelOptimisations.php,Http/Controllers/Admin/*ReportOptimisationsController.php}'
---

# Jobs Controllers Admin

## Queue report-wide Pixel generation per eligible page
Bulk report generation must queue one unique job per page and only include warning/failed checks the automated generator supports. Never run many AI prompts in the report HTTP request. Report-wide approval may deploy the reviewed drafts together but must preserve individual optimisation histories.
