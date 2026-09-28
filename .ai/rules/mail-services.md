---
paths:
  - 'app/{Mail,Services}/**/*ProspectOutreach*.php'
---

# Mail Services

## Include an optional video in initial outreach
Initial outreach includes the prospect showcase video and available thumbnail whenever a video URL is present. Admin tests use direct untracked URLs; live sends reserve a tracked showcase_video link. An initial email without a video renders without a video section. Do not restore blanket suppression of initial-email videos.
