<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Contracts;

interface StaticRootProvider
{

    public function path(): string;
}
