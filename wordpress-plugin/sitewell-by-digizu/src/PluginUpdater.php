<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

/** Integrates self-hosted releases with WordPress's own update and opt-in automation. */
final class PluginUpdater {

	public const HOME = 'https://sitewell.digizu.co.uk/wordpress';

	public const CACHE = 'sitewell_plugin_update_metadata';

	public const SLUG = 'sitewell-by-digizu';

	public function __construct( private readonly string $pluginFile ) {}

	public function boot(): void {
		add_filter( 'update_plugins_sitewell.digizu.co.uk', [ $this, 'update' ], 10, 3 );
		add_filter( 'plugins_api', [ $this, 'information' ], 10, 3 );
		add_filter( 'upgrader_pre_download', [ $this, 'download' ], 10, 2 );
	}

	/** @param array<string, mixed> $pluginData */
	public function update( mixed $update, array $pluginData, string $pluginFile ): mixed {
		if ( $pluginFile !== $this->pluginFile ) {
			return $update;
		}
		$metadata = $this->metadata();
		if ( $metadata === null ) {
			return $update;
		}

		return [
			'id'           => self::HOME,
			'slug'         => self::SLUG,
			'version'      => $metadata['version'],
			'url'          => self::HOME,
			'package'      => $metadata['package'],
			'requires'     => $metadata['requires'],
			'requires_php' => $metadata['requires_php'],
			'tested'       => $metadata['tested'],
		];
	}

	public function information( mixed $result, string $action, object $arguments ): mixed {
		if ( $action !== 'plugin_information' || ( $arguments->slug ?? '' ) !== self::SLUG ) {
			return $result;
		}
		$metadata = $this->metadata();
		if ( $metadata === null ) {
			return $result;
		}

		return (object) [
			'name'          => 'Sitewell by Digizu',
			'slug'          => self::SLUG,
			'version'       => $metadata['version'],
			'author'        => 'Digizu',
			'homepage'      => self::HOME,
			'download_link' => $metadata['package'],
			'requires'      => $metadata['requires'],
			'requires_php'  => $metadata['requires_php'],
			'tested'        => $metadata['tested'],
			'sections'      => [ 'description' => 'Website updates and care through Sitewell. Automatic plugin updates can be enabled from the WordPress Plugins screen.' ],
		];
	}

	/** Verify the advertised checksum before handing the ZIP to the native upgrader. */
	public function download( mixed $reply, string $package ): mixed {
		if ( ! str_starts_with( $package, self::HOME . '/download?' ) ) {
			return $reply;
		}
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		$metadata = $this->metadata();
		if ( $metadata === null || $package !== $metadata['package'] ) {
			delete_site_transient( self::CACHE );

			return new \WP_Error( 'sitewell_update_changed', __( 'The Sitewell release changed or could not be verified. Check for updates again.', 'sitewell-static-frontend' ) );
		}
		$file = $reply === false ? download_url( $package, 60 ) : $reply;
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! is_string( $file ) || ! is_file( $file ) || ! hash_equals( $metadata['sha256'], (string) hash_file( 'sha256', $file ) ) ) {
			if ( is_string( $file ) && is_file( $file ) ) {
				wp_delete_file( $file );
			}

			return new \WP_Error( 'sitewell_update_checksum', __( 'The Sitewell plugin download failed verification. Please retry the update.', 'sitewell-static-frontend' ) );
		}

		return $file;
	}

	/** @return array<string, string>|null */
	private function metadata(): ?array {
		$cached = get_site_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $this->valid( $cached ) ? $cached : null;
		}
		$response = wp_remote_request(
			self::HOME . '/update.json',
			[
				'method'              => 'GET',
				'timeout'             => 8,
				'redirection'         => 0,
				'sslverify'           => true,
				'limit_response_size' => 16384,
				'headers'             => [ 'Accept' => 'application/json' ],
			]
		);
		$data     = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200
			? json_decode( wp_remote_retrieve_body( $response ), true ) : null;
		$valid    = is_array( $data ) && $this->valid( $data );
		set_site_transient( self::CACHE, $valid ? $data : [], $valid ? 600 : 60 );

		return $valid ? $data : null;
	}

	/** @param array<string, mixed> $data */
	private function valid( array $data ): bool {
		foreach ( [ 'version', 'package', 'sha256', 'requires', 'requires_php', 'tested' ] as $key ) {
			if ( ! is_string( $data[ $key ] ?? null ) ) {
				return false;
			}
		}

		return preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+$/D', $data['version'] ) === 1
			&& preg_match( '/^[a-f0-9]{64}$/D', $data['sha256'] ) === 1
			&& $data['package'] === self::HOME . '/download?version=' . $data['version']
			&& preg_match( '/^\d+\.\d+(?:\.\d+)?$/D', $data['requires'] ) === 1
			&& preg_match( '/^\d+\.\d+(?:\.\d+)?$/D', $data['requires_php'] ) === 1
			&& preg_match( '/^\d+\.\d+(?:\.\d+)?$/D', $data['tested'] ) === 1;
	}
}
