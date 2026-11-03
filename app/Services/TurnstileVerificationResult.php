<?php

namespace App\Services;

class TurnstileVerificationResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly string $reason,
        public readonly bool $unavailable = false,
    ) {}
}
