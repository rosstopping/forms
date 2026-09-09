---
paths:
  - 'resources/views/{auth/**,layouts/auth.blade.php,components/auth/**},app/Http/Controllers/Auth/**,app/Http/Requests/{ResetPasswordRequest,SendPasswordResetLinkRequest}.php'
---

# Http Requests

## Share the Sitewell auth design and use broker password resets
Sign-in, signed invitations, trial profile completion and password resets share layouts.auth and auth field/submit components. Keep signed setup URLs, CSRF/method fields, field-level errors and success feedback intact. Password recovery uses Laravel's broker with guest/throttle middleware and a generic email-request response; it remains separate from invitation acceptance and must not change trial or website permissions.
