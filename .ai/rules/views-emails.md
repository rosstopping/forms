---
paths:
  - 'app/{Jobs,Mail,Services,Console/Commands}/**/*.php,resources/views/emails/**'
  - 'app/{Jobs,Mail,Services,Console/Commands}/**/*.php,resources/views/emails/**,routes/console.php'
---

# Views Emails

## Restrict viewer email delivery
Website members with the viewer role receive only weekly ranking updates and website health audit emails (apart from account/invitation transactions). Exclude viewers from operational/action emails. Viewer report copies must not contain direct GitHub links; render GitHub-backed update details as non-linked text.

## Monthly search reports use complete stored months
Monthly ranking reports are separate from the existing weekly report. Send on the 8th after the monthly Search Console history has synced, compare only complete stored calendar months, keep verified Search Console figures distinct from third-party SEO estimates, and exclude viewer website members.
