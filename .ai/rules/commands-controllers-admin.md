---
paths:
  - 'app/{Models/FormSubmissionFollowUpReminder.php,Jobs/SendLeadFollowUpReminder.php,Console/Commands/DispatchDueLeadFollowUpReminders.php,Http/Controllers/Admin/FormSubmissionController.php}'
---

# Commands Controllers Admin

## Follow-up emails require a newly scheduled reminder
Only setting or changing a lead's follow-up date creates a reminder; never backfill existing dates. Send one internal email when due, to an eligible managing assignee or otherwise an eligible owner, excluding viewers and closed/spam leads or inactive websites/memberships. Recheck at send time. Lock the submission before its reminder in both scheduling and sending; persist terminal state and keep the mail send inside the queued job, not a separately queued mailable.
