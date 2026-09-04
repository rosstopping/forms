<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

final readonly class ResolvedStaticFile
{

    public function __construct(
        public string $path,
        public string $contentType,
        public bool $isHtml,
    ) {
    }
}
