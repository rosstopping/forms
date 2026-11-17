---
paths:
  - '{app/Services/SpamDetector.php,config/forms.php,tests/Feature/FormSubmissionTest.php}'
---

# App Services Feature

## Allow links in genuine enquiries
Genuine enquiries may include agency URLs and links to requested products or villas. Ordinary URLs alone must not quarantine a submission by default, including after successful Turnstile verification. Keep links a weak configurable signal alongside independent spam evidence and preserve Turnstile, honeypot and rate-limit checks.
