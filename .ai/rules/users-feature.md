---
paths:
  - '{app/Models/User.php,app/Http/Controllers/Admin/UserImpersonationController.php,resources/views/{admin/users/index.blade.php,layouts/app.blade.php},routes/web.php,tests/Feature/UserImpersonationTest.php}'
---

# Users Feature

## Restrict and clearly mark impersonation
Only administrators may start impersonation, and admin accounts cannot be impersonated. Use CSRF-protected POST/DELETE application routes around lab404/laravel-impersonate, show a persistent return-to-admin banner, and protect billing mutations and profile updates while impersonating.
