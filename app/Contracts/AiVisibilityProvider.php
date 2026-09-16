<?php

namespace App\Contracts;

interface AiVisibilityProvider
{
    public function key(): string;

    public function available(): bool;

    public function model(): string;

    /** @return array{text: string, citations: array<int, array<string, mixed>>, usage: array<string, mixed>, metadata: array<string, mixed>} */
    public function check(string $prompt, string $model): array;
}
