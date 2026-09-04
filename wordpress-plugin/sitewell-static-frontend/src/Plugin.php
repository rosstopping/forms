<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use Sitewell\StaticFrontend\Admin\ConnectionActions;
use Sitewell\StaticFrontend\Admin\SettingsPage;

final class Plugin {

	private static ?self $instance = null;

	private readonly ActiveStaticRootProvider $staticRoot;

	private readonly FrontendRouter $router;

	private readonly SettingsPage $settingsPage;

	private readonly ConnectionActions $connectionActions;

	private readonly DeploymentManager $deployments;

	private readonly DeploymentEndpoint $deploymentEndpoint;

	private function __construct() {
		$bundledRoot              = new BundledStaticRootProvider( SITEWELL_STATIC_FRONTEND_PATH );
		$this->staticRoot         = new ActiveStaticRootProvider( $bundledRoot );
		$this->router             = new FrontendRouter(
			new StaticPathResolver( $this->staticRoot->path() ),
			new BypassPolicy(),
			SITEWELL_STATIC_FRONTEND_PATH . 'templates/static-router.php',
		);
		$this->settingsPage       = new SettingsPage( $this->staticRoot );
		$client                   = new SitewellClient( SITEWELL_STATIC_FRONTEND_API_URL );
		$uploads                  = wp_upload_dir();
		$releasesPath             = rtrim( (string) ( $uploads['basedir'] ?? '' ), '/\\' ) . '/sitewell-static-frontend/releases';
		$this->deployments        = new DeploymentManager( $client, new ReleaseInstaller( $releasesPath ) );
		$this->deploymentEndpoint = new DeploymentEndpoint( $this->deployments );
		$this->connectionActions  = new ConnectionActions( $client, $this->deployments );
	}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public static function activate(): void {
		if ( get_option( SettingsPage::OPTION_ENABLED, null ) === null ) {
			add_option( SettingsPage::OPTION_ENABLED, false, '', false );
		}

        // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Five minutes is the deliberate deployment fallback interval.
		add_filter( 'cron_schedules', [ self::class, 'cronSchedules' ] );
		self::scheduleUpdates();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'sitewell_static_frontend_check_updates' );
	}

	public function boot(): void {
		add_action( 'admin_menu', [ $this->settingsPage, 'register' ] );
		add_action( 'admin_init', [ $this->settingsPage, 'registerSettings' ] );
		add_action( 'admin_post_sitewell_static_frontend_connect', [ $this->connectionActions, 'connect' ] );
		add_action( 'admin_post_sitewell_static_frontend_heartbeat', [ $this->connectionActions, 'heartbeat' ] );
		add_action( 'admin_post_sitewell_static_frontend_disconnect', [ $this->connectionActions, 'disconnect' ] );
		add_action( 'admin_post_sitewell_static_frontend_deploy', [ $this->connectionActions, 'deploy' ] );
		add_action( 'rest_api_init', [ $this->deploymentEndpoint, 'register' ] );
		add_action( 'sitewell_static_frontend_check_updates', [ $this, 'checkForUpdates' ] );
        // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Five minutes is the deliberate deployment fallback interval.
		add_filter( 'cron_schedules', [ self::class, 'cronSchedules' ] );
		self::scheduleUpdates();

		if ( ! SettingsPage::isEnabled() ) {
			return;
		}

		add_filter( 'template_include', [ $this->router, 'template' ], PHP_INT_MAX );
		add_filter( 'redirect_canonical', [ $this->router, 'disableCanonicalRedirect' ], PHP_INT_MAX );
	}

	public function render(): void {
		$this->router->render();
	}

	/** @param array<string, array{interval: int, display: string}> $schedules */
	public static function cronSchedules( array $schedules ): array {
		$schedules['sitewell_five_minutes'] = [
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'sitewell-static-frontend' ),
		];

		return $schedules;
	}

	public function checkForUpdates(): void {
		if ( SettingsPage::connection() === null ) {
			return;
		}

		try {
			$this->deployments->checkForUpdate();
		} catch ( \RuntimeException $exception ) {
			update_option( 'sitewell_static_frontend_last_deployment_error', sanitize_text_field( $exception->getMessage() ), false );
		}
	}

	private static function scheduleUpdates(): void {
		if ( ! wp_next_scheduled( 'sitewell_static_frontend_check_updates' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'sitewell_five_minutes', 'sitewell_static_frontend_check_updates' );
		}
	}
}
