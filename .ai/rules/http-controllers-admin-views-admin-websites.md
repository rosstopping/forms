---
paths:
  - 'app/Http/Controllers/Admin/**,resources/views/admin/websites/**'
  - 'app/Http/Controllers/Admin/{WebsiteController,SeoImpactController}.php,resources/views/admin/websites/**'
---

# Http Controllers Admin Views Admin Websites

## Keep website form redirects and navigation in their URL section
Website sections are selected server-side from WebsiteNavigation/currentWebsiteSection and link to admin.websites.section. Do not restore client-side/sessionStorage selection for the website container. SEO sub-sections use seo_section links and server-rendered visibility. Successful website mutations must redirect to their relevant section (Content, Search, Forms, Business Profile etc.), not the generic admin.websites.show default; validation returns to the originating URL.

## Keep SEO impact inside SEO Intelligence
SEO impact lives in the SEO Intelligence impact sub-section, after Recommended Actions (Overview remains first). List, pagination and individual impact details use admin.websites.section with section=seo and seo_section=impact; seo_impact selects a website-scoped detail. Back links and saved-form redirects preserve that context. Content only provides a cross-link to this canonical workspace; legacy standalone impact GET routes redirect there.
