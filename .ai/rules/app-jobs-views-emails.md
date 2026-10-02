---
paths:
  - 'app/{Jobs/Send*RankingReport.php,Services/GoogleAdsEmailSummary.php,Services/GoogleAdsClient.php,Mail/*RankingReport.php},resources/views/emails/*ranking-report.blade.php'
---

# App Jobs Views Emails

## Keep Google Ads email snapshots conditional and period-matched
Weekly and monthly ranking emails may include one combined Google Ads snapshot for the same reporting period, using only currently enabled campaigns. Omit it unless the website owner has active Complete access, a verified Ads customer is selected, and enabled-campaign metrics are available. Fetch once before recipient delivery; Ads API failures must not block the SEO email. Keep paid and organic results visually separate.
