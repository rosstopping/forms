---
paths:
  - 'app/{Models/Website.php,Http/Controllers/FormSubmissionController.php},resources/views/admin/websites/show.blade.php'
---

# Controllers Views Admin Websites

## Sitewell address supersedes custom autoresponder senders
This supersedes the earlier website-specific autoresponder sender rule. Customer acknowledgement email must always snapshot config('forms.autoresponder_from_address'), defaulting to mail@digizu.co.uk. Do not expose a custom From-address control; the From name may remain website-specific.
