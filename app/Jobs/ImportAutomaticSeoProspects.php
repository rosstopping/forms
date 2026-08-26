<?php

namespace App\Jobs;

use App\Models\SeoProspectSearch;
use App\Services\SeoProspectImporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportAutomaticSeoProspects implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public SeoProspectSearch $search) {}

    public function uniqueId(): string
    {
        return (string) $this->search->id;
    }

    public function handle(SeoProspectImporter $importer): void
    {
        $this->search->refresh()->loadMissing('owner');

        if (! $this->search->automated || ! $this->search->owner) {
            return;
        }

        $candidateIds = $this->search->candidates()
            ->where('qualification_status', 'suitable')
            ->where('commercial_opportunity_score', '>=', $this->search->automatic_import_score ?? 65)
            ->orderByDesc('commercial_opportunity_score')
            ->limit(20)
            ->pluck('id')
            ->all();

        if ($candidateIds !== []) {
            $importer->import($this->search, $candidateIds, $this->search->owner);
        }
    }
}
