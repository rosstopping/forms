---
paths:
  - 'app/{Models/{FormSubmission,ReviewInvitation}.php,Jobs/Send*Invitation.php,Services/ReviewInvitationService.php,Http/Controllers/Admin/*ReviewInvitationController.php},resources/views/admin/form-submissions/**'
---

# Views Admin Form Submissions

## Review invitations require explicitly completed work
work_completed is distinct from won: winning the sale never implies work is finished. Completed work is closed for follow-up counts and delivery. Review invitations require an explicit manager send from a preview and a configured website HTTPS review link; never send on a status change or backfill Won leads. Snapshot the recipient/content/link, reserve one invitation per lead and recheck eligibility before sending. A persisted sending claim prevents duplicate retries; failures mean sending is unconfirmed, not permission to resend. Sent records do not prove a review was submitted.
