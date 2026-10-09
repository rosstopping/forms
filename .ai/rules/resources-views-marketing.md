---
paths:
  - resources/views/marketing/free-site-audit.blade.php
  - resources/views/marketing/website-audit.blade.php
  - 'resources/views/marketing/**'
  - resources/views/marketing/audit-email-gate.blade.php
  - 'resources/views/marketing/{website-audit,audit-email-gate}.blade.php'
---

# Resources Views Marketing

## Keep Get started as one focused audit form
The /get-started page has one concise heading, one sentence explaining the public search and technical check, and one website-domain form with a single Get your free search audit action. Keep the input and button as one rounded control, a compact mobile label, inline validation, CSRF, honeypot, marketing attribution, and configured Turnstile. Submission uses the existing IP/domain-throttled anonymous audit route and redirects to the existing website audit page. Avoid secondary sales sections, repeated checklists, and links that compete with the form.

## Explain the audit briefly beside the single form
Keep /get-started concise and focused on one website-domain audit form, but include a short report promise and three compact, factual checks beneath it: search setup (titles/headings/sitemap/crawl access), website health (homepage response/mobile setup/image text), and security basics (HTTPS/browser headers). This supersedes the earlier one-sentence-only rule. Avoid a second sales section, duplicate CTA, or claims of ranking measurement; retain the existing spam protections and redirect.

## Show audit fix count and managed SEO work plan
The public audit result shows one count of warning/failed checks as website fixes flagged, not itemized technical findings, scores, or pass/fail metrics. Explain that this is an initial public scan and the wider site needs review. After the count, present concise ordered work: fix issues; review and improve existing pages against customer searches; publish useful content for unmet questions; keep the site current and earn/monitor relevant backlinks; measure enquiries, search performance and AI citations where available, then iterate. Do not claim the scan measured rankings, content relevance, backlinks, or AI visibility. Completed-result CTA is Book a call with Ross, not email claim/account signup; leave existing private continuation routes intact for prior customers. Keep pending/failure states simple.

## Bound public audit enrichment and label projections
Public audit reports show measured technical checks and sitemap page counts, plus cached DataForSEO UK Google rankings, search volume, traffic estimates, and backlink summaries when credentials are configured. Limit enrichment to one domain overview, one 20-keyword sample (only when rankings exist), and one backlink summary; cache provider data for seven days and show unavailable when data is missing. Technical health is passed checks / total checks, not a search ranking score. Six-month numbers are conditional scenarios, never promises. Keep the managed SEO plan and Book a call with Ross CTA concise.

## Use a restrained pending audit loader
The pending website audit shows a compact animated search mark, a single Building your audit heading, and concise copy that the report appears automatically. Keep the real status polling and avoid fake percentage progress or repetitive checking text; support reduced-motion preferences.

## Interpret audit stats with restrained status colour and a clear projection
Emphasise technical health, flagged fixes, and page-one Google terms with conditional green/amber/rose status treatments; leave page counts, traffic estimates, and referring domains neutral because raw counts have no universal good/bad threshold. Compare current estimated monthly organic visits with a clearly labelled six-month illustrative range. Keep projection assumptions accessible but out of the primary reading path, and derive the optimistic end from stated ranking/traffic assumptions, not an arbitrary uplift.

## Keep report email action accessible while scrolling
On a completed public audit that has not yet been emailed, show a compact fixed bottom control that opens the existing email-report dialog immediately. Retain the 30-second automatic prompt, but cancel its timer after manual opening so a dismissed dialog does not reopen. Hide the control after an email request; do not show it during pending or failed audits.

## Center the native audit email dialog explicitly
Tailwind preflight removes the browser's default dialog margin, so showModal alone can place the email popup at the top left. Keep fixed inset-0 m-auto on the dialog, with a viewport-height cap and vertical overflow for mobile. Verify centering at mobile and desktop widths when changing its layout.

## Describe the public audit by outcomes
Supersedes the old Search setup / Website health / Security basics check list on Get started. Explain the audit in short plain-language outcomes: Google visibility and competitors, website issues and health, sampled AI mentions/citations, and prioritised next steps with a conditional six-month scenario. Do not list implementation checks or imply every data source is available for every site; keep one website form and one audit CTA.

## Keep the audit preview visual and evidence-safe
The Get started page uses four compact illustrated previews for Google visibility, technical health, sampled AI answers, and prioritised next steps. Illustrations are decorative and must not show invented rankings, scores, citations, or guaranteed growth; the real report supplies measured data. Keep the single website form and audit CTA, concise copy, and conditional language for AI observations and six-month scenarios.

## Keep completed-audit actions together
Completed audit reports use a fixed bottom action row with Book a call with Ross as the primary action and Get a copy by email beside it until a copy is requested. On mobile, shorten the booking label and show the email action as an accessible 48px icon button. The booking action stays available after the email request.

