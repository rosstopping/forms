---
paths:
  - 'app/{Services,Jobs,Console/Commands,Http/Controllers/Admin}/**/*BusinessProfile*.php,resources/views/admin/websites/partials/business-profile*.blade.php'
---

# Admin Websites Partials

## Business Profile queue and approval-first replies
Post suggestions enter an explicit topic queue; the weekly schedule drafts the oldest queued topic and does nothing when empty. Review sync automatically drafts unanswered reviews of every rating, preserving existing pending/generating/published replies across syncs. Posts and replies always require a manager's explicit approval before publishing to Google. Keep reviews bounded and paginated; fetch every Google review page. Never name a Queueable job's model property $connection: it conflicts with the trait's queue connection property.
