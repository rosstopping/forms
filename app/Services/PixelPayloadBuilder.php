<?php

namespace App\Services;

use App\Enums\DeploymentMethod;
use App\Enums\OptimisationStatus;
use App\Models\Optimisation;

class PixelPayloadBuilder
{
    public function __construct(
        private PixelUrlNormalizer $urls,
        private PixelSiteResolver $sites,
    ) {}

    /** @return array{version: int, url: string, changes: array<int, array<string, mixed>>}|null */
    public function build(string $siteKey, string $url): ?array
    {
        $website = $this->sites->resolve($siteKey, $url);

        if (! $website) {
            return null;
        }

        $changes = $website->optimisations()
            ->with(['currentVersion' => fn ($query) => $query->select([
                'optimisation_versions.id',
                'optimisation_versions.optimisation_id',
                'optimisation_versions.new_value',
            ])])
            ->where('url_hash', $this->urls->hash($url))
            ->where('status', OptimisationStatus::Deployed)
            ->where('deployment_method', DeploymentMethod::Pixel)
            ->orderBy('id')
            ->get()
            ->map(fn (Optimisation $optimisation): array => array_filter([
                'id' => $optimisation->public_id,
                'type' => $optimisation->type->value,
                'selector' => $optimisation->selector,
                'attribute' => $optimisation->attribute,
                'value' => $optimisation->currentVersion?->new_value,
            ], fn (mixed $value): bool => $value !== null))
            ->filter(fn (array $change): bool => array_key_exists('value', $change))
            ->values()
            ->all();

        return [
            'version' => (int) $website->pixel_payload_version,
            'url' => $url,
            'changes' => $changes,
        ];
    }
}
