---
paths:
  - 'app/{Jobs/CreateGoogleAdsCampaign.php,Services/GoogleAdsCampaignCreator.php,Http/Controllers/Admin/GoogleAdsController.php,Models/GoogleAdsCampaignDraft.php},resources/views/admin/websites/google-ads.blade.php'
---

# Jobs Controllers Admin Views Admin Websites

## Create Google Ads campaigns in one queued, non-retrying attempt
The form records a pending campaign request and queues Google Ads validation plus paused creation, returning immediately. A queued job makes one attempt only: validation-stage failures become failed; once the real mutation begins, any missing/error/timeout response stays uncertain until the account is checked. Keep request-key and same-name pending/uncertain duplicate protection, and show queued, failed, uncertain, or created status in the workspace.
