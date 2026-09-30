---
paths:
  - '{config/ppc.php,app/Support/MarketingJourney.php,app/Models/MarketingConversion.php,app/Events/MarketingConversionRecorded.php,app/Http/Controllers/{PpcLandingController,CalWebhookController,FreeSiteAuditController}.php,resources/{js/marketing-events.js,views/marketing/ppc.blade.php,views/layouts/ppc.blade.php}}'
---

# Marketing Layouts

## Keep PPC intent, offer and conversion semantics explicit
PPC pages use individually authored config entries and the existing anonymous audit. Preserve existing organic service canonicals for overlapping intents; only self-canonical PPC pages enter the sitemap. Pricing follows MembershipPlan active Growth offer and VAT rules, not a permanent hardcoded £316. Browser hooks queue sitewellEvents and dispatch sitewell:marketing-event without analytics vendors; authoritative audit/booking records emit MarketingConversionRecorded with stable event_id and unique deduplication keys. Only audit_submitted and signed confirmed call_booked are lead conversions. Future consent-aware analytics must choose one delivery path and deduplicate by event_id. Never count views, starts or calendar redirects as leads. Attribution stays in session and is snapshotted on the audit so email claims keep it.

## Keep PPC intent, offer and conversion semantics explicit
PPC pages use individually authored config entries and the existing anonymous audit. Preserve existing organic service canonicals for overlapping intents; only self-canonical PPC pages enter the sitemap. Pricing follows MembershipPlan active Growth offer, not a permanent hardcoded £316. Sitewell does not charge VAT; never add VAT qualifiers to marketing or account pricing. Browser hooks queue sitewellEvents and dispatch sitewell:marketing-event without analytics vendors; authoritative audit/booking records emit MarketingConversionRecorded with stable event_id and unique deduplication keys. Only audit_submitted and signed confirmed call_booked are lead conversions. Future consent-aware analytics must choose one delivery path and deduplicate by event_id. Never count views, starts or calendar redirects as leads. Attribution stays in session and is snapshotted on the audit so email claims keep it.
