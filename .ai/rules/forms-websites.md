---
paths:
  - 'app/{Jobs/SendFormSubmissionAcknowledgement.php,Services/FormSettingsResolver.php,Http/Controllers/FormSubmissionController.php},resources/views/{admin/forms/show.blade.php,admin/websites/show.blade.php,emails/form-submission-acknowledgement*.blade.php}'
---

# Forms Websites

## Queue delayed form autoresponders with submission tags
Autoresponder messages are rendered from a submission snapshot and dispatched through SendFormSubmissionAcknowledgement using the resolved website/form delay. Templates may reference any submitted field as {field_name}; keep the fixed website/form tags, plain minimal email presentation, spam suppression, and delivery activity timestamps intact.
