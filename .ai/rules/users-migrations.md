---
paths:
  - 'app/{Models/User.php,Http/Controllers/Admin/{DashboardController.php,OnboardingCallController.php,UserOnboardingCallController.php,WebsiteHealthReportController.php},Http/Requests/UpdateUserOnboardingCallRequest.php},resources/views/{admin/dashboard.blade.php,admin/users/index.blade.php,layouts/app.blade.php},routes/web.php,database/migrations/**/*onboarding*progress*.php'
---

# Users Migrations

## Track and guide onboarding calls
During an active onboarding trial, show a prominent dashboard call CTA, actionable onboarding checklist, and a smaller app-wide reminder until the call is completed. Send booking links through the tracked onboarding route; booking-started is a funnel event, while booked/completed are admin-confirmed states. Checklist completion must come from persisted ownership, Search Console, call, and health-report-view activity—not presentation-only flags.
