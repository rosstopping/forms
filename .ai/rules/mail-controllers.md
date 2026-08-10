---
paths:
  - 'app/{Mail/FormSubmissionReceived.php,Http/Controllers/FormSubmissionSpamController.php}'
---

# Mail Controllers

## Confirm email spam reports before mutation
Email spam-report links must use expiring signed URLs that open a confirmation page. Never mark spam on the initial GET because email security scanners follow links automatically; only the signed POST may set is_spam=true.
