---
paths:
  - 'app/{Models/Website.php,Policies/WebsitePolicy.php,Http/Controllers/Admin/WebsiteMemberController.php,Http/Requests/*WebsiteMemberRequest.php},resources/views/admin/websites/**,routes/web.php'
---

# Requests Views Admin Websites

## Use manager and viewer website roles
Website access is presented and managed only as Manager or Viewer; do not expose the legacy owner role or Assign owner control. Managers may add, change, and remove website users. Keep websites.user_id only as the internal subscription sponsor: a pivot role overrides its access, and removing that user transfers sponsorship to another manager when available or clears it.
