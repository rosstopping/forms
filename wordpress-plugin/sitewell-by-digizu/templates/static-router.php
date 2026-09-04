<?php

declare(strict_types=1);
use Sitewell\StaticFrontend\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Plugin::instance()->render();
