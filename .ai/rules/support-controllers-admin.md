---
paths:
  - 'app/{Support/WebsiteNavigation.php,Http/Controllers/Admin/CurrentWebsiteController.php}'
---

# Support Controllers Admin

## Preserve Google Ads when switching websites
The app layout posts currentWebsiteSection with the website switcher. Keep every emitted section, including google-ads, in WebsiteNavigation::SECTIONS and map it to the correct route. When a selected website lacks Ads access, preserve the site selection and open its health section instead of sending the user back through validation.
