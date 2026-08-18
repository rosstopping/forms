<?php

namespace App\Contracts;

use App\Data\SerpSearchResponse;

interface SerpProvider
{
    public function search(string $keyword, string $location, int $depth = 100): SerpSearchResponse;
}
