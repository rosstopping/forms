<?php

namespace App\Data;

final readonly class DeploymentResult
{
    public function __construct(
        public bool $successful,
        public ?string $message = null,
    ) {}

    public static function succeeded(?string $message = null): self
    {
        return new self(true, $message);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}
