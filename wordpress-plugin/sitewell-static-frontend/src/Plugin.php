<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use Sitewell\StaticFrontend\Admin\ConnectionActions;
use Sitewell\StaticFrontend\Admin\SettingsPage;

final class Plugin {

	private static ?self $instance = null;

	private readonly BundledStaticRootProvider $staticRoot;

	private readonly FrontendRouter $router;

	private readonly SettingsPage $settingsPage;

	private readonly ConnectionActions $connectionActions;

	private function __construct() {
		$this->staticRoot        = new BundledStaticRootProvider( SITEWELL_STATIC_FRONTEND_PATH );
		$this->router            = new FrontendRouter(
			new StaticPathResolver( $this->staticRoot->path() ),
			new BypassPolicy(),
			SITEWELL_STATIC_FRONTEND_PATH . 'templates/static-router.php',
		);
		$this->settingsPage      = new SettingsPage( $this->staticRoot );
		$client                  = new SitewellClient( SITEWELL_STATIC_FRONTEND_API_URL );
		$this->connectionActions = new ConnectionActions( $client );
	}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public static function activate(): void {
		if ( get_option( SettingsPage::OPTION_ENABLED, null ) === null ) {
			add_option( SettingsPage::OPTION_ENABLED, false, '', false );
		}
	}

	public function boot(): void {
		add_action( 'admin_menu', [ $this->settingsPage, 'register' ] );
		add_action( 'admin_init', [ $this->settingsPage, 'registerSettings' ] );
		add_action( 'admin_post_sitewell_static_frontend_connect', [ $this->connectionActions, 'connect' ] );
		add_action( 'admin_post_sitewell_static_frontend_heartbeat', [ $this->connectionActions, 'heartbeat' ] );
		add_action( 'admin_post_sitewell_static_frontend_disconnect', [ $this->connectionActions, 'disconnect' ] );

		if ( ! SettingsPage::isEnabled() ) {
			return;
		}

		add_filter( 'template_include', [ $this->router, 'template' ], PHP_INT_MAX );
		add_filter( 'redirect_canonical', [ $this->router, 'disableCanonicalRedirect' ], PHP_INT_MAX );
	}

	public function render(): void {
		$this->router->render();
	}
}