## Keep SEO copy aligned to the managed service
The current public offer is managed website and SEO improvements for UK businesses, with the free search audit as the primary conversion. Titles, descriptions, schema and internal copy must describe work done for clients rather than self-service SaaS. Keep the homepage audit-only CTA and preserve existing URLs until Search Console evidence supports consolidation.

## Use green for the leading search position
In the public audit competitor comparison, lower Google position numbers indicate the lead. Colour the winning number green for either site, keep the losing or tied number neutral, and do not use the marketing garden token because it is coral on public pages.

## Use directional SVGs in the six-month comparison
The six-month projection comparison uses an inline downward arrow SVG while its cards stack below sm, and an inline rightward arrow SVG at sm and wider. Keep the arrows decorative and aligned with the card direction; do not restore the text arrow.

## Emphasise personal review and show Ross beside booking
The inline personal-review offer uses a black background with white heading and readable muted white supporting text. The booking button includes Ross's supplied portrait (public/ross-topping.jpg) as a small circular image. Use whole responsive label spans for 'Book a call' and 'Book a call with Ross' to preserve desktop word spacing.

## Align review call to action with the copy
Keep the portrait left of a single flexible content column containing the personal-review heading, paragraph and CTA. The button aligns with the text, not the portrait edge. Keep the portrait fixed at 56px, content min-w-0, and readable paragraph width so the card wraps within narrow screens.

## Center the popup portrait above its copy
In the personal-review email dialog, place Ross's small circular portrait at the top centre. Keep the heading and paragraph below it at full available width and left aligned. This supersedes the popup portrait beside the heading or paragraph; the inline black offer retains its portrait beside the content.

## Lead the public audit with health, fixes and the six-month visitor opportunity
Supersedes the qualitative-only snapshot and hiding health scores in Onboarding Leads. Public visitors see measured technical health, warning/failed fix count and the existing data-derived six-month Google-visitor range, before and after email capture. Hide current visit counts, ranking-term counts, positions, competitor/AI detail and itemised fixes; full detail stays admin-only or an expiring signed share link. Keep a compact fix-then-grow plan and free personal video review within one working day, with one prominent growth-plan CTA, secondary Cal popup and once-per-session scroll prompt. A 100% health score is an aim for the checks run, not a promise about every page; work starts after access/scope agreement. Only show a visitor range when the sample supports the existing conditional scenario, label it illustrative/not guaranteed and never invent a fallback number. Zero findings with no checks is unavailable, not a healthy 0-fix result. Preserve engagement telemetry and optional marketing consent.

## Give audit preview stats clear hierarchy
Keep the gated audit preview compact: a shared divided white panel, large left-aligned health/fix values, concise text status and a health meter using the measured score. Use restrained status colours and coral for flagged fixes; unavailable results remain em dashes without a meter or implied success. Labels stay on one line; use container queries and verify the email CTA remains easy to reach on mobile. Do not expose additional report details to strengthen these stats.

## Keep the gated audit focused on the unlock offer
Supersedes the expanded preview stats guidance: keep health and fix counts in a compact secondary strip, with accessible status text and a measured health meter. Lead the gate with specific benefits (rankings, missed searches, prioritised work), place the form beside the offer on desktop, and abbreviate supporting content on mobile so the email field and unlock button appear sooner. Preserve server-side privacy, separate optional marketing consent, and honest data availability; do not invent results, scarcity, or guarantees. Check desktop/mobile CTA placement and overflow.

## Lead the gated audit with Google and AI growth
Supersedes health/fixes-only preview guidance: lead with customer discovery through Google and AI, keep technical health/fixes in a compact supporting row, and describe rankings, competitors and sampled AI checks as the unlock offer. Before capture allow at most one recent (within 30 days) Google ranking in positions 11–30 with measured demand, restricted to terms supported by the stored comparable-page opportunity research; never choose unrelated raw keywords just because they have high volume. Rendering reuses saved initial research without provider calls; other rankings, competitor domains, findings and AI answers stay server-gated. Omit unsupported/undated/stale teasers and label measurement date/desktop estimate. AI checks remain deferred until unlock, with availability stated honestly. Preserve one-field capture and separate optional consent.

## Use a confident search-report unlock with a compact identity preview
Latest user preference supersedes the could-grow headline and broad preview-health panel. Locked ready audits lead with See where you show up, a small domain/screenshot identity thumbnail, and a high-contrast report-offer panel beside the email form. Keep health/fix numbers as small supporting evidence, no prominent bar; shorten supporting copy on mobile so capture remains easy. Keep competitor/AI research after email capture per explicit user correction. Use We’ve got your Google rankings only when saved rankings exist, and a positive sampled-AI appearance teaser only when a successful saved result confirms mention/citation; never imply a check ran or found a positive result without evidence. Preserve gated detailed data, one-field email unlock, separate optional consent, current tracking and signed report links.

