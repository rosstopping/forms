<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use Sitewell\StaticFrontend\Contracts\StaticRootProvider;

final class BundledStaticRootProvider implements StaticRootProvider {

	public function __construct( private readonly string $pluginPath ) {}

	public function path(): string {
		return rtrim( $this->pluginPath, '/\\' ) . DIRECTORY_SEPARATOR . 'fixture-site';
	}
}
