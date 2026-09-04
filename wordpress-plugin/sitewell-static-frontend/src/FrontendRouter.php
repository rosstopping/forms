<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

final class FrontendRouter
{

    private ?ResolvedStaticFile $preparedFile = null;

    private int $preparedStatus = 404;

    private string $preparedMethod = 'GET';

    public function __construct(
        private readonly StaticPathResolver $resolver,
        private readonly BypassPolicy $bypassPolicy,
        private readonly string $routerTemplate,
    ) {
    }

    public function shouldBypassCurrentRequest(): bool
    {
        return $this->bypassPolicy->shouldBypass(
            $this->requestUri(),
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            is_admin(),
            defined('DOING_AJAX') && DOING_AJAX,
            defined('DOING_CRON') && DOING_CRON,
            defined('REST_REQUEST') && REST_REQUEST,
            defined('WP_CLI') && WP_CLI,
        );
    }

    public function template( string $template ): string
    {
        if ($this->shouldBypassCurrentRequest() ) {
            return $template;
        }

        $this->preparedMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->preparedFile   = $this->resolver->resolve($this->requestUri());
        $this->preparedStatus = $this->preparedFile === null ? 404 : 200;
        $this->preparedFile ??= $this->resolver->fallback404();

        if ($this->preparedFile === null ) {
            return $template;
        }

        status_header($this->preparedStatus);
        header('Content-Type: ' . $this->preparedFile->contentType);

        if ($this->preparedFile->isHtml ) {
            nocache_headers();
        } else {
            header('Cache-Control: public, max-age=3600');
        }

        return $this->routerTemplate;
    }

    public function disableCanonicalRedirect( mixed $redirectUrl ): mixed
    {
        if ($this->shouldBypassCurrentRequest() ) {
            return $redirectUrl;
        }

        return false;
    }

    public function render(): void
    {
        if ($this->preparedMethod === 'HEAD' || $this->preparedFile === null ) {
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams a validated static file without evaluating it as PHP.
        readfile($this->preparedFile->path);
    }

    private function requestUri(): string
    {
        return is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
    }
}
