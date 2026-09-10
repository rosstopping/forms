---
paths:
  - 'app/{Models/LeadTag.php,Models/FormSubmission.php,Http/Controllers/Admin/*FormSubmission*,Http/Requests/*FormSubmission*},resources/views/admin/form-submissions/**'
---

# Admin Form Submissions

## Keep lead tags and saved filters website scoped
Lead tags are a reusable vocabulary within one website, with case/whitespace-normalized names. Managers may assign/remove them; viewers remain read-only. Removing a tag from a lead retains it for reuse. Status-only updates must preserve tags. Persist filters per user and selected website, and carry tag filters through all-matching bulk actions so hidden leads are never included.
