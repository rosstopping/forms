---
paths:
  - 'resources/views/admin/websites/**,routes/web.php'
  - resources/views/admin/websites/show.blade.php
  - resources/views/admin/websites/google-ads.blade.php
---

# Admin Websites

## Preview paid features while enforcing server gates
Keep Search, SEO Intelligence, Content automation, Google Business Profile, and other paid feature areas visible to lower tiers as locked previews with an upgrade banner linking to Billing. Never render active mutation controls in a locked preview, and retain membership middleware on every paid action route.

## Keep website data chat contextual
Render the website data assistant as a fixed bottom-right chat widget only on the website detail page, not as a website tab or global layout element. Keep the lower-tier locked preview in the widget and reopen it after answers or question validation errors.

## Keep campaigns list focused on review
The Campaigns tab shows one account-wide 30-day summary, then status-filtered campaign rows. Avoid repeating the same performance figures within a single campaign, and use the Create campaign tab instead of a second create button. Keep the external Google Ads link on the individual campaign review page only; group status controls and tuck removal under More actions.
