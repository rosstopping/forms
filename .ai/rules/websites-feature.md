---
paths:
  - '{app/Http/Controllers/Admin/WebsiteController.php,resources/views/admin/websites/show.blade.php,tests/Feature/DashboardInformationArchitectureTest.php}'
---

# Websites Feature

## Guide unconnected Growth content users
For non-admin Growth/Complete users, treat Content delivery as connected only after a Pixel heartbeat, WordPress connection, or selected GitHub repository exists. Until then, show all three connection options and a support-call CTA; active onboarding trials must use the tracked onboarding-call route.

## Show connection guidance in support contexts
The unconnected Content state applies whenever Growth access is effective, including admins and impersonation/support contexts. Do not hide it based on the acting user's admin role; suppress it only once Pixel heartbeat, WordPress, or GitHub delivery is actually connected.
