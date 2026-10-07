---
paths:
  - '{app/Http/Requests/SendWebsiteAuditReviewRequest.php,app/Http/Controllers/Admin/WebsiteAuditReviewController.php,app/Mail/WebsiteAuditPersonalReview.php,resources/views/{admin/onboarding-leads/index,mail/website-audit*,marketing/website-audit}.blade.php}'
---

# Onboarding Leads Indexmail

## Fulfil personal audit reviews with a Loom video
Supersedes the three written priority fields: Admin Onboarding accepts a single HTTPS loom.com/share URL and sends a personal review email with a Watch your website review button. Store the link under personal_review.loom_url; preserve send-once protection, reply-to, consent, booking and report links. Public and acknowledgement copy promises a personal video review. Retain rendering of old saved priority arrays for already queued legacy review emails.
