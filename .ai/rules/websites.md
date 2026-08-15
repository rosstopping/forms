---
paths:
  - 'app/{Http/Controllers/Admin/WebsiteMemberController.php,Http/Requests/StoreWebsiteMemberRequest.php,Notifications/WebsiteInvitation.php},resources/views/admin/websites/show.blade.php,routes/web.php'
---

# Websites

## Invite website members without account enumeration
Only an active Growth or Complete website-owner subscription may add website members (admins bypass). Accept an email address rather than querying/listing unrelated users. New accounts receive a one-time signed setup link; existing accounts receive a sign-in invitation and must never be given a password-reset link controlled by the inviter.
