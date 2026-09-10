---
paths:
  - 'app/{Models/FormSubmission.php,Http/Controllers/Admin/FormSubmissionController.php,Http/Requests/*LeadRequest.php},resources/views/{layouts/app.blade.php,admin/form-submissions/**}'
---

# Layouts Form Submissions

## Manual leads do not require contact forms
This supersedes the forms-only Leads navigation rule: show Leads whenever a website is selected, including sites with no forms. Managers may create leads only in the current website, with a submitted website ID checked against that selection. Manual leads use is_manual=true and a null form_id; never infer manual origin from a missing/deleted form. Creation records the actor and optional internal reminder but sends no form acknowledgements, notifications or webhooks. Contact edits apply only to manual leads; retain the original payload of form submissions.
