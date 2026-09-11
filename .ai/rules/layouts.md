---
paths:
  - 'app/{Http/Middleware/ResolveCurrentWebsite.php,Support/WebsiteNavigation.php,Models/User.php},resources/views/layouts/app.blade.php,routes/web.php'
---

# Layouts

## Persist and authorize current website navigation
Treat the website in a contextual route as authoritative, otherwise use the user's persisted current_website_id and fall back to their first accessible website. Populate the switcher only through Website::accessibleTo(); switching must never accept an inaccessible website. Customer navigation links directly to website sections while the all-websites directory remains admin-only.
