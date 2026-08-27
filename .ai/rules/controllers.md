---
paths:
  - 'app/{Http/Controllers/FormSubmissionController.php,Services/SpamDetector.php}'
  - 'app/{Http/Controllers/FormSubmissionController.php,Services/RedirectResolver.php}'
---

# Controllers

## Quarantine suspected form spam silently
Score sanitized public form data with SpamDetector. Persist suspected spam with is_spam=true for auditability, return the normal success response, and never send email or webhooks for it. Keep thresholds and heuristics configurable through config/forms.php.

## Submitted success URL takes response precedence
When a valid `_form_success_url` is present, return its redirect before negotiating a JSON success response or falling back to the submitted page. Keep RedirectResolver's registered-domain and HTTP(S) validation to prevent open redirects.
