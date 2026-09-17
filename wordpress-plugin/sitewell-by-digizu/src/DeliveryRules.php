<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;

/** Server rules for the opt-in, filesystem-controlled delivery mode. */
final class DeliveryRules {

	public function __construct( private readonly string $root, private readonly string $token ) {
		if ( ! preg_match( '~^/[a-zA-Z0-9_./-]+$~D', $root ) || ! preg_match( '/^[a-f0-9]{32}$/D', $token ) ) {
			throw new RuntimeException( 'This hosting path requires a custom static delivery configuration.' );
		}
	}

	public function apache(): string {
		$path = $this->root . '/sitewell-static';

		return strtr(
			<<<'RULES'
# BEGIN Sitewell Direct
<IfModule mod_rewrite.c>
RewriteEngine On
# Internal rewrites may read the publication; visitors cannot browse its storage.
RewriteCond %{ENV:SITEWELL_DIRECT} !=1
RewriteCond %{ENV:REDIRECT_SITEWELL_DIRECT} !=1
RewriteRule ^sitewell-static(?:/|$) - [R=404,L]
RewriteRule ^sitewell-static/ - [L]
RewriteRule ^ - [E=SITEWELL_DIRECT:0]
RewriteCond %{ENV:REDIRECT_STATUS} ^$
RewriteCond __PATH__/.enabled -f [OR]
RewriteCond %{QUERY_STRING} ^sitewell_probe=__TOKEN__$
RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$
RewriteCond %{REQUEST_URI} !^/(?:wp-admin|wp-json|wp-content|wp-includes|\.well-known)(?:/|$) [NC]
RewriteCond %{REQUEST_URI} !^/(?:wp-login|wp-cron|wp-comments-post|xmlrpc)\.php(?:/|$) [NC]
RewriteCond %{QUERY_STRING} !(^|&)(rest_route|preview|customize_changeset_uuid|wc-ajax)(=|%3D|&|$) [NC]
RewriteRule ^ - [E=SITEWELL_DIRECT:1]
RewriteCond %{ENV:SITEWELL_DIRECT} =1
RewriteRule (^|/)(\.|_headers$|static-build-manifest\.json$)|\.(php[0-9]?|phtml|phar)(/|$) /sitewell-static/missing.txt [L,NC]
RewriteCond %{ENV:SITEWELL_DIRECT} =1
RewriteCond __PATH__/$1 -f
RewriteRule ^(assets/(?:fonts|static)/[a-zA-Z0-9_-]+\.[a-f0-9]{16}\.[a-zA-Z0-9]+)$ /sitewell-static/$1 [L]
RewriteCond %{ENV:SITEWELL_DIRECT} =1
RewriteCond __PATH__/current/$1 -f
RewriteRule ^([a-zA-Z0-9_./-]+)$ /sitewell-static/current/$1 [L]
RewriteCond %{ENV:SITEWELL_DIRECT} =1
RewriteCond %{REQUEST_URI} !\.[^/]+/?$
RewriteCond __PATH__/current/$1/index.html -f
RewriteRule ^((?:[a-zA-Z0-9_.-]+/)*[a-zA-Z0-9_-]*)/?$ /sitewell-static/current/$1/index.html [L]
RewriteCond %{ENV:SITEWELL_DIRECT} =1
RewriteCond __PATH__/current/$1.html -f
RewriteRule ^((?:[a-zA-Z0-9_.-]+/)*[a-zA-Z0-9_-]+)/?$ /sitewell-static/current/$1.html [L]
RewriteCond %{ENV:SITEWELL_DIRECT} =1
RewriteRule ^ /sitewell-static/missing.txt [L]
</IfModule>
# END Sitewell Direct

RULES,
			[
				'__PATH__'  => $path,
				'__TOKEN__' => $this->token,
			]
		);
	}

