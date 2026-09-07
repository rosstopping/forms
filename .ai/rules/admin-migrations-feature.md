---
paths:
  - '{app/Models/WebsiteDomain.php,app/Actions/{ActivateWebsiteAuditTrial.php,VerifyWebsiteDomainFromSearchConsole.php},app/Services/{SourceWebsiteResolver.php,PixelSiteResolver.php,TurnstileVerifier.php},app/Http/Controllers/Admin/SearchConsoleController.php,database/migrations/**/*ownership*website*domain*.php,tests/Feature/{FreeSiteAuditTest.php,ContentGenerationTest.php,FormSubmissionTest.php,PixelPayloadApiTest.php}}'
---

# Admin Migrations Feature

## Isolate unverified onboarding domains
Onboarding creates a pending, non-exclusive WebsiteDomain so an existing verified domain cannot block signup. Only verified domains participate in trusted form, Turnstile, or Pixel resolution. Search Console promotes a matching siteOwner property; URL-prefix properties match the exact host while domain properties may cover www. Never auto-transfer a verified conflict or expose another workspace's data.
