---
paths:
  - 'app/{Services,Models}/**/*Prospect*.php,config/outreach.php,resources/views/admin/prospects/**'
---

# Views Admin Prospects

## Allow one post-video email then require manual handling
VideoSent may send exactly one configured PostVideoFollowUp through the central due-action sequence. After that, clear next_action_at permanently. Positively scored audit, Sitewell, video, or booking events after video delivery may create/update persisted manual-follow-up reasons; opens and scanner/zero-score events never do. Manual recommendation pauses automation and must not trigger another email.
