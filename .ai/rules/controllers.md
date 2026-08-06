---
paths:
  - 'app/{Http/Controllers/FormSubmissionController.php,Services/SpamDetector.php}'
---

# Controllers

## Quarantine suspected form spam silently
Score sanitized public form data with SpamDetector. Persist suspected spam with is_spam=true for auditability, return the normal success response, and never send email or webhooks for it. Keep thresholds and heuristics configurable through config/forms.php.
