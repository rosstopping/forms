=== Sitewell by Digizu ===
Contributors: digizu
Tags: website management, maintenance, seo, site audit
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.7
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

== Plugin updates ==

From version 1.0.5, this manually installed plugin receives updates through the
normal WordPress Plugins and Updates screens, using Sitewell's own update service.
After installing this version, select Enable auto-updates beside Sitewell by
Digizu on the Plugins screen if you want future plugin versions installed
automatically. WordPress scheduling, filesystem permissions and host policy apply.
The plugin must remain active for its self-hosted update integration to run.

Update checks fetch public version metadata from
https://sitewell.digizu.co.uk/wordpress/update.json and download the ZIP from
https://sitewell.digizu.co.uk/wordpress/download over HTTPS. No Sitewell pairing
credentials are sent. The downloaded ZIP is verified against the advertised
SHA-256 checksum before installation. An unavailable update service does not
interrupt the website. Updating does not enable the Sitewell frontend or opt you
into automatic updates; existing connection and delivery settings are retained.

Release maintainers: bump the plugin version and rebuild the tracked ZIP for each
release. Deploy the Sitewell endpoint and ZIP together before distributing the
updater-enabled plugin. The endpoint reads the version and requirements from the
ZIP and refuses downloads pinned to an obsolete version; old update notifications
may need a fresh WordPress update check.

== Delivery without server configuration ==

Version 1.0.4 makes ordinary WordPress uploads the default asset delivery path.
Enable the Sitewell website and select Save. No Nginx configuration, symlinks,
.htaccess changes or DNS changes are needed for this mode on a standard WordPress
host with publicly accessible uploads.

The plugin prepares the installed release, publishes CSS, JavaScript, images and
fonts under uploads/sitewell-assets, and rewrites the private HTML pages and
asset dependencies to use those physical URLs. Public pages still pass through
WordPress; asset requests can be served by the host as ordinary media files.
This removes the many asset requests from PHP without claiming to eliminate the
cost of the initial WordPress page request. Host caching headers remain in effect.

The original validated release is kept intact. Prepared pages activate only after
asset publication completes. Public asset directories have release-specific URLs
and are retained across deployments for cached pages and open browser tabs.
Local script/style integrity attributes are removed only when the corresponding
resource has been rewritten; external resources retain their integrity attributes.
Compiled HTML, CSS, JavaScript and assets can be deployed without
static-build-manifest.json or _headers. If a manifest is supplied, it must be valid.
Unsafe files, unbuilt templates and missing local dependencies remain rejected.

For an existing installation, save the enable setting once to prepare its current
release. Future releases are prepared automatically. Clearing the checkbox restores
the original WordPress website. Admin, REST, login and form POSTs retain their
existing WordPress routing. Custom dynamically constructed asset URLs can still
use the compatibility router.

If version 1.0.3 stopped at the Nginx verification message, simply update and save;
there is no need to install that generated configuration. If managed direct
routing was already active, saving switches its filesystem flag off and uses
uploads delivery instead. Existing configured direct hosts are not switched just
by updating the plugin.

Custom upload/CDN/offloading arrangements need testing to ensure newly published
files are publicly reachable. Test the site and forms on staging before Rowglo.
Uninstall removes private releases but retains public assets for old cached pages;
the host can remove uploads/sitewell-assets after the chosen retention period.

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

= 1.0.7 =
* Allow Google Fonts HTTPS stylesheets and font files in static releases.

= 1.0.6 =
* Allow compiled static sites without static-build-manifest.json or _headers.
* Preserve artifact safety checks and support ordinary asset filenames.

= 1.0.5 =
* Add self-hosted updates through the native WordPress updater, with optional automatic updates.
* Validate HTTPS release metadata and verify downloaded ZIP checksums before installation.


= 1.0.4 =
* Make normal WordPress uploads the default asset delivery path, without mandatory server configuration.
* Prepare rewritten pages and asset dependencies before activation; retain original releases and old asset URLs.
* Keep page delivery and operational endpoints in WordPress and support migration from the 1.0.3 setup.


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
