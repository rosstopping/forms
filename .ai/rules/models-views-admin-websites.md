---
paths:
  - 'app/{Jobs,Mail,Http/Controllers}/**/*FormSubmission*.php,app/Models/Website.php,resources/views/admin/websites/show.blade.php'
---

# Models Views Admin Websites

## Keep autoresponder sender website-specific
Website autoresponders may use a custom from name and address. Snapshot the resolved sender when queuing a customer acknowledgement, fall back to the global mail sender, and do not apply this sender to internal new-lead notifications.
