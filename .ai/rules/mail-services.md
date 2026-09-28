---
paths:
  - 'app/{Mail,Services}/**/*ProspectOutreach*.php'
---

# Mail Services

## Include an optional video in initial outreach
Initial outreach includes the prospect showcase video and available thumbnail whenever a video URL is present. Admin tests use direct untracked URLs; live sends reserve a tracked showcase_video link. An initial email without a video renders without a video section. Do not restore blanket suppression of initial-email videos.

## Initial audit block is opt in
Persist the initial-email Include site audit checkbox as Prospect.include_site_audit, default false; changing it resets approval. Test and live initial emails include the available audit only when selected, immediately after any video and before the contact block. Live audit links use the delivery tracking ledger; test links remain untracked. Personalised-video emails retain their existing audit placement.
