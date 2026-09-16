=== Sitewell by Digizu ===
Contributors: digizu
Tags: website management, maintenance, seo, site audit
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to Sitewell for managed website updates, SEO insights and site auditing from Digizu.

== Description ==

Sitewell by Digizu securely connects a WordPress website to the Sitewell website-care service.

Once connected, Sitewell can provide approved website releases while the existing WordPress administration, login, REST API, scheduled tasks and other operational routes remain available. Website updates are downloaded over HTTPS, verified using their expected size and SHA-256 checksum, inspected for unsafe paths and unsupported files, and activated as a complete release.

A Sitewell account and a connection code supplied through Sitewell are required. Sitewell is available to customers onboarded by Digizu. The plugin itself does not collect visitor information or add tracking to the public website.

Learn more at https://sitewell.digizu.co.uk.

== External service ==

This plugin connects to Sitewell, a service operated by Digizu, in order to pair the WordPress installation, check the connection, discover approved website updates, download those updates and report successful activation.

When an administrator chooses to connect, the plugin sends the one-time connection code, this WordPress site's public URL and the installed plugin version to Sitewell. Once connected, it sends an installation credential with update checks, the active Sitewell release identifier when checking for a newer version, and confirmation when a release is activated. It downloads approved website files from Sitewell. These requests occur when an administrator uses the connection controls, when Sitewell requests a deployment, and during the scheduled update check approximately every five minutes when WordPress cron runs.

The plugin does not send WordPress passwords, WordPress user details, form submissions or visitor analytics to Sitewell.

Sitewell service: https://sitewell.digizu.co.uk
Privacy policy: https://sitewell.digizu.co.uk/privacy-policy
Terms of service: https://sitewell.digizu.co.uk/terms-of-service

== Installation ==

1. Install and activate Sitewell by Digizu.
2. In Sitewell, open the website's Content tab and generate a WordPress connection code.
3. In WordPress, go to Settings > Sitewell by Digizu.
4. Review the service disclosure, enter the connection code and select Connect to Sitewell.
5. Select Check for updates if an approved release is already available.
6. Enable the Sitewell website and select Save.

The plugin will not replace the public WordPress website until a verified Sitewell release has been downloaded and the administrator enables it.

== Direct static hosting (required for fast cold asset loads) ==

The PHP router is a compatibility mode: it still boots WordPress. For direct
static delivery, configure the host before switching public traffic. Merely
updating the plugin or uploading _headers does not configure LiteSpeed caching.

1. Configure Sitewell's repository build settings. For Rowglo use publishing
   branch main, workflow .github/workflows/build-site.yml, artifact wordpress-site.
   Keep project_path for editable source; it is not the artifact output path.
   Run the workflow and deploy its artifact root, not the source ZIP or _site parent.
   All releases now require static-build-manifest.json version 1 and _headers.
   Existing committed build-output repositories must also satisfy that contract.
2. In wp-config.php define SITEWELL_STATIC_FRONTEND_PUBLIC_PATH as an absolute,
   dedicated filesystem directory outside the WordPress document root. PHP needs
   write access and symlink support; the static web server needs read access to
   it and to SITEWELL_STATIC_FRONTEND_RELEASES_PATH. Keep all sites isolated.
3. Use templates/static-nginx.conf in a dedicated static virtual host (including
   the normal MIME map, TLS and site security headers). Replace __PUBLIC_PATH__
   with that directory and __WORDPRESS_ORIGIN__ with a private WordPress listener.
   Nginx can front the existing LiteSpeed WordPress backend. Do not point that
   backend URL at this public virtual host: that would create a proxy loop.
   A LiteSpeed-only host needs equivalent virtual-host filesystem routing;
   WordPress rewrite rules and _headers alone cannot provide this behavior.
4. Serve public/current as the site root, and public/assets as the persistent
   fingerprinted asset store. Do not expose the private release parent. The
   template deliberately has no static-to-WordPress fallback. It preserves
   admin, login, REST (including root rest_route), AJAX/admin-post, cron, comments
   and XML-RPC through explicit paths. Add reviewed dynamic application routes
   explicitly; arbitrary POSTs do not become WordPress routes. External Sitewell
   forms and third-party widgets continue using their configured endpoints.
5. Exclude static traffic from LiteSpeed guest/HTML optimization and origin page
   caching. Purge existing HTML, old asset URLs and negative cache entries at
   cutover. Remove conflicting one-hour rules and bypass old physical document-
   root assets. Honor revalidation for HTML/aliases and immutable caching only
   for validated hashed assets. Static responses do not vary by cookies or user
   agent; compression may vary by Accept-Encoding. Preserve security headers in
   each Nginx location: add_header inheritance depends on host configuration.
6. Validate the preview with an empty cache and a repeat request before cutover.
   Test missing assets, menu/slider/cookie interactions and form submission.
   Cache warming is not a prerequisite. No production rollout is automatic.

Installation is serialized; immutable assets are published before an atomic
current symlink switch. Invalid releases leave the serving target unchanged.
Old hashed assets are retained so cached HTML and open pages survive deployment.
Budget disk space and remove them only after the cache lifetime and rollback
window have elapsed. HTML and unhashed aliases use max-age=0, must-revalidate;
missing assets use genuine 404 responses with no-store. No asset request fetches
WordPress, Sitewell or the export origin.

The WordPress enable checkbox and plugin deactivation control the PHP router
only. In direct-host mode the web server owns public routing: roll back its
configuration before disabling or uninstalling the plugin. Uninstall removes
managed private releases; the operator-managed public asset store is retained.

Local delivery regression tests require nginx on PATH and loopback listeners.
Run php artisan test --compact tests/Unit/StaticArtifactDeliveryTest.php from the
Sitewell repository. Set ROWGLO_STATIC_FIXTURE to a completed Rowglo output folder
to additionally install and test all real fingerprinted assets on MISS and HIT.

== Frequently Asked Questions ==

= Do I need a Sitewell account? =

Yes. A valid connection code from a Sitewell website is required. Contact sitewell@digizu.co.uk for help with onboarding.

= Does this disable the WordPress administration area? =

No. WordPress administration, login, REST API, AJAX, cron, WP-CLI and non-read requests bypass the public website router.

= Can I return to the original WordPress website? =

In PHP compatibility mode, clear the Use the Sitewell website checkbox and select Save. In direct-host mode, first restore the web-server routing as described above.

= What happens when I delete the plugin? =

The plugin attempts to revoke its Sitewell connection, then removes its local credentials, settings, scheduled update task and downloaded releases.

= How are website updates protected? =

Updates are downloaded directly from Sitewell over verified HTTPS. The plugin verifies the expected archive size and SHA-256 checksum, rejects unsafe or ambiguous paths, blocks executable and server-control files, applies file-count and size limits, and activates each release atomically.

== Privacy ==

The plugin does not add visitor tracking. Connection and deployment requests are limited to the operational data described in the External service section. See https://sitewell.digizu.co.uk/privacy-policy for details.

== Changelog ==

= 1.0.2 =
* Require complete optimized builds and verify hashed assets and dependencies.
* Publish an atomic static root and persistent immutable asset store for direct hosting.
* Return static 404s even without an exported error page.


= 1.0.1 =
* Serve deployed pages, assets, sitemaps and robots.txt before WordPress routing.
* Prevent page caching of static responses and preserve plain-permalink REST access.

= 1.0.0 =
* Initial public release.
* Added authenticated Sitewell connections and automated website releases.
* Added archive verification, safe routing and clean uninstall support.
