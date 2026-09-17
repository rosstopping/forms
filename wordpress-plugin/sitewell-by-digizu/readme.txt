=== Sitewell by Digizu ===
Contributors: digizu
Tags: website management, maintenance, seo, site audit
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.3
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
6. Enable the Sitewell website and select Save and verify delivery.

The plugin will not replace the public WordPress website until a verified Sitewell release has been downloaded and the administrator enables it.

== Fast delivery ==

Version 1.0.3 adds managed direct delivery: public pages, CSS, fonts and images
are served by the web server without loading WordPress, PHP or the database.
Existing enabled installations retain compatibility mode until an administrator
saves the enable setting to migrate. Installing the update does not switch traffic.

On a single site installed at the domain root:

1. Install a validated release. static-build-manifest.json v1 and _headers remain
   required, including for repositories containing committed compiled files.
2. Enable the Sitewell website and select Save and verify delivery.
3. Apache and LiteSpeed installations attempt a marked .htaccess block before
   existing WordPress rules. Other host rules are preserved. The host must permit
   rewrite rules, headers, filesystem writes and symlinks. LiteSpeed deployments
   still need staging validation on the target hosting configuration.
4. Nginx requires a one-time host configuration. The plugin displays the exact
   server-block configuration after the first attempt. The host must include it
   before other server rewrites, preserve existing PHP/WordPress routes, run
   nginx -t and reload, then retry activation. Preserve the site's MIME map, TLS
   and security headers in the generated static locations.
5. Activation checks the homepage, a hashed asset when present, and a missing
   asset using a private probe, then checks normal public URLs. Byte hashes,
   response status and static-delivery headers must match. Failed checks leave
   the switch off and restore the previous Apache rewrite configuration.
6. Test menus, cookie controls and forms on staging, then test concurrent cold
   requests. Purge existing upstream HTML and negative cache entries at cutover.

The switch is sitewell-static/.enabled in the WordPress root. Clearing the
checkbox, deactivating or uninstalling removes it so the original WordPress
routing resumes without reloading the web server. If WordPress administration
is inaccessible, the host can remove this file directly. The plugin never
silently falls back to PHP after managed direct delivery has been configured.

Admin, login, REST (including rest_route), wp-content/wp-includes, cron,
comments, XML-RPC, .well-known and non-GET/HEAD requests retain WordPress routing.
Custom dynamic GET endpoints need a reviewed host configuration. Public static
misses are genuine 404s. HTML and aliases revalidate; hashed assets are immutable.

The plugin copies validated releases into sitewell-static/versions outside the
protected private release tree and atomically switches current. It retains the
current and previous snapshots and keeps old hashed assets for cached HTML and
open tabs. The public store remains after uninstall; the host may remove it after
its cache/rollback retention period. Budget disk space for retained hashed assets.

Multisite, subdirectory WordPress installations and unsupported web servers are
not automatically configured. A failed setup displays the required host action
instead of enabling slow PHP delivery. Test on staging before production cutover.

== Legacy custom static hosts ==

SITEWELL_STATIC_FRONTEND_PUBLIC_PATH and templates/static-nginx.conf remain
available for existing operator-managed virtual hosts. These legacy routes are
independent of the plugin switch: restore the original server configuration before
disabling or uninstalling. Remove/migrate the legacy constant and server rules
before using managed delivery. The plugin will not migrate these automatically.

Local server regression tests require nginx on PATH, macOS Apache and permission
to bind loopback sockets. Actual LiteSpeed and WordPress/plugin-stack validation
must also be performed on staging.

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

= 1.0.3 =
* Add verified direct delivery with managed Apache/LiteSpeed rules and generated Nginx configuration.
* Restore original WordPress routing through a filesystem switch on disable and deactivation.
* Preserve operational routes, retain immutable assets and test real Apache/Nginx delivery.


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
