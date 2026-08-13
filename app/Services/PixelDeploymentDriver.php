<?php

namespace App\Services;

use App\Contracts\DeploymentDriver;
use App\Data\DeploymentResult;
use App\Enums\DeploymentMethod;
use App\Models\Optimisation;
use InvalidArgumentException;

class PixelDeploymentDriver implements DeploymentDriver
{
    public function __construct(private OptimisationValueSanitizer $values) {}

    public function deploy(Optimisation $optimisation): DeploymentResult
    {
        if ($optimisation->deployment_method !== DeploymentMethod::Pixel) {
            return DeploymentResult::failed('The optimisation is not configured for Pixel deployment.');
        }

        if (! $optimisation->type->isPixelDeployable()) {
            return DeploymentResult::failed('This optimisation type cannot be deployed through Pixel.');
        }

        $requiresSelector = in_array($optimisation->type->value, [
            'text', 'html', 'append_html', 'prepend_html', 'attribute', 'image_alt', 'internal_link',
        ], true);

        if ($requiresSelector && blank($optimisation->selector)) {
            return DeploymentResult::failed('This optimisation type requires a target selector.');
        }

        try {
            $value = $optimisation->currentVersion?->new_value;
            $sanitized = $this->values->sanitize(
                $optimisation->type,
                (string) $value,
                $optimisation->attribute,
                $optimisation->url,
            );
        } catch (InvalidArgumentException $exception) {
            return DeploymentResult::failed($exception->getMessage());
        }

        if ($value === null || $sanitized !== $value) {
            return DeploymentResult::failed('The optimisation value must be stored in its sanitized canonical form before deployment.');
        }

        return DeploymentResult::succeeded();
    }

    public function rollback(Optimisation $optimisation): DeploymentResult
    {
        if ($optimisation->deployment_method !== DeploymentMethod::Pixel) {
            return DeploymentResult::failed('The optimisation is not configured for Pixel deployment.');
        }

        return DeploymentResult::succeeded();
    }
}
