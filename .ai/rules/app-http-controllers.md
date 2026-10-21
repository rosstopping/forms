---
paths:
  - app/Http/Controllers/FormSubmissionController.php
---

# App Http Controllers

## Issue marketing receipts only for accepted saved enquiries
The public form handler deliberately returns normal success for quarantined spam. Browser Lead tracking must require conversion_event_id, not success=true, a button click, or a success URL. Receipts are opaque keyed hashes of persisted submission IDs; spam receives no receipt. Do not send form contents to advertising platforms.
