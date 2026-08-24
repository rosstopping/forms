---
paths:
  - 'app/{Http/Controllers/Admin,Services,Jobs}/**/*Pixel*.php,resources/views/admin/{websites/**,website-health-reports/**},routes/web.php'
---

# Admin Services Jobs Views Adminwebsites

## Gate Pixel at Growth membership
Pixel is a Growth-and-above website feature, not an admin-only workspace. Resolve entitlement from the website owner so shared managers inherit access; global admins retain the standard support bypass. Keep every Pixel mutation route behind membership:growth and website manageability checks.
