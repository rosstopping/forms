---
paths:
  - 'app/{Models/Website.php,Support/MembershipPlan.php,Services/FormSettingsResolver.php,Http/Controllers/{FormSubmissionController.php,Admin/FormController.php,Admin/WebsiteAutoresponderController.php},Http/Requests/UpdateWebsiteAutoresponderRequest.php},resources/views/admin/{forms/show.blade.php,websites/show.blade.php},config/{forms.php,mail.php}'
---

# Forms

## Keep form setup simple and gate automatic replies
Forms show essential notification controls first; installation instructions, webhooks, and per-form overrides belong in native disclosures. Do not render Postmark setup while that workflow is unfinished. Customer acknowledgements always use mail@digizu.co.uk, and automatic customer replies are Growth/Complete only; enforce this in mutations and delivery resolution, not just the UI.

## Essential includes automatic customer acknowledgements
This supersedes the Growth/Complete-only automatic-reply entitlement. Active Essential, Growth and Complete subscriptions include autoresponders; preserve owner-based active membership checks and admin support access. Do not enable replies automatically or replay old submissions. Existing sender, delay, spam and delivery-limit rules remain unchanged.
