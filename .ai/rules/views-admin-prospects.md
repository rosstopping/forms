---
paths:
  - 'app/{Services,Models}/**/*Prospect*.php,config/outreach.php,resources/views/admin/prospects/**'
---

# Views Admin Prospects

## Allow one post-video email then require manual handling
VideoSent may send exactly one configured PostVideoFollowUp through the central due-action sequence. After that, clear next_action_at permanently. Positively scored audit, Sitewell, video, or booking events after video delivery may create/update persisted manual-follow-up reasons; opens and scanner/zero-score events never do. Manual recommendation pauses automation and must not trigger another email.

## No automatic emails after personalised video
This supersedes the earlier one-post-video-email rule. Sending a personalised video clears next_action_at and next_follow_up_at; do not schedule or send a PostVideoFollowUp, including legacy due actions. Keep historical delivery records and engagement tracking. Strong scored clicks after video delivery may immediately recommend manual follow-up without waiting for another email.