	public function apacheStorage(): string {
		return <<<'RULES'
Options -Indexes -ExecCGI
FileETag None
ErrorDocument 404 default
<IfModule mod_headers.c>
Header unset Last-Modified
Header always set X-Sitewell-Delivery "static"
Header always set X-Content-Type-Options "nosniff"
Header always set Cache-Control "no-cache, max-age=0, must-revalidate"
<FilesMatch "\.[a-f0-9]{16}\.[a-zA-Z0-9]+$">
Header always set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
</IfModule>
<FilesMatch "^(\.|_headers$|static-build-manifest\.json$)|\.(php[0-9]?|phtml|phar)$">
Require all denied
</FilesMatch>

RULES;
	}

	/** Include inside the existing WordPress server block, before other rewrite directives. */
	public function nginx(): string {
		return strtr(
			<<<'RULES'
# Sitewell managed delivery. Include in the existing WordPress server block,
# BEFORE other server-level rewrites. Keep existing WordPress/PHP locations.
# Validate with nginx -t, then reload. The plugin controls .enabled without reloads.
set $sitewell_direct 0;
if (-f __PATH__/.enabled) { set $sitewell_direct 1; }
if ($args = "sitewell_probe=__TOKEN__") { set $sitewell_direct 1; }
if ($request_method !~ ^(GET|HEAD)$) { set $sitewell_direct 0; }
if ($uri ~* "^/(wp-admin|wp-json|wp-content|wp-includes|\.well-known)(/|$)|^/(wp-login|wp-cron|wp-comments-post|xmlrpc)\.php(/|$)") { set $sitewell_direct 0; }
if ($args ~* "(^|&)(rest_route|preview|customize_changeset_uuid|wc-ajax)(=|%3D|&|$)") { set $sitewell_direct 0; }
if ($uri ~ "^/sitewell-static/") { set $sitewell_direct 0; }
if ($sitewell_direct = 1) { rewrite ^/(.*)$ /sitewell-static/serve/$1 last; }

# ^~ prevents existing PHP and static-extension regex locations intercepting these files.
location ^~ /sitewell-static/ {
    internal;
    root __PATH__/current;
    open_file_cache off;
    autoindex off;
    etag off;
    if_modified_since off;
    error_page 404 = @sitewell_missing;
    add_header X-Sitewell-Delivery static always;
    add_header X-Content-Type-Options nosniff always;
    add_header Cache-Control "no-cache, max-age=0, must-revalidate" always;
    if ($uri ~* "(^|/)(\.|_headers$|static-build-manifest\.json$)|\.(php[0-9]?|phtml|phar)(/|$)") { return 404; }
    rewrite "^/sitewell-static/serve/(assets/(fonts|static)/[a-zA-Z0-9_-]+\.[a-f0-9]{16}\.[a-zA-Z0-9]+)$" /sitewell-static/immutable/$1 last;
    rewrite "^/sitewell-static/serve/(.*\.[^/]+/?)$" /sitewell-static/file/$1 last;
    rewrite ^/sitewell-static/serve/(.*?)/?$ /$1 break;
    try_files $uri/index.html $uri.html =404;
}
location ^~ /sitewell-static/file/ {
    internal;
    root __PATH__/current;
    open_file_cache off;
    etag off;
    if_modified_since off;
    error_page 404 = @sitewell_missing;
    add_header X-Sitewell-Delivery static always;
    add_header X-Content-Type-Options nosniff always;
    add_header Cache-Control "no-cache, max-age=0, must-revalidate" always;
    rewrite ^/sitewell-static/file/(.*)$ /$1 break;
    try_files $uri =404;
}
location ^~ /sitewell-static/immutable/ {
    internal;
    alias __PATH__/;
    open_file_cache off;
    autoindex off;
    error_page 404 = @sitewell_missing;
    add_header X-Sitewell-Delivery static always;
    add_header X-Content-Type-Options nosniff always;
    add_header Cache-Control "public, max-age=31536000, immutable";
}
location @sitewell_missing {
    internal;
    add_header X-Sitewell-Delivery static always;
    add_header Cache-Control "no-store" always;
    return 404;
}

RULES,
			[
				'__PATH__'  => $this->root . '/sitewell-static',
				'__TOKEN__' => $this->token,
			]
		);
	}
}
