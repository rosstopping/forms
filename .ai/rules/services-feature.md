---
paths:
  - 'app/Services/BacklinkAuditService.php,tests/Feature/BacklinkAuditTest.php'
---

# Services Feature

## Map backlink domain intersections from nested targets
DataForSEO backlink domain-intersection items do not expose the referring domain at item.domain. Read it from each domain_intersection.{target-number}.target entry; target numbers map to the request's competitor order. Entries in one item must normalize to the same referring domain, and stored evidence should retain only bounded metrics used by the audit.
