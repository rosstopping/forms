---
paths:
  - 'app/{Services/GoogleAds*,Http/Controllers/Admin/GoogleAdsController.php,Http/Requests/StoreGoogleAdsCampaignDraftRequest.php},resources/views/admin/websites/google-ads.blade.php'
---

# Admin Requests Views Admin Websites

## Google Ads campaigns require review and stay paused
Google Ads is connected per website through OAuth and an explicitly verified client account. Search Console gaps are research prompts, not automatically selected keywords. Campaign creation validates the landing page against the verified website domain, previews budget/location/copy, and creates Search campaigns paused; never automatically enable spend. A conversion action existing in Ads does not prove a tag fires on the client site, so tracking requires a real test before activation.
