---
paths:
  - 'app/Services/GoogleAdsClient.php,app/Http/Controllers/Admin/GoogleAdsController.php,resources/views/admin/websites/google-ads.blade.php,tests/Feature/GoogleAdsConnectionTest.php'
---

# Views Admin Websites Feature

## Choose Google Ads accounts by name
After Ads OAuth, list direct client accounts with customer descriptive names and expand directly accessible manager accounts through customer_client to include enabled visible client accounts. Offer only selectable client accounts in a named dropdown with IDs for disambiguation; do not require users to enter customer or manager IDs. On submit, confirm the selected account is still in the discovered list and verify it with the Ads API using the manager login ID where needed. Never allow selecting a manager account itself.
