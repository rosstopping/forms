---
paths:
  - 'app/{Services/AutoresponderHtmlSanitizer.php,Services/FormSettingsResolver.php,Mail/FormSubmissionAcknowledgement.php,Http/Controllers/Admin/{FormController.php,WebsiteAutoresponderController.php}},resources/{js/app.js,views/admin/forms/show.blade.php,views/admin/websites/show.blade.php,views/emails/form-submission-acknowledgement*.blade.php}'
---

# Websites Emails

## Autoresponders support text and raw HTML modes
Autoresponder content type defaults to text, which keeps the existing Trix editor and sanitization. HTML mode stores trusted administrator-authored markup without sanitizing it, but submission token replacements must always be HTML-escaped. Forms may inherit the website content type or override it.
