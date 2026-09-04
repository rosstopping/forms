<?php

/**
 * Plugin Name: Sitewell Static Frontend
 * Description: Safely serves an approved static frontend while retaining WordPress administration.
 * Version: 0.3.0
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Author: Digizu
 * Text Domain: sitewell-static-frontend
 */

declare(strict_types=1);
use Sitewell\StaticFrontend\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SITEWELL_STATIC_FRONTEND_VERSION', '0.3.0' );
define( 'SITEWELL_STATIC_FRONTEND_FILE', __FILE__ );
define( 'SITEWELL_STATIC_FRONTEND_PATH', plugin_dir_path( __FILE__ ) );

if ( ! defined( 'SITEWELL_STATIC_FRONTEND_API_URL' ) ) {
	define( 'SITEWELL_STATIC_FRONTEND_API_URL', 'https://sitewell.digizu.co.uk/api' );
}

require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/Contracts/StaticRootProvider.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/BundledStaticRootProvider.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/ActiveStaticRootProvider.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/ResolvedStaticFile.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/StaticPathResolver.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/BypassPolicy.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/FrontendRouter.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/SitewellClient.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/ReleaseInstaller.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/DeploymentManager.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/DeploymentEndpoint.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/Admin/ConnectionActions.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/Admin/SettingsPage.php';
require_once SITEWELL_STATIC_FRONTEND_PATH . 'src/Plugin.php';

register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Plugin::class, 'deactivate' ] );

Plugin::instance()->boot();
