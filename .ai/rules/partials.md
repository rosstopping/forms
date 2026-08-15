---
paths:
  - 'app/{Http/Controllers/Admin/BusinessProfileController.php,Services/BusinessProfileClient.php},resources/views/admin/websites/partials/business-profile.blade.php'
---

# Partials

## Guard incomplete Business Profile connections
A stored Google authorization is not a usable Business Profile connection until location_name is selected. Hide audit, review-sync, post, and automation controls in that state; show Select location and Reconnect Google actions instead. Controllers must redirect with an actionable message rather than allowing BusinessProfileClient location/auth RuntimeExceptions to reach the user.
