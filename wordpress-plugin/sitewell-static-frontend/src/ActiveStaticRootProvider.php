<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use Sitewell\StaticFrontend\Admin\SettingsPage;
use Sitewell\StaticFrontend\Contracts\StaticRootProvider;

final class ActiveStaticRootProvider implements StaticRootProvider {

	public function __construct( private readonly StaticRootProvider $fallback ) {}

	public function path(): string {
		$release = get_option( SettingsPage::OPTION_ACTIVE_RELEASE );

		if ( is_array( $release )
			&& is_string( $release['path'] ?? null )
			&& is_dir( $release['path'] )
			&& is_readable( $release['path'] . '/index.html' ) ) {
			return $release['path'];
		}

		return $this->fallback->path();
	}
}
