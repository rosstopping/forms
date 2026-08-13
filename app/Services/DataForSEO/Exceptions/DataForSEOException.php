<?php

namespace App\Services\DataForSEO\Exceptions;

use RuntimeException;
use Throwable;

class DataForSEOException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $endpoint = null,
        public readonly ?int $providerStatusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return array_filter([
            'provider' => 'dataforseo',
            'endpoint' => $this->endpoint,
            'provider_status_code' => $this->providerStatusCode,
        ], fn (mixed $value): bool => $value !== null);
    }
}
