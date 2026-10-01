---
paths:
  - 'app/Services/WebsiteActionCenter.php,database/migrations/*seo_opportunities*.php'
---

# Services Migrations

## Grouped website actions may link multiple SEO opportunities
WebsiteActionCenter queues one ContentRequest for all SEO findings grouped under a page. seo_opportunities.content_request_id must use a non-unique index (while retaining its foreign key), and deleting a queued request must reopen every linked finding. Do not restore a unique constraint on this column.
