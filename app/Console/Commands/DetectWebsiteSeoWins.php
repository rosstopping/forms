<?php

namespace App\Console\Commands;

use App\Jobs\DetectSeoWins;
use App\Models\Website;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:detect-wins')]
#[Description('Detect SEO wins from saved observations without provider or AI calls')]
class DetectWebsiteSeoWins extends Command
{
    public function handle(): int
    {
        Website::where('is_active', true)->whereHas('seoTargetKeywords', fn ($query) => $query->whereNull('archived_at'))
            ->chunkById(100, function ($websites): void {
                foreach ($websites as $website) {
                    DetectSeoWins::dispatch($website);
                }
            });

        return self::SUCCESS;
    }
}
