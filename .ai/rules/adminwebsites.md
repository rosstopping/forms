---
paths:
  - 'app/{Http/Controllers/Admin/WebsiteAiQuestion*Controller.php,Mail/WebsiteAiQuestionReported.php,Models/WebsiteAiQuestion.php},resources/views/admin/{websites/show.blade.php,website-ai-question-report.blade.php}'
---

# Adminwebsites

## Report and credit website AI answers safely
Only the user who asked a completed or failed website AI question may report it, and duplicate reports must not send duplicate admin emails. Admin credits are idempotent; credited questions remain in the audit trail but are excluded from that user's per-website weekly allowance count.
