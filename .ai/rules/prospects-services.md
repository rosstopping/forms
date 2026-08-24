---
paths:
  - 'app/{Services,Jobs,Http/Controllers/Admin,Http/Requests}/**/*PersonalisedVideo*.php,resources/views/admin/prospects/**,app/Services/ProspectEngagementScorer.php'
---

# Prospects Services

## Keep personalised video delivery operator initiated
Crossing hot moves the prospect to NeedsPersonalisedVideo, pauses cold actions, and surfaces the reason queue. Only an authenticated admin form may supply, edit, send, or schedule the video; the lifecycle evaluator must never create or send it. Reuse the delivery snapshot/idempotency ledger, and transition to VideoSent only after successful mail delivery.
