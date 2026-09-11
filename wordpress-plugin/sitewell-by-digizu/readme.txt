=== Sitewell by Digizu ===
Contributors: digizu
Tags: website management, maintenance, seo, site audit
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.0
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

== Frequently Asked Questions ==

= Do I need a Sitewell account? =

Yes. A valid connection code from a Sitewell website is required. Contact sitewell@digizu.co.uk for help with onboarding.

= Does this disable the WordPress administration area? =

No. WordPress administration, login, REST API, AJAX, cron, WP-CLI and non-read requests bypass the public website router.

= Can I return to the original WordPress website? =

Yes. Clear the Use the Sitewell website checkbox and select Save. Deactivating the plugin also stops its public routing immediately.

= What happens when I delete the plugin? =

The plugin attempts to revoke its Sitewell connection, then removes its local credentials, settings, scheduled update task and downloaded releases.

= How are website updates protected? =

Updates are downloaded directly from Sitewell over verified HTTPS. The plugin verifies the expected archive size and SHA-256 checksum, rejects unsafe or ambiguous paths, blocks executable and server-control files, applies file-count and size limits, and activates each release atomically.

== Privacy ==

The plugin does not add visitor tracking. Connection and deployment requests are limited to the operational data described in the External service section. See https://sitewell.digizu.co.uk/privacy-policy for details.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Added authenticated Sitewell connections and automated website releases.
* Added archive verification, safe routing and clean uninstall support.
