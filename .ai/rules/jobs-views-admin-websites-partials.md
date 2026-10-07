---
paths:
  - '{app/Services/{ManualContentRequest,ContentGenerationPromptGenerator,ContentWorkSelector}.php,app/Models/ContentRequest.php,app/Jobs/GenerateContentRequestPixelOptimisations.php,resources/views/admin/websites/partials/content*.blade.php}'
---

# Jobs Views Admin Websites Partials

## Reserve manual content work separately from copying
Admins can preview/copy a website-scoped request prompt without starting automation or changing queue state. Taking manual work snapshots the prompt and sets picked_up_at to exclude it from shared queue selection; synchronize with running Copilot work and Pixel drafting. Return clears the reservation while preserving queue order. Completion records manual activity, not live publication or SEO measurement. Include active/recent manual work in prompt history and overlap/cooldown selection.
