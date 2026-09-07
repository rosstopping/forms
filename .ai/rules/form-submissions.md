---
paths:
  - 'app/{Http/Controllers/Admin/*FormSubmission*,View/Composers/NavigationComposer.php,Support/WebsiteNavigation.php},resources/views/{layouts/app.blade.php,admin/form-submissions/**}'
---

# Form Submissions

## Scope Leads to the selected website
Leads, lead summaries, bulk actions, and navigation badge counts must use the persisted current website selected by the website switcher, including for global admins. Do not render a website filter in Leads. Show the Leads navigation only when the selected website has at least one detected Form, and preserve the Leads destination when switching websites.