## Return the email gate to a simple light report reveal
October 8 pasted redesign brief supersedes the dark split offer, punchy three-part headlines and repeated benefit list. Use Your website audit is ready, one short factual sentence, small health/fix stats and a centred Where should we send it email card over a clearly visible light blurred report layout. Use View my report as the single primary CTA and a short Ross note; keep existing optional consent wording, privacy link and subtle functional expiry. Reuse the existing report-preview structure, keep private result text server-redacted (CSS blur alone must not bypass capture), and avoid fabricated data. Preserve report generation, signed/session unlocks, analytics and after-email competitor/AI research; unfinished checks must not be described as completed.

## Show a larger right-hand audit screenshot and measured ranking count
Latest tweak moves the locked audit screenshot to the right of the introduction at desktop (20rem wide); on mobile keep a larger 7rem thumbnail to the right of the domain. Alongside small health and fix stats, show the saved SEO organic_keywords count as Google rankings with Estimated attribution when numeric, including a genuine zero. Missing ranking data omits the stat rather than inventing zero. Do not add pre-capture competitor/AI stats or provider calls; preserve the light blurred report gate and current copy.

## Caption the right-hand website screenshot with the domain
On locked audit results, place the small coral domain below the right-hand screenshot as a figcaption, for both desktop and mobile. Keep the screenshot frame separate from the caption; when no screenshot is available retain the domain in the introduction.

## Keep the gated audit introduction compact and top aligned
Place the ready heading and introduction at the top of the left column, with supporting stats beneath and their bottom aligned with the right screenshot frame on desktop. Keep the coral domain caption under the screenshot. Category grade rings should be small and grouped at the left, wrapping compactly on mobile. Do not display the sentence 'Grades reflect the homepage checks run, not a full-site assessment.'; retain accessible check counts and genuine saved score calculations.

## Constrain audit grade labels to their circles
Audit category grade items use the circle width (56px mobile, 64px desktop) for their labels too. Labels use 12px text and wrap full names within that width; grades inside rings use 16px. Keep the group left aligned and compact.

## Center the compact audit grade labels
Keep audit category grade labels centered beneath their circles, constrained to the circle width. Preserve the smaller 12px labels and 16px grades; the overall group remains left aligned.

## Start the white audit report with visible grades
Place the compact category grade circles in the unblurred top of the same white rounded report surface as the email gate. The blurred redacted report preview begins beneath this grade row, contained in its own positioned section so blur and overlay do not affect the grades. Preserve the smaller centered labels and genuine score calculations.

## Keep grade labels on one line and the unlock card close
Supersedes circle-width wrapping: keep each grade label centered and on one line at 12px, allowing its item to expand slightly beyond the 56/64px circle. Rings remain centered over their labels and the whole group left aligned. Use a compact 24px mobile/32px desktop gap before the email card beneath the grade header.

## Use a full-width mobile audit screenshot and one stats row
On locked ready mobile audits, show the screenshot at the available content width with the coral domain beneath. Keep health, fixes, pages and rankings in a single four-column row with compact labels and values; mobile label Pages expands to Pages found on larger screens. Preserve desktop screenshot placement and supporting stats alignment.

## Continue the unlocked audit with the shared preview layout
The full audit uses the same compact header, right screenshot/full-width mobile preview, supporting four stats and shared category grade partial as the email gate. Display unlocked details in one white report surface, leading with search overview, rankings, competitors and AI, then website checks and the work plan. Remove service-offer and bottom call panels and the inline Prefer to talk link; only the fixed bottom-right Talk to Ross button remains for completed full reports. Retain legacy requested-video status, optional business-goal form, Cal popup/tracking, server gating and deferred research.

## Blur the full report under one finalising loader
For unlocked ready audits, while full_report.status is queued/running or completed with AI status pending, blur the report surface behind one centered Finalising your full report loader. Clip the background to a compact loading area, mark it inert and aria-hidden, hide the floating booking button and suppress the old per-section processing banner. Poll the existing signed/session status URL and reveal the complete report automatically after completion. Failed/deferred research must not produce an indefinite loader; retain actionable recovery states. Rendering/polling must never queue research.

## Let the final-report loader size its container
The finalising loader must remain in normal flow with min-h-104 and padding so its complete spinner and message always fit. Position only the blurred inert report background absolute inset-0 during loading. Do not cap a full-height report container with max-height and place an absolute loader over it; that can clip the spinner. Verify containment at 320px and 390px mobile widths and desktop.
