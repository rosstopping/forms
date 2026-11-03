<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;

/** A concurrent update should be acknowledged without tying up another worker. */
final class DeploymentInProgress extends RuntimeException {}
