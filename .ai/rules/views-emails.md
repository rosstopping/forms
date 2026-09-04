---
paths:
  - 'app/{Jobs,Mail,Services,Console/Commands}/**/*.php,resources/views/emails/**'
---

# Views Emails

## Restrict viewer email delivery
Website members with the viewer role receive only weekly ranking updates and website health audit emails (apart from account/invitation transactions). Exclude viewers from operational/action emails. Viewer report copies must not contain direct GitHub links; render GitHub-backed update details as non-linked text.
