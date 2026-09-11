<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Admin;

use Sitewell\StaticFrontend\Contracts\StaticRootProvider;

final class SettingsPage {

	public const OPTION_ENABLED = 'sitewell_static_frontend_enabled';

	public const OPTION_CONNECTION = 'sitewell_static_frontend_connection';

	public const OPTION_ACTIVE_RELEASE = 'sitewell_static_frontend_active_release';

	public const OPTION_PREVIOUS_RELEASE = 'sitewell_static_frontend_previous_release';

	public function __construct( private readonly StaticRootProvider $staticRoot ) {}

	public function register(): void {
		add_options_page(
			__( 'Sitewell by Digizu', 'sitewell-static-frontend' ),
			__( 'Sitewell by Digizu', 'sitewell-static-frontend' ),
			'manage_options',
			'sitewell-static-frontend',
			[ $this, 'render' ],
		);
	}

	public function registerSettings(): void {
		register_setting(
			'sitewell_static_frontend',
			self::OPTION_ENABLED,
			[
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => static fn ( mixed $value ): bool => self::activeRelease() !== null && ( $value === '1' || $value === 1 || $value === true ),
			]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this setting.', 'sitewell-static-frontend' ) );
		}

		$enabled         = self::isEnabled();
		$root            = realpath( $this->staticRoot->path() );
		$available       = $root !== false && is_dir( $root ) && is_readable( $root ) && is_readable( $root . '/index.html' );
		$connection      = self::connection();
		$notice_key      = 'sitewell_static_frontend_notice_' . get_current_user_id();
		$notice          = get_transient( $notice_key );
		$deploymentError = get_option( 'sitewell_static_frontend_last_deployment_error' );
		delete_transient( $notice_key );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Sitewell by Digizu', 'sitewell-static-frontend' ); ?></h1>
			<p><?php echo esc_html__( 'Keep your public website updated through Sitewell while retaining access to WordPress administration.', 'sitewell-static-frontend' ); ?></p>

		<?php if ( is_array( $notice ) && is_string( $notice['message'] ?? null ) ) { ?>
				<div class="notice <?php echo 'success' === ( $notice['type'] ?? null ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
		<?php } ?>
		<?php if ( is_string( $deploymentError ) && $deploymentError !== '' ) { ?>
			<?php /* translators: %s: Error returned by the latest automatic Sitewell deployment attempt. */ ?>
				<div class="notice notice-warning"><p><?php echo esc_html( sprintf( __( 'The last automatic update failed: %s', 'sitewell-static-frontend' ), $deploymentError ) ); ?></p></div>
		<?php } ?>

			<hr>

			<h2><?php echo esc_html__( 'Sitewell connection', 'sitewell-static-frontend' ); ?></h2>
		<?php if ( $connection !== null ) { ?>
				<p>
					<strong><?php echo esc_html__( 'Connected website:', 'sitewell-static-frontend' ); ?></strong>
			<?php echo esc_html( $connection['website']['name'] ); ?>
			<?php if ( $connection['website']['domain'] ) { ?>
						(<?php echo esc_html( $connection['website']['domain'] ); ?>)
			<?php } ?>
				</p>
			<?php if ( is_string( $connection['last_checked_at'] ?? null ) ) { ?>
				<?php /* translators: %s: Date and time of the most recent Sitewell connection check. */ ?>
					<p><?php echo esc_html( sprintf( __( 'Last checked: %s UTC', 'sitewell-static-frontend' ), gmdate( 'j M Y, H:i', strtotime( $connection['last_checked_at'] ) ) ) ); ?></p>
			<?php } ?>
				<div style="display:flex;gap:8px;align-items:center">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sitewell_static_frontend_deploy">
			<?php wp_nonce_field( 'sitewell_static_frontend_deploy' ); ?>
			<?php submit_button( __( 'Check for updates', 'sitewell-static-frontend' ), 'primary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sitewell_static_frontend_heartbeat">
			<?php wp_nonce_field( 'sitewell_static_frontend_heartbeat' ); ?>
			<?php submit_button( __( 'Test connection', 'sitewell-static-frontend' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sitewell_static_frontend_disconnect">
			<?php wp_nonce_field( 'sitewell_static_frontend_disconnect' ); ?>
			<?php submit_button( __( 'Disconnect and revoke', 'sitewell-static-frontend' ), 'delete', 'submit', false ); ?>
					</form>
				</div>
		<?php } else { ?>
				<p><?php echo esc_html__( 'Generate a connection code from the website’s Content tab in Sitewell, then enter it below.', 'sitewell-static-frontend' ); ?></p>
				<p class="description">
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: Sitewell privacy policy URL. 2: Sitewell terms of service URL. */
							__( 'Connecting sends this site’s URL and plugin version to Sitewell. Sitewell will then check the connection and provide approved website updates. See the <a href="%1$s" target="_blank" rel="noopener noreferrer">privacy policy</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">terms of service</a>.', 'sitewell-static-frontend' ),
							esc_url( 'https://sitewell.digizu.co.uk/privacy-policy' ),
							esc_url( 'https://sitewell.digizu.co.uk/terms-of-service' ),
						)
					);
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sitewell_static_frontend_connect">
			<?php wp_nonce_field( 'sitewell_static_frontend_connect' ); ?>
					<label for="sitewell-static-frontend-code"><strong><?php echo esc_html__( 'Connection code', 'sitewell-static-frontend' ); ?></strong></label>
					<input id="sitewell-static-frontend-code" name="connection_code" type="text" class="regular-text code" maxlength="32" autocomplete="off" required>
			<?php submit_button( __( 'Connect to Sitewell', 'sitewell-static-frontend' ) ); ?>
				</form>
		<?php } ?>

		<?php /* translators: %s: Sitewell API URL used by this plugin. */ ?>
			<p class="description"><?php echo esc_html( sprintf( __( 'Sitewell API: %s', 'sitewell-static-frontend' ), SITEWELL_STATIC_FRONTEND_API_URL ) ); ?></p>

			<hr>

			<p>
				<strong><?php echo esc_html__( 'Status:', 'sitewell-static-frontend' ); ?></strong>
		<?php
		echo esc_html(
			! $available
			? __( 'Unavailable — the website files are missing or unreadable.', 'sitewell-static-frontend' )
			: ( $enabled ? __( 'Enabled', 'sitewell-static-frontend' ) : __( 'Disabled', 'sitewell-static-frontend' ) ),
		);
		?>
			</p>

		<?php if ( $available ) { ?>
			<p><strong><?php echo esc_html__( 'Website files:', 'sitewell-static-frontend' ); ?></strong> <code><?php echo esc_html( $root ); ?></code></p>
		<?php } ?>

		<?php $activeRelease = self::activeRelease(); ?>
		<?php if ( $activeRelease !== null ) { ?>
				<p><strong><?php echo esc_html__( 'Live Sitewell version:', 'sitewell-static-frontend' ); ?></strong> <code><?php echo esc_html( $activeRelease['release_id'] ); ?></code></p>
		<?php } ?>

			<form method="post" action="options.php">
		<?php settings_fields( 'sitewell_static_frontend' ); ?>
				<label for="sitewell-static-frontend-enabled">
					<input
						id="sitewell-static-frontend-enabled"
						name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>"
						type="checkbox"
						value="1"
		<?php checked( $enabled ); ?>
		<?php disabled( ! $available ); ?>
					>
		<?php echo esc_html__( 'Use the Sitewell website', 'sitewell-static-frontend' ); ?>
				</label>
				<p class="description"><?php echo esc_html__( 'Clear this checkbox and save to restore the original WordPress website immediately.', 'sitewell-static-frontend' ); ?></p>
		<?php submit_button( __( 'Save', 'sitewell-static-frontend' ) ); ?>
			</form>

			<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'View public homepage', 'sitewell-static-frontend' ); ?></a></p>
		</div>
		<?php
	}

	public static function isEnabled(): bool {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * @return array{connection_id: string, credential: string, webhook_secret?: string|null, website: array{name: string, domain: string|null}, connected_at?: string, last_checked_at?: string}|null
	 */
	public static function connection(): ?array {
		$connection = get_option( self::OPTION_CONNECTION );

		if ( ! is_array( $connection )
			|| ! is_string( $connection['connection_id'] ?? null )
			|| ! is_string( $connection['credential'] ?? null )
			|| ! is_array( $connection['website'] ?? null )
			|| ! is_string( $connection['website']['name'] ?? null )
		) {
			return null;
		}

		return $connection;
	}

	/**
	 * @return array{release_id: string, path: string, checksum: string, activated_at: string}|null
	 */
	public static function activeRelease(): ?array {
		$release = get_option( self::OPTION_ACTIVE_RELEASE );

		if ( ! is_array( $release )
			|| ! is_string( $release['release_id'] ?? null )
			|| ! is_string( $release['path'] ?? null )
			|| ! is_string( $release['checksum'] ?? null )
			|| ! is_string( $release['activated_at'] ?? null )
		) {
			return null;
		}

		return $release;
	}
}
