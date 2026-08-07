# Website Health Reports

## Goal

Give each managed website a useful weekly health report that combines public website checks with form-delivery health known only to this application.

## MVP scope

- A per-website setting enables or disables weekly reports.
- A queued audit checks up to 25 internal pages, two links deep, using the sitemap first and discovered internal links as a fallback.
- Reports are stored as structured results and rendered as authenticated HTML pages.
- Weekly email summaries go to all administrators and the website owner, with duplicate addresses removed.
- Administrators can run a report immediately.
- Owners can view reports for websites assigned to them.
- Reports retain history and compare the current result with the previous report.

### Checks

- Availability: HTTP status, response time, redirects, and HTTPS.
- On-page SEO: title, meta description, H1, canonical, robots directives, language, viewport, image alt text, and structured data syntax.
- Site-wide SEO: unavailable and redirecting pages, duplicate or missing metadata, noindex pages, thin content, and missing image alternatives.
- Discoverability: `robots.txt` and `sitemap.xml` availability.
- Security configuration: HSTS, Content Security Policy, content-type protection, frame protection, and referrer policy.
- Forms health: submission volume, spam volume, legitimate submissions, email failures, webhook failures, and last legitimate submission.

## Architecture

- Store report state, summary counts, category scores, check results, and comparison data in `website_health_reports`, with page-level evidence in `website_health_report_pages`.
- Dispatch one unique queued job per website so slow or failing sites cannot block other reports.
- Run a daily scheduler that selects enabled websites whose last completed report is at least seven days old.
- Keep outbound requests bounded with registered-domain validation, public-address validation, timeouts, response-size limits, and redirect limits.
- Render one shared report partial into the admin HTML page and the email where practical.
- Keep report pages behind the existing administrator/website-owner authorization boundary.

## Later phases

1. Add PageSpeed Insights mobile/desktop data and Core Web Vitals when API configuration is available.
2. Integrate Google Search Console for impressions, clicks, query movement, and indexing information.
3. Add optional deeper crawls and link-level broken-link evidence for larger websites.
