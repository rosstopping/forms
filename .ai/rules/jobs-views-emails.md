---
paths:
  - 'app/Jobs/SendWeeklyRankingReport.php,resources/views/emails/weekly-ranking-report.blade.php'
---

# Jobs Views Emails

## Wait briefly for fresh weekly target evidence without buying checks
Weekly ranking email waits in five-minute intervals for up to one hour from dispatch when automatic SEO snapshots are enabled and active targets lack successful observations for the dispatch week in the configured market. Preserve the deadline and reporting week across retries, then send dated latest evidence with a missing-fresh-result label. Email delivery must never initiate paid checks; disabled automatic checks and archived targets must not delay email.
