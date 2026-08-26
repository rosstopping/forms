---
paths:
  - 'app/{Services/AutoresponderHtmlSanitizer.php,Services/FormSettingsResolver.php,Mail/FormSubmissionAcknowledgement.php},resources/{js/app.js,css/app.css,views/components/trix-editor.blade.php,views/admin/forms/show.blade.php,views/admin/websites/show.blade.php,views/emails/form-submission-acknowledgement*.blade.php}'
---

# Emails

## Keep autoresponder rich text basic and sanitized
Autoresponder bodies use the shared Trix editor with bold, italic, lists, and history controls only; attachments are rejected. Sanitize stored and rendered HTML through AutoresponderHtmlSanitizer, escape all submission tag replacements, retain a plain-text mail part, and keep the delivered email free of layout chrome or side padding.
