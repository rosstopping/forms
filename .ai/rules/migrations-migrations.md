---
paths:
  - '{app/Models/GoogleAdsCampaignDraft.php,app/Services/GoogleAds{Client,CampaignCreator}.php,database/migrations/*google_ads_campaign_drafts*.php,database/migrations/*sitewell*service*campaign*.php}'
---

# Migrations Migrations

## Create the UK service campaign once and keep it paused
The October 8 service campaign targets the whole UK at £20 average daily budget, with an initial £3 max CPC, buying-intent exact keywords and campaign-level phrase negatives for checker/tool searches. Its deploy migration seeds only the verified Sitewell domain's expected GBP Ads connection and queues existing non-retrying creation after commit; preserve the deterministic request ledger on rollback. Country-targeted drafts resolve and validate an enabled Google Country geo target before mutation and use PRESENCE targeting; existing city-radius drafts retain proximity targeting. Always create paused and review tracking before enabling.
